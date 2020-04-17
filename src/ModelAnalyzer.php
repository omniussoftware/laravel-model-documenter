<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Enz0project\ModelDocumenter\Interfaces\FileContentsAnalyzer;
use Enz0project\ModelDocumenter\Interfaces\FileHelper;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Exception;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelAnalyzer {
	public static $newLine;

	private const LINEENDING_CRLF = "\r\n";
	private const LINEENDING_LFCR = "\n\r";
	private const LINEENDING_CR = "\r";
	private const LINEENDING_LF = "\n";

	protected DBHelper $dbHelper;
	protected FileHelper $fileHelper;
	protected ReflectionHelper $reflectionHelper;
	protected FileContentsAnalyzer $fileContentsAnalyzer;

	private $traitRelationsCache = [];
	private $requiredImports = [];
	private $lines;
	private $currentFile;
	private $modelFileType;
	private $traitsInModel;
	private $options;

	public function __construct() {
		if (!self::$newLine) {
			// Set newline var
			switch (config('modeldocumenter.lineendings')) {
				case 'crlf':
					self::$newLine = self::LINEENDING_CRLF;
					break;
				case 'lfcr':
					self::$newLine = self::LINEENDING_LFCR;
					break;
				case 'cr':
					self::$newline = self::LINEENDING_CR;
					break;
				case 'lf':
				default:
					self::$newLine = self::LINEENDING_LF;
					break;
			}
		}

		$this->dbHelper = app()->make(DBHelper::class);
		$this->fileHelper = app()->make(FileHelper::class);
		$this->reflectionHelper = app()->make(ReflectionHelper::class);
		$this->fileContentsAnalyzer = app()->make(FileContentsAnalyzer::class);

		$this->options = config('modeldocumenter.options');
	}

	public function analyze(string $filePath): ModelData {
		$this->currentFile = $filePath;
		$this->lines = $this->fileHelper->getLines($filePath);

		$classname = $this->fileContentsAnalyzer->getName($this->lines);
		$namespace = $this->fileContentsAnalyzer->getNamespace($this->lines);

		$reflectionClass = new ReflectionClass("$namespace\\$classname");
		$this->modelFileType = $this->reflectionHelper->getClassType($reflectionClass);

		// Get all relations from this class as well as any traits it has
		$relations = $this->analyzeRelations($reflectionClass, $this->lines);

		$properties = null;
		if ($this->modelFileType === ModelData::TYPE_CLASS) {
			$tableName = $this->reflectionHelper->getTableName($reflectionClass);
			$properties = $this->analyzeProperties($reflectionClass, $this->dbHelper->fetchColumnData($tableName));
		}


		if ($properties) {
			$properties = $this->sort($properties);
		}
		$relations = $this->sort($relations);


		$classDocBlock = $reflectionClass->getDocComment();

		if (!$this->classDocBlockIsValid($classDocBlock)) {
			$classDocBlock = null;
		} else {
			$classDocBlock .= self::$newLine;
		}

		$modelData = new ModelData(
			$classname,
			$this->modelFileType,
			$this->lines,
			$classDocBlock,
			$properties ?? [],
			$relations,
			$this->requiredImports,
			$reflectionClass
		);

		$this->reset();

		return $modelData;
	}

	/**
	 * Sorts an array if the ModelDocumenter options say it should; otherwise just returns the original array
	 *
	 * @param array $array
	 * @return array
	 */
	protected function sort(array $array): array {
		if (array_key_exists(ModelDocumenterOptions::SORT_DOCBLOCK, $this->options)) {
			static $sorts = [];

			if ($sorts === []) {
				$sorts = [
					ModelDocumenterOptions::SORT_NAME_ASC => function (&$array) { ksort($array); },
					ModelDocumenterOptions::SORT_NAME_DESC => function (&$array) { krsort($array); },
				];
			}

			$sorts[$this->options[ModelDocumenterOptions::SORT_DOCBLOCK]]($array);
		}

		return $array;
	}

	/**
	 * @param string $classDocBlock
	 * @return bool
	 */
	protected function classDocBlockIsValid(string $classDocBlock): bool {
		$phpstormHeaders = '/**' . self::$newLine . ' * Created by phpStorm.' . self::$newLine;

		if ($classDocBlock === self::$newLine) {
			return false;
		}

		if ($classDocBlock === '') {
			return false;
		}

		if (Str::startsWith($classDocBlock, $phpstormHeaders)) {
			return false;
		}

		return true;
	}

	/**
	 * @param ReflectionClass $reflectionClass
	 * @param array $lines
	 * @return array
	 * @throws Exception
	 */
	protected function analyzeRelations(ReflectionClass $reflectionClass, array $lines): array {
		$methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
		$this->traitsInModel = $reflectionClass->getTraits();

		$relations = [];

		foreach ($methods as $method) {
			$currMethod = $method;

			// If the class declaring this method isn't the model we're inside now, we skip over it; for now.
			$methodClassName = $method->getDeclaringClass()->getName();

			if ($reflectionClass->getName() !== $methodClassName) {
				continue;
			}

			$methodName = $method->getName();

			// If this method comes from a trait, we skip it for now; it will be handled later
			if ($this->methodIsInTrait($methodName)) {
				continue;
			}

			$startLine = $method->getStartLine();
			$endLine = $method->getEndLine();

			for ($i = $startLine; $i < $endLine; $i++) {
				$line = trim($lines[$i]);

				// TODO: Maybe add support for returns where the returned thing is on the line below the 'return' keyword
				if (Str::startsWith($line, 'return ')) {
					$relatedClassName = ModelDocumenterHelper::getRelatedClassName($line);
					if (null !== $relatedClassName) {
						$relations[$methodName] = $relatedClassName;
					}

					// If the model uses a Collection we need to either import or fully qualify them with namespace
					if (Str::startsWith($relatedClassName, 'Collection|') && !in_array('Collection', $this->requiredImports)) {
						$this->requiredImports[] = 'Collection';
					}
				}
			}
		}

		return $relations;
	}

	/**
	 * @param ReflectionClass $reflectionClass
	 * @param array $properties
	 * @return array
	 * @throws \ReflectionException
	 */
	protected function analyzeProperties(ReflectionClass $reflectionClass, array $properties): array {
		$dates = $this->reflectionHelper->getDates($reflectionClass);
		$propsToReturn = [];

		$carbonString = config('modeldocumenter.importCarbon', false) ? 'Carbon' : '\Carbon\Carbon';
		$nullableCarbonString = $carbonString . '|null';

		foreach ($properties as $property) {
			$phpType = $this->dbHelper->dbTypeToPHP($property);
			$propName = $property->Field;
			// If the prop is an integer and the property is in the $dates array, it is a Carbon
			if ($phpType === 'int' && in_array($propName, $dates)) {
				$phpType = $carbonString;
			} elseif ($phpType === 'int|null' && in_array($propName, $dates)) {
				$phpType = $nullableCarbonString;
			}

			$propsToReturn[$propName] = $phpType;

			// If the model uses a Carbon we need to either import or fully qualify them with namespace
			if (($phpType === $carbonString || $phpType === $nullableCarbonString) && !in_array('Carbon', $this->requiredImports)) {
				$this->requiredImports[] = 'Carbon';
			}
		}

		return $propsToReturn;
	}

	/**
	 * Checks if any of the models traits have a specific method
	 *
	 * @param $method
	 * @return bool
	 */
	private function methodIsInTrait($method): bool {
		foreach ($this->traitsInModel as $trait) {
			if ($trait->hasMethod($method)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resets the model analyzer so it's ready for the next file
	 */
	private function reset() {
		$this->currentFile = null;
		$this->modelFileType = null;
		$this->lines = null;
		$this->traitsInModel = null;
		$this->requiredImports = [];
	}
}

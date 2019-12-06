<?php


namespace Enz0project\ModelDocumenter;


use Exception;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelAnalyzer {
	public static $newLine;

	public const TYPE_INTERFACE = 1;
	public const TYPE_CLASS = 2;
	public const TYPE_ABSTRACT_CLASS = 3;

	private const LINEENDING_CRLF = "\r\n";
	private const LINEENDING_LFCR = "\n\r";
	private const LINEENDING_CR = "\r";
	private const LINEENDING_LF = "\n";

	protected $dbHelper;

	private $traitRelationsCache = [];
	private $requiredImports = [];
	private $lines;
	private $currentFile;
	private $modelFileType;
	private $traitsInModel;

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

		// TODO: bind in service provider
		$this->dbHelper = app()->make(DBHelper::class);
	}

	public function analyze(string $filePath) {
		$this->currentFile = $filePath;
		$this->lines = $this->getLines($filePath);

		$classname = $this->getName();
		$namespace = $this->getNamespaceFromFileContents($this->lines);

		$reflectionClass = new ReflectionClass("$namespace\\$classname");

		// Get all relations from this class as well as any traits it has
		$relations = $this->analyzeRelations($reflectionClass, $this->lines);
		foreach ($this->traitsInModel as $trait) {
			$relations = array_merge($relations, $this->getTraitRelations($trait));
		}

		$properties = null;
		if ($this->modelFileType === self::TYPE_CLASS) {
			$tableName = $this->getTableName($reflectionClass);
			$properties = $this->analyzeProperties($reflectionClass, $this->dbHelper->fetchColumnData($tableName));
		}

		// Finally, sort props and relations alphabetically by name
		if ($properties) {
			ksort($properties);
		}
		ksort($relations);

		$classDocBlock = $reflectionClass->getDocComment();

		if (!$this->classDocBlockIsValid($classDocBlock)) {
			$classDocBlock = null;
		} else {
			$classDocBlock .= self::$newLine;
		}

		$modelData = (object) [
			'name' => $classname,
			'type' => $this->modelFileType,
			'fileContents' => $this->lines,
			'classDocBlock' => $classDocBlock,
			'properties' => $properties ?? [],
			'relations' => $relations,
			'requiredImports' => $this->requiredImports,
			'reflectionClass' => $reflectionClass,
		];

		$this->reset();

		return $modelData;
	}

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
	 * Gets the table name from the $table property on the model
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string
	 * @throws \ReflectionException
	 */
	protected function getTableName(ReflectionClass $reflectionClass): string {
		$instance = $reflectionClass->newInstance();
		$table = $reflectionClass->getProperty('table');
		$table->setAccessible(true);
		$tableName = $table->getValue($instance);
		$table->setAccessible(false);

		return $tableName;
	}

	/**
	 * Gets the dates array from a model
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string
	 * @throws \ReflectionException
	 */
	protected function getDates(ReflectionClass $reflectionClass): array {
		$instance = $reflectionClass->newInstance();
		$dates = $reflectionClass->getProperty('dates');
		$dates->setAccessible(true);
		$datesValue = $dates->getValue($instance);
		$dates->setAccessible(false);

		return $datesValue;
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
			// TODO: Add support for getting relations from parent class as well
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
		$dates = $this->getDates($reflectionClass);
		$propsToReturn = [];

		foreach ($properties as $property) {
			$phpType = $this->dbHelper->dbTypeToPHP($property);
			$propName = $property->Field;

			// If the prop is an integer and the property is in the $dates array, it is a Carbon
			if ($phpType === 'int' && in_array($propName, $dates)) {
				$phpType = 'Carbon';
			}

			$propsToReturn[$propName] = $phpType;


			// If the model uses a Carbon we need to either import or fully qualify them with namespace
			if ($phpType === 'Carbon' && !in_array('Carbon', $this->requiredImports)) {
				$this->requiredImports[] = 'Carbon';
			}
		}

		return $propsToReturn;
	}

	/**
	 * Gets the name (and sets type) of the interface/class
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function getName(): string {
		foreach ($this->lines as $line) {
			if (Str::startsWith($line, 'interface')) {
				$this->modelFileType = self::TYPE_INTERFACE;
				$split = explode(' ', $line);

				// $key + 1 should always be the interface name
				$key = array_search('interface', $split);

				return $split[$key + 1];
			} elseif (Str::startsWith($line, 'abstract class ')) {
				$this->modelFileType = self::TYPE_ABSTRACT_CLASS;
			} else {
				if (Str::startsWith($line, 'class ')) {
					$this->modelFileType = self::TYPE_CLASS;
				}
			}

			if (null === $this->modelFileType) {
				continue;
			}

			$split = explode(' ', $line);

			// $key + 1 should always be the class name
			$key = array_search('class', $split);

			return $split[$key + 1];
		}

		if (null === $this->modelFileType) {
			throw new Exception("Could not extract class/interface name from file $this->currentFile");
		}
	}

	/**
	 * Reads namespace of Model file
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function getNamespaceFromFileContents(array $lines): string {
		foreach ($lines as $line) {
			if (Str::startsWith($line, 'namespace ')) {
				$split = explode(' ', $line);
				$key = array_search('namespace', $split);

				if (count($split) < $key + 1) {
					throw new Exception("Could not extract namespace from file $this->currentFile");
				}

				return str_replace([';', "\n", "\r"], '', $split[$key + 1]);
			}
		}
	}

	/**
	 * @param ReflectionClass $trait
	 * @return array
	 */
	private function getTraitRelations(ReflectionClass $trait): array {
		$key = $trait->getName();

		if (array_key_exists($key, $this->traitRelationsCache)) {
			return $this->traitRelationsCache[$key];
		}

		$traitFileContents = $this->getLines($trait->getFileName());
		$relations = $this->analyzeRelations($trait, $traitFileContents);

		$this->traitRelationsCache[$key] = $relations;

		return $relations;
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

	/**
	 * Reads file content into an array
	 *
	 * @param string $filePath
	 * @return array
	 */
	private function getLines(string $filePath): array {
		$file = fopen($filePath, 'r');

		$lines = [];

		while (!feof($file)) {
			$lines[] = fgets($file);
		}

		fclose($file);

		return $lines;
	}
}

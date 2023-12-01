<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelAnalyzer {
	public static $newLine;

	private const LINEENDING_CRLF = "\r\n";
	private const LINEENDING_LFCR = "\n\r";
	private const LINEENDING_CR = "\r";
	private const LINEENDING_LF = "\n";

	protected DBHelper $dbHelper;
	protected ReflectionHelper $reflectionHelper;

	private array $requiredImports = [];
	private array $options;

	public function __construct() {
		// TODO: Pick whatever the file uses?
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
		$this->reflectionHelper = app()->make(ReflectionHelper::class);

		$this->options = config('modeldocumenter.options');
	}

	public function analyze(string $filePath): ModelData {
		$lines = file($filePath);

		$classname = FileContentsAnalyzer::getName($lines);
		$namespace = FileContentsAnalyzer::getNamespace($lines);

		$reflectionClass = new ReflectionClass("$namespace\\$classname");

		// Get all relations from this class
		$relationData = $this->reflectionHelper->getRelations($reflectionClass, $lines);
		$relations = $relationData['relations'];
		$this->requiredImports = $relationData['requiredImports'];

		$properties = null;
		if (!$reflectionClass->isAbstract()) {
			$propertyData = $this->reflectionHelper->getProperties($reflectionClass);

			$properties = $propertyData['properties'];
			$this->requiredImports = array_merge($this->requiredImports, $propertyData['requiredImports']);
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
			$lines,
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
	 * Resets the model analyzer so it's ready for the next file
	 */
	private function reset() {
		$this->modelFileType = null;
		$this->requiredImports = [];
	}
}

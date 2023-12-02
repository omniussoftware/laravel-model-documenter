<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelAnalyzer {
	public static $newLine;

	private const LINEENDING_CRLF = "\r\n";
	private const LINEENDING_LFCR = "\n\r";
	private const LINEENDING_CR = "\r";
	private const LINEENDING_LF = "\n";

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
	}

	public function analyze(string $filePath): ModelData {
		$lines = file($filePath);

		$classname = FileContentsAnalyzer::getName($lines);
		$namespace = FileContentsAnalyzer::getNamespace($lines);

		$reflectionClass = new ReflectionClass("$namespace\\$classname");

		// Get all relations from this class
		[$relations, $requiredImports] = $this->getRelations($reflectionClass, $lines);

		$properties = null;
		if (!$reflectionClass->isAbstract()) {
			$propertyData = $this->getProperties($reflectionClass);

			$properties = $propertyData['properties'];
			$requiredImports = array_merge($requiredImports, $propertyData['requiredImports']);
		}

		$classDocBlock = $reflectionClass->getDocComment();
		if (!$this->classDocBlockIsValid($classDocBlock)) {
			$classDocBlock = null;
		} else {
			$classDocBlock .= self::$newLine;
		}

		return new ModelData(
			$classname,
			$lines,
			$classDocBlock,
			$properties ?? [],
			$relations,
			$requiredImports,
			$reflectionClass
		);
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

	private function getProperties(ReflectionClass $reflectionClass): array {
		$reflectedInstance = $reflectionClass->newInstance();

		$dates = $reflectedInstance->getDates();
		$propsToReturn = [];
		$requiredImports = [];

		$dbColumns = DB::select('DESCRIBE `' . $reflectedInstance->getTable() . '`');
		foreach ($dbColumns as $column) {
			// If it's in $dates it's always a Carbon
			if (in_array($column->Field, $dates)) {
				$phpType = 'Carbon';
			} else {
				$colType = $column->Type;
				$phpType = '';
				if (Str::contains($colType, 'int')) {
					$phpType = 'int';
				} elseif ($colType === 'time' || Str::contains($colType, ['varchar', 'text', 'char', 'json', 'enum'])) {
					$phpType = 'string';
				} elseif (Str::contains($colType, ['timestamp', 'date'])) {
					$phpType = 'Carbon';
				} elseif (Str::contains($colType, 'decimal')) {
					$phpType = 'float';
				}

				if (strlen($phpType) === 0) {
					throw new \InvalidArgumentException("Could not parse type from `$colType`");
				}
			}
			if ($column->Null === 'YES') {
				$phpType .= '|null';
			}

			// If the model uses a Carbon we need to either import or fully qualify them with namespace
			if (Str::startsWith($phpType, 'Carbon') && !in_array('Carbon', $requiredImports)) {
				$requiredImports[] = 'Carbon';
			}
			$propsToReturn[$column->Field] = $phpType;
		}

		return [
			'properties' => $propsToReturn,
			'requiredImports' => $requiredImports,
		];
	}

	private function getRelations(ReflectionClass $reflectionClass, array $lines): array {
		$methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
		$traitsInModel = $reflectionClass->getTraits();

		$requiredImports = [];
		$relations = [];

		foreach ($methods as $method) {
			// If the class declaring this method isn't the model we're inside now, we skip over it; for now.
			$methodClassName = $method->getDeclaringClass()->getName();
			if ($reflectionClass->getName() !== $methodClassName) {
				continue;
			}
			$methodName = $method->getName();

			// If this method comes from a trait, we skip it for now; it will be handled later
			// if (collect($traitsInModel)->contains->hasMethod($method)) {
			if ($this->methodIsInTrait($method, $traitsInModel)) {
				continue;
			}

			$startLine = $method->getStartLine();
			$endLine = $method->getEndLine();
			for ($i = $startLine; $i < $endLine; $i++) {
				$line = trim($lines[$i]);

				// TODO: Maybe add support for returns where the returned thing is on the line below the 'return' keyword
				if (Str::startsWith($line, 'return ')) {
					$relatedClassName = ModelDocumenterHelper::getReturnedClassName($line);
					if (null !== $relatedClassName) {
						$relations[$methodName] = $relatedClassName;
					}

					// If the model uses a Collection we need to either import or fully qualify them with namespace
					if (Str::startsWith($relatedClassName, 'Collection|') && !in_array('Collection', $requiredImports)) {
						$requiredImports[] = 'Collection';
					}
				}
			}
		}

		return [
			'relations' => $relations,
			'requiredImports' => $requiredImports,
		];
	}

	protected function methodIsInTrait($method, array $traitsInModel): bool {
		foreach ($traitsInModel as $trait) {
			if ($trait->hasMethod($method)) {
				return true;
			}
		}

		return false;
	}
}

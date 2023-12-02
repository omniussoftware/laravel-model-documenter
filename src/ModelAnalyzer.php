<?php


namespace Enz0project\ModelDocumenter;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelAnalyzer {
	public static string $newLine;

	public function analyze(string $filePath): ModelData {
		$lines = file($filePath);
		self::$newLine = ($lines[0][-2] ?? null) === "\r" ? "\r\n" : "\n";

		$classname = FileContentsAnalyzer::getName($lines);
		$namespace = FileContentsAnalyzer::getNamespace($lines);

		$reflectionClass = new ReflectionClass("$namespace\\$classname");

		// Get all relations from this class
		[$relations, $requiredImports] = $this->getRelations($reflectionClass, $lines);
		[$properties, $propertyImports] = $this->getProperties($reflectionClass);
		$requiredImports = array_merge($requiredImports, $propertyImports);

		$classDocBlock = $reflectionClass->getDocComment() ?: null;
		if ($classDocBlock) {
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

	private function getProperties(ReflectionClass $reflectionClass): array {
		if ($reflectionClass->isAbstract()) {
			return [[], []];
		}
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
			$propsToReturn,
			$requiredImports,
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
			if (collect($traitsInModel)->contains->hasMethod($methodName)) {
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
			$relations,
			$requiredImports,
		];
	}
}

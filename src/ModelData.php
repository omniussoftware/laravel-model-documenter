<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NotAClassException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelData {
	public string $newline;
	public string $name;
	public array $fileContents;
	public string $classDocBlock;
	public array $properties;
	public array $relations;
	public array $requiredImports;
	public ReflectionClass $reflectionClass;

	public function __construct($filePath) {
		$this->fileContents = file($filePath);
		$this->newline = ($this->fileContents[0][-2] ?? null) === "\r" ? "\r\n" : "\n";

		$this->reflectionClass = new ReflectionClass($this->getFQN($this->fileContents));
		$this->name = $this->reflectionClass->getName();
		$this->classDocBlock = with($this->reflectionClass->getDocComment(), fn ($x) => $x ? $x . $this->newline : '');

		[$this->relations, $relationImports] = $this->getRelations($this->reflectionClass, $this->fileContents);
		[$this->properties, $propertyImports] = $this->getProperties($this->reflectionClass);
		$this->requiredImports = array_merge($relationImports, $propertyImports);
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

	private function getFQN(array $lines): string {
		foreach ($lines as $line) {
			if (Str::startsWith($line, 'interface')) {
				throw new NotAClassException('This is an interface');
			}

			if (Str::startsWith($line, 'abstract class') || Str::startsWith($line, 'class')) {
				$split = explode(' ', $line);
				$name = $split[array_search('class', $split, true) + 1];
			} elseif (Str::startsWith($line, 'namespace ')) {
				$namespace = str_replace(['namespace ', ';', "\n", "\r"], '', $line);
			}
		}

		try {
			return "$namespace\\$name";
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException("Could not find class name and namespace!");
		}
	}
}
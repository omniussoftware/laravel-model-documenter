<?php


namespace Omniway\ModelDocumenter;


use Omniway\ModelDocumenter\Exceptions\NotAClassException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;

class ModelData {
	public static $toManyRelations = [
		'$this->hasMany(',
		'$this->belongsToMany(',
	];

	public static $allRelations = [
		'$this->belongsTo(',
		'$this->belongsToOneThrough(',
		'$this->hasOne(',
		'$this->hasOneThrough(',
		'$this->hasMany(',
		'$this->belongsToMany(',
	];


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
		$this->name = $this->reflectionClass->getShortName();
		$this->classDocBlock = with($this->reflectionClass->getDocComment(), fn ($x) => $x ? $x . $this->newline : '');

		$this->properties = $this->getProperties($this->reflectionClass);
		$this->relations = $this->getRelations($this->reflectionClass, $this->fileContents);
		$this->requiredImports = collect([
			collect($this->properties)->contains(fn ($x) => Str::startsWith($x, 'Carbon')) ? 'Carbon' : '',
			collect($this->relations)->contains(fn ($x) => Str::startsWith($x, 'Collection')) ? 'Collection' : '',
		])->filter()->values()->all();
	}

	private function getProperties(ReflectionClass $reflectionClass): array {
		if ($reflectionClass->isAbstract()) {
			return [];
		}
		$reflectedInstance = $reflectionClass->newInstance();
		$dates = $reflectedInstance->getDates();

		return collect(DB::select('DESCRIBE `' . $reflectedInstance->getTable() . '`'))
			->mapWithKeys(function ($column) use ($dates) {
				// If it's in $dates it's always a Carbon
				if (in_array($column->Field, $dates, true)) {
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

				return [$column->Field => $phpType . ($column->Null === 'YES' ? '|null' : '')];
			})->all();
	}

	private function getRelations(ReflectionClass $reflectionClass, array $lines): array {
		$traitsInModel = collect($reflectionClass->getTraits());

		return collect($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC))
			->filter(function ($method) use ($reflectionClass, $traitsInModel) {
				// If the class declaring this method isn't the model we're inside now, we skip over it; for now.
				if ($reflectionClass->getName() !== $method->getDeclaringClass()->getName()) {
					return false;
				}

				// If this method comes from a trait, we skip it for now; it will be handled later
				if ($traitsInModel->contains->hasMethod($method->getName())) {
					return false;
				}

				return true;
			})->mapWithKeys(function ($method) use ($lines) {
				$startLine = $method->getStartLine();
				$endLine = $method->getEndLine();
				for ($i = $startLine; $i < $endLine; $i++) {
					$line = trim($lines[$i]);

					// TODO: Maybe add support for returns where the returned thing is on the line below the 'return' keyword
					if (Str::startsWith($line, 'return ')) {
						return [$method->getName() => $this->getReturnedClassName($line)];
					}
				}

				return [];
			})->filter()->all();
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

	/**
	 * Gets the class name of the related class from a 'return $this->hasOne(...)' line
	 */
	private function getReturnedClassName(string $line): ?string {
		try {
			// If this method does not return a relation, we're not interested in it
			if (!Str::contains($line, self::$allRelations)) {
				return null;
			}

			// Finds first (, ignores first '", then grabs word characters (and backslashes). Then removes namespace
			preg_match("/^.*?\(['\"]?([\w\\\]+)/", $line, $matches);
			$className = explode('\\', $matches[1]);
			$className = end($className);

			// If its a relation that will return a Collection we need to specify that, i.e. 'Collection|Student[]'
			if (Str::contains($line, self::$toManyRelations)) {
				$className = 'Collection|' . $className . '[]';
			}
		} catch (\Exception $e) {
			return null;
		}

		return $className ?: null;
	}
}
<?php


namespace Enz0project\ModelDocumenter;


use Illuminate\Support\Str;

class ModelLineWriter {
	public static $oneToOneRelations = [
		'$this->belongsTo(',
		'$this->hasOne(',
		'$this->hasOneThrough(',
	];

	public static $oneOrManyToManyRelations = [
		'$this->hasMany(',
		'$this->belongsToMany(',
	];

	public static $allRelations = [
		'$this->belongsTo(',
		'$this->hasOne(',
		'$this->hasOneThrough(',
		'$this->hasMany(',
		'$this->belongsToMany(',
	];

	private ModelData $modelData;
	private array $lines = [];
	private string $stringToBeWritten;


	public function __construct(ModelData $modelData) {
		$this->modelData = $modelData;
	}

	public function replaceFileContents(): string {
		$classDeclarationString = ModelDocumenterHelper::getClassDeclaration($this->modelData);
		$hasOriginalDocBlock = $this->hasExistingDocBlock();
		$oldDocBlock = $this->modelData->classDocBlock;
		$useStatements = collect();

		$this->buildClassDocBlock();

		$isInsideClass = false;

		$previousLine = null;

		foreach ($this->modelData->fileContents as $key => $line) {
			// Ugly hack to remove newlines between class docblock and class declaration
			if ($isInsideClass
					&& Str::contains($previousLine, ' */') && $key !== count($this->modelData->fileContents) - 1
					&& Str::startsWith($this->modelData->fileContents[$key + 1], $classDeclarationString)) {
				continue;
			}

			// Store use statements
			if (!$isInsideClass && Str::startsWith($line, 'use ')) {
				$useStatements[] = $line;
			}

			if (!$isInsideClass && Str::startsWith($line, $classDeclarationString)) {
				// Add the class docblock before the class declaration!
				if (!$hasOriginalDocBlock) {
					$newBlock = explode(ModelAnalyzer::$newLine, $this->modelData->classDocBlock);

					foreach ($newBlock as $key => $classBlockLine) {
						// If its the last line we don't add a newline because otherwise there is a blank line between the
						// docblock and the class
						if ($key === count($newBlock) - 1) {
							$this->lines[] = $classBlockLine;
						} else {
							$this->lines[] = $classBlockLine . ModelAnalyzer::$newLine;
						}
					}
				}
				$this->lines[] = $line;
				$isInsideClass = true;
			} else {
				$this->lines[] = $line;
			}

			$previousLine = $line;
		}

		$this->stringToBeWritten = implode('', $this->lines);

		// If the model already has a docblock, replace it with the new one
		if ($hasOriginalDocBlock) {
			$this->stringToBeWritten = str_replace($oldDocBlock, $this->modelData->classDocBlock, $this->stringToBeWritten);
		}

		// Handle use statements
		if (!empty($this->modelData->requiredImports)) {
			$originalUseString = $useStatements->join('');
			if (in_array('Carbon', $this->modelData->requiredImports)) {
				if (!$useStatements->contains(fn ($line) => Str::contains($line, ['\Carbon;', 'as Carbon;']))) {
					$useStatements[] = 'use Carbon\Carbon;' . ModelAnalyzer::$newLine;
				}
			}

			if (in_array('Collection', $this->modelData->requiredImports)) {
				if (!$useStatements->contains(fn ($line) => Str::contains($line, ['\Collection;', 'as Collection;']))) {
					$useStatements[] = 'use Illuminate\Support\Collection;' . ModelAnalyzer::$newLine;
				}
			}

			$this->stringToBeWritten = str_replace($originalUseString,
				$useStatements->sort()->join(''),
				$this->stringToBeWritten);
		}

		return $this->stringToBeWritten;
	}

	/**
	 * Builds a new class level docblock. If there is a docblock already, this keeps the old one intact except any
	 * lines starting with "* @property"
	 */
	protected function buildClassDocBlock(): void {
		$modelData = $this->modelData;

		$linesToIgnore = [
			'/**',
			'abstract class ' . strtolower($modelData->name),
			'interface ' . strtolower($modelData->name),
			'class ' . strtolower($modelData->name),
			' * properties:',
			' * relations:',
			' * @property',
		];


		$properties = $modelData->properties;
		$relations = $modelData->relations;

		$originalDocBlock = explode(ModelAnalyzer::$newLine, $modelData->classDocBlock);

		$class = ucfirst(ModelDocumenterHelper::getClassDeclaration($modelData));

		$newDocBlock = [
			'/**',
			" * $class",
		];

		$previousLine = null;


		// Add the parts of the old docblock that we want to keep
		foreach ($originalDocBlock as $docBlockLine) {
			if ($docBlockLine === '') {
				continue;
			}

			// If this isn't the end of the docblock or a @property line, we add it
			if ($docBlockLine !== ' */' && !Str::contains(strtolower($docBlockLine), $linesToIgnore)) {
				// Don't stack multiple "blank" lines"
				if ($previousLine === ' *' && $docBlockLine === ' *') {
					continue;
				}
				$newDocBlock[] = $docBlockLine;
				$previousLine = $docBlockLine;
			}
		}


		// Then add the new props and relations:

		// First we add a "blank" line between any text and the properties block
		if (count($properties) > 0) {
			if ($previousLine !== ' *') {
				$newDocBlock[] = ' *';
			}

			$newDocBlock[] = ' * Properties:';
			$previousLine = ' * Properties:';
		}

		// Then, we add the properties block
		foreach ($properties as $propertyName => $propertyType) {
			$propLine = " * @property $propertyType $propertyName";
			$newDocBlock[] = $propLine;
			$previousLine = $propLine;
		}

		// First, we add a "blank" line between properties and relations
		if (count($relations) > 0) {
			if ($previousLine !== ' *') {
				$newDocBlock[] = ' *';
			}

			$newDocBlock[] = ' * Relations:';
			$previousLine = ' * Relations:';
		}

		// Then, we add the relations block
		foreach ($relations as $relationName => $relationType) {
			$relationLine = " * @property $relationType $relationName";
			$newDocBlock[] = $relationLine;
			$previousLine = $relationLine;
		}

		$newDocBlock[] = ' */';

		// Finally add newlines after each line
		$newDocBlock = array_map(function ($line) {
			return $line . ModelAnalyzer::$newLine;
		}, $newDocBlock);

		$this->modelData->classDocBlock = implode('', $newDocBlock);
	}

	protected function hasExistingDocBlock(): bool {
		if (null === $this->modelData->classDocBlock) {
			return false;
		}

		$originalDocBlock = explode(ModelAnalyzer::$newLine, $this->modelData->classDocBlock);
		if ((count($originalDocBlock) && $originalDocBlock[0] !== '') || count($originalDocBlock) > 1) {
			return true;
		}

		return false;
	}
}

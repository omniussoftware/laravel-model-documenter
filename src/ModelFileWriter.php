<?php


namespace Omniway\ModelDocumenter;


use Illuminate\Support\Str;

class ModelFileWriter {
	private ModelData $modelData;

	public function __construct(ModelData $modelData) {
		$this->modelData = $modelData;
	}

	public function writeFile($path): void {
		$classDeclarationString = $this->getClassDeclaration($this->modelData);
		$hasOriginalDocBlock = $this->hasExistingDocBlock();
		$oldDocBlock = $this->modelData->classDocBlock;
		$useStatements = collect();

		$this->buildClassDocBlock();

		$isInsideClass = false;
		$lines = [];
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
					$newBlock = explode($this->modelData->newline, $this->modelData->classDocBlock);

					foreach ($newBlock as $key => $classBlockLine) {
						// If its the last line we don't add a newline because otherwise there is a blank line between the
						// docblock and the class
						if ($key === count($newBlock) - 1) {
							$lines[] = $classBlockLine;
						} else {
							$lines[] = $classBlockLine . $this->modelData->newline;
						}
					}
				}
				$lines[] = $line;
				$isInsideClass = true;
			} else {
				$lines[] = $line;
			}

			$previousLine = $line;
		}

		$stringToBeWritten = implode('', $lines);

		// If the model already has a docblock, replace it with the new one
		if ($hasOriginalDocBlock) {
			$stringToBeWritten = str_replace($oldDocBlock, $this->modelData->classDocBlock, $stringToBeWritten);
		}

		// Handle use statements
		if (!empty($this->modelData->requiredImports)) {
			$originalUseString = $useStatements->join('');
			if (in_array('Carbon', $this->modelData->requiredImports)) {
				if (!$useStatements->contains(fn ($line) => Str::contains($line, ['\Carbon;', 'as Carbon;']))) {
					$useStatements[] = 'use Carbon\Carbon;' . $this->modelData->newline;
				}
			}

			if (in_array('Collection', $this->modelData->requiredImports)) {
				if (!$useStatements->contains(fn ($line) => Str::contains($line, ['\Collection;', 'as Collection;']))) {
					$useStatements[] = 'use Illuminate\Support\Collection;' . $this->modelData->newline;
				}
			}

			$stringToBeWritten = str_replace($originalUseString,
				$useStatements->sort(function ($a, $b) {
					// In order to mimic php-cs-fixer order, replace backslashes by spaces before sorting
					return str_replace('\\', ' ', $a) <=> str_replace('\\', ' ', $b);
				})->join(''),
				$stringToBeWritten);
		}

		// Don't write if contents are identical
		if ($stringToBeWritten !== implode($this->modelData->fileContents)) {
			$fileHandle = fopen($path, 'w');
			fwrite($fileHandle, $stringToBeWritten);
			fclose($fileHandle);
		}
	}

	/**
	 * Builds a new class level docblock. If there is a docblock already, this keeps the old one intact except any
	 * lines starting with "* @property"
	 */
	protected function buildClassDocBlock(): void {
		$modelData = $this->modelData;

		$linesToIgnore = [
			'/**',
			' * properties:',
			' * relations:',
			' * @property',
		];


		$properties = $modelData->properties;
		$relations = $modelData->relations;

		$originalDocBlock = explode($this->modelData->newline, $modelData->classDocBlock);

		$newDocBlock = [
			'/**',
			" * " . ucfirst($this->getClassDeclaration($modelData)),
		];

		$previousLine = null;


		// Add the parts of the old docblock that we want to keep
		foreach ($originalDocBlock as $docBlockLine) {
			if ($docBlockLine === '') {
				continue;
			}

			// If this isn't the end of the docblock or a @property line, we add it
			if ($docBlockLine !== ' */'
				&& !Str::contains(strtolower($docBlockLine), $linesToIgnore)
				// nukes old class declarations
				&& !preg_match('/^( \* )([aA]bstract [cC]lass|[cC]lass) \w+\s*$/', $docBlockLine)
			) {
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
			return $line . $this->modelData->newline;
		}, $newDocBlock);

		$this->modelData->classDocBlock = implode('', $newDocBlock);
	}

	protected function hasExistingDocBlock(): bool {
		if (null === $this->modelData->classDocBlock) {
			return false;
		}

		$originalDocBlock = explode($this->modelData->newline, $this->modelData->classDocBlock);
		if ((count($originalDocBlock) && $originalDocBlock[0] !== '') || count($originalDocBlock) > 1) {
			return true;
		}

		return false;
	}

	private function getClassDeclaration(ModelData $modelData): string {
		return ($modelData->reflectionClass->isAbstract() ? 'abstract ' : '') . "class {$modelData->name}";

	}
}

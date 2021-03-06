<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Modules\AfterClass;
use Enz0project\ModelDocumenter\Modules\BaseModule;
use Enz0project\ModelDocumenter\Modules\BeforeWrite;
use Enz0project\ModelDocumenter\Modules\InsideClass;
use Enz0project\ModelDocumenter\Modules\ModifiesClassDocBlock;
use Enz0project\ModelDocumenter\Modules\ModifiesLines;
use Enz0project\ModelDocumenter\Modules\UsesLines;
use Enz0project\ModelDocumenter\Modules\UsesModelData;
use Enz0project\ModelDocumenter\Modules\UsesStringToBeWritten;
use Enz0project\ModelDocumenter\Modules\WritesLines;
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

	private $modules = [
		ModifiesClassDocBlock::class => [],
		InsideClass::class => [],
		AfterClass::class => [],
		BeforeWrite::class => [],
	];

	/** @var ModelData */
	private $modelData;
	/** @var array */
	private $lines = [];
	/** @var string */
	private $stringToBeWritten;
	/** @var array */
	private $modulesRan = [
		ModifiesClassDocBlock::class => [],
		InsideClass::class => [],
		AfterClass::class => [],
		BeforeWrite::class => [],
	];


	public function __construct(ModelData $modelData) {
		$this->modelData = $modelData;
		$this->createModules();
	}

	public function replaceFileContents(): string {
		$classDeclarationString = ModelDocumenterHelper::getClassDeclaration($this->modelData);
		$hasOriginalDocBlock = $this->hasExistingDocBlock();
		$oldDocBlock = $this->modelData->getClassDocBlock();
		$useStatements = '';

		$this->buildClassDocBlock();

		$isInsideClass = false;

		$previousLine = null;

		foreach ($this->modelData->getFileContents() as $key => $line) {
			// Ugly hack to remove newlines between class docblock and class declaration
			if ($isInsideClass
					&& Str::contains($previousLine, ' */') && $key !== count($this->modelData->getFileContents()) - 1
					&& Str::startsWith($this->modelData->getFileContents()[$key + 1], $classDeclarationString)) {
				continue;
			}

			// Store use statements
			if (!$isInsideClass && Str::startsWith($line, 'use ')) {
				$useStatements .= $line;
			}

			if (!$isInsideClass && Str::startsWith($line, $classDeclarationString)) {
				// Add the class docblock before the class declaration!
				if (!$hasOriginalDocBlock) {
					$newBlock = explode(ModelAnalyzer::$newLine, $this->modelData->getClassDocBlock());

					foreach ($newBlock as $key => $classBlockLine) {
						// If its the last line we don't add a newline because otherwise there is a blank line between the
						// docblock and the class
						if ($key === count($newBlock) - 1) {
							$this->addLine($classBlockLine);
						} else {
							$this->addLine($classBlockLine . ModelAnalyzer::$newLine);
						}
					}
				}
				$this->addLine($line);
				$isInsideClass = true;
			} else {
				// Run "InsideClass" modules
				foreach ($this->modules[InsideClass::class] as $module) {
					$module = $this->setupModule($module);
					$this->runModule($module);
				}

				$this->addLine($line);
			}

			$previousLine = $line;
		}

		// Run "AfterClass" modules
		foreach ($this->modules[AfterClass::class] as $module) {
			$module = $this->setupModule($module);
			$this->runModule($module);
		}

		$this->stringToBeWritten = implode('', $this->lines);

		// If the model already has a docblock, replace it with the new one
		if ($hasOriginalDocBlock) {
			$this->stringToBeWritten = str_replace($oldDocBlock, $this->modelData->getClassDocBlock(), $this->stringToBeWritten);
		}

		// Run "BeforeWrite" modules
		foreach ($this->modules[BeforeWrite::class] as $module) {
			$module = $this->setupModule($module);
			$this->runModule($module);
		}

		if (config('modeldocumenter.importCarbon', false) && in_array('Carbon', $this->modelData->getRequiredImports())) {
			$this->stringToBeWritten = $this->importCarbon($this->stringToBeWritten, $useStatements);
		}

		return $this->stringToBeWritten;
	}

	/**
	 * @param string $line
	 */
	protected function addLine(string $line): void {
		$this->lines[] = $line;
	}

	/**
	 * Runs a module (if it's not already executed) and handles its result/response, then adds it to $modulesRan
	 *
	 * @param $module
	 * @throws \Exception
	 */
	protected function runModule(BaseModule $module): void {
		$moduleType = $module->moduleType();

		// Abort if we've already ran this module
		if (in_array($module, $this->modulesRan[$moduleType])) {
			return;
		}


		// Modify this objects data according to the module

		if ($module instanceof WritesLines) {
			$this->appendNewLines($module);
		}

		if ($module instanceof ModifiesClassDocBlock) {
			$this->modifyClassDocBlock($module);
		}

		if ($module instanceof ModifiesLines) {
			$this->modifyLines($module);
		}

		if ($module instanceof BeforeWrite) {
			$this->beforeWrite($module);
		}

		// Mark the module as ran so we don't run it again
		$this->modulesRan[$moduleType][] = $module;
	}

	/**
	 * This module replaces the processed string to be written to the file
	 *
	 * @param BeforeWrite $module
	 */
	protected function beforeWrite(BeforeWrite $module): void {
		$this->stringToBeWritten = $module->linesString();
	}

	/**
	 * Append to $this->lines from a WritesLines modules result
	 *
	 * @param WritesLines $module
	 */
	protected function appendNewLines(WritesLines $module): void {
		$moduleResult = $module->lines();

		if (count($moduleResult)) {
			foreach ($moduleResult as $result) {
				$this->lines[] = $result;
			}
		}
	}

	/**
	 * Modifies $this->modelData->classDocBlock with a modules result
	 *
	 * @param ModifiesClassDocBlock $module
	 */
	protected function modifyClassDocBlock(ModifiesClassDocBlock $module): void {
		$this->modelData->setClassDocBlock($module->classDocBlock());
	}

	/**
	 * Modifies $this->lines with the result of a ModifiesLines module
	 *
	 * @param ModifiesLines $module
	 */
	protected function modifyLines(ModifiesLines $module): void {
		$this->lines = $module->modifiedLines();
	}

	/**
	 * @param $module
	 * @return mixed
	 */
	protected function setupModule(BaseModule $module) {
		if ($module instanceof UsesModelData) {
			$module->setModelData($this->modelData);
		}

		if ($module instanceof UsesLines) {
			$module->setLines($this->lines);
		}

		if ($module instanceof UsesStringToBeWritten) {
			$module->setStringToBeWritten($this->stringToBeWritten);
		}

		return $module;
	}

	/**
	 * Builds a new class level docblock. If there is a docblock already, this keeps the old one intact except any
	 * lines starting with "* @property"
	 */
	protected function buildClassDocBlock(): void {
		$modelData = $this->modelData;

		$linesToIgnore = [
			'/**',
			'abstract class ' . strtolower($modelData->getName()),
			'interface ' . strtolower($modelData->getName()),
			'class ' . strtolower($modelData->getName()),
			' * properties:',
			' * relations:',
			' * @property',
		];


		$properties = $modelData->getProperties();
		$relations = $modelData->getRelations();

		$originalDocBlock = explode(ModelAnalyzer::$newLine, $modelData->getClassDocBlock());

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

		$this->modelData->setClassDocBlock(implode('', $newDocBlock));

		foreach ($this->modules[ModifiesClassDocBlock::class] as $module) {
			$module = $this->setupModule($module);
			$this->runModule($module);
		}
	}

	/**
	 * @return bool
	 */
	protected function hasExistingDocBlock(): bool {
		if (null === $this->modelData->getClassDocBlock()) {
			return false;
		}

		$originalDocBlock = explode(ModelAnalyzer::$newLine, $this->modelData->getClassDocBlock());

		if ((count($originalDocBlock) && $originalDocBlock[0] !== '') || count($originalDocBlock) > 1) {
			return true;
		}

		return false;
	}

	/**
	 * Creates module instances
	 */
	protected function createModules(): void {
		$configModules = config('modeldocumenter.modules');

		foreach ($configModules as $module) {
			$instance = new $module();
			$type = $instance->moduleType();

			$this->modules[$type][] = $instance;
		}
	}

	/**
	 * @param string $stringToBeWritten
	 * @return string
	 */
	protected function importCarbon(string $stringToBeWritten, string $useStatements): string {
		if (Str::contains($useStatements, 'Carbon\Carbon;')) {
			return $stringToBeWritten;
		}

		$replacedUseStatements = 'use Carbon\Carbon;' . ModelAnalyzer::$newLine . $useStatements;

		return str_replace($useStatements, $replacedUseStatements, $stringToBeWritten);
	}
}
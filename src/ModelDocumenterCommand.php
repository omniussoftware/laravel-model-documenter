<?php

namespace Enz0project\ModelDocumenter;

use Enz0project\ModelDocumenter\Exceptions\NotAClassException;
use Illuminate\Console\Command;

class ModelDocumenterCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'enz0project:model-documenter {model?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Adds docblocks and such to Laravel models. Usage:\n\n"
		.  "php artisan enz0project:model-documenter {model filename}\n\n"
		.   "If {model filename} (i.e. 'User'), the command will only run on the file User.php. If omitted, it will run on all models.\n\n"
		.   "Note: You can run on multiple specific models by comma separating them, i.e. 'php artisan enz0project:model-documenter User,Post,Comment";

	/** @var array */
	private $models;
	/** @var array */
	private $foundModels;

	/**
	 * @var ModelAnalyzer
	 */
	protected $modelAnalyzer;

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$this->modelAnalyzer = new ModelAnalyzer();
		$modelFolder = config('modeldocumenter.modelPath');
		$recursive = config('modeldocumenter.recursive');


		if ($this->hasArgument('model') && null !== $this->argument('model')) {
			$this->models = array_filter(explode(',', $this->argument('model')));
			$this->foundModels = [];
		}

		$files = $this->getModelFiles(realpath($modelFolder), $recursive);

		// Warn if any models weren't found
		if ($this->models) {
			$this->printMissingModelWarnings();
		}

		$bar = $this->output->createProgressBar(count($files));
		$bar->start();

		foreach ($files as $file) {
		    try {
                $modelData = $this->modelAnalyzer->analyze($file);
		    } catch (NotAClassException $e) {
				// This isn't a class, skip it
		    } catch (\Throwable $e) {
		        $this->line('');
		        $this->error($e->getMessage() . ' when analyzing file ' . $file);
		        $this->warn('Skipping file ' . $file);
		        continue;
            }

			$newFileContents = (new ModelLineWriter($modelData))->replaceFileContents();

			$fileHandle = fopen($file, 'w');

			fwrite($fileHandle, $newFileContents);

			fclose($fileHandle);

			$bar->advance();
		}

		$bar->finish();
	}

	/**
	 * @param string $path
	 * @param bool $recursive
	 * @return array
	 */
	protected function getModelFiles(string $path, bool $recursive = false): array {
		$files = $this->getFiles($path);
		$results = [];

		if ($recursive) {
			foreach ($files as $file) {
				$filePath = realpath($file);

				if (!is_dir($filePath)) {
					$results[] = $filePath;
				} else {
					$results = array_merge($results, $this->getFiles($filePath));
				}
			}
		} else {
			// Filter out any directories
			$results = array_filter($files, static fn (string $file) => !is_dir($file));
		}

		$filteredModels = $this->filterOutUnwantedModels($results);

		return $filteredModels;
	}

	/**
	 * @param array $files
	 * @return array
	 */
	protected function filterOutUnwantedModels(array $files): array {
		if (null === $this->models) {
			return $files;
		}

		return array_filter($files, function ($file) {
			$filePath = realpath($file);
			$filenameWithoutExtension = basename($filePath, '.php');

			// If we are in "specific model mode" we just skip all files that aren't the models we're looking for
			if (!in_array($filenameWithoutExtension, $this->models)) {
				return false;
			}

			$this->foundModels[] = $filenameWithoutExtension;

			return true;
		});
	}

	/**
	 * @param string $path
	 * @return array
	 */
	protected function getFiles(string $path): array {
		$files = scandir($path);

		$files = array_filter($files, function ($item) {
			return $item !== '.' && $item !== '..';
		});

		$files = array_map(function ($file) use ($path) {
			return "$path/$file";
		}, $files);

		return $files;
	}

	/**
	 * If in "specific models mode", prints warnings if any specified models were not found
	 */
	protected function printMissingModelWarnings(): void {
		$missingModels = array_diff($this->models, $this->foundModels);

		if (count($missingModels)) {
			$this->error('Models not found:');

			foreach ($missingModels as $missingModel) {
				$this->error("* $missingModel");
			}
		}
	}
}

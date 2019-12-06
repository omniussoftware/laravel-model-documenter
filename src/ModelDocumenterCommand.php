<?php

namespace enz0project\ModelDocumenter;

use Illuminate\Console\Command;

class ModelDocumenterCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'enz0project:model-documenter';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Adds docblocks and such to Laravel models';

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

		$files = $this->getModelFiles(realpath($modelFolder), $recursive);

		$bar = $this->output->createProgressBar(count($files));
		$bar->start();

		foreach ($files as $file) {
			$modelData = $this->modelAnalyzer->analyze($file);
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
		}

		return $recursive ? $results : $files;
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
}

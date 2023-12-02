<?php

namespace Enz0project\ModelDocumenter;

use Enz0project\ModelDocumenter\Exceptions\NotAClassException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ModelDocumenterCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'model:comment {models?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Adds docblocks with properties and relations to models";

	private Collection $models;
	private array $foundModels;

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$modelAnalyzer = new ModelAnalyzer();
		if ($this->hasArgument('models') && null !== $this->argument('models')) {
			$this->models = collect(explode(',', $this->argument('models'))->filter());
		}

		$files = collect((new Filesystem())->allFiles(app_path('Models')))
			->filter(fn ($splFile) => $splFile->getExtension() === 'php')
			->when($this->models, function ($collection) {
				return $collection->filter(function ($splFile) {
					return $this->models->contains($splFile->getFilenameWithoutExtension());
				});
			})
			->map->toString();

		$bar = $this->output->createProgressBar(count($files));
		$bar->start();

		foreach ($files as $file) {
		    try {
                $modelData = $modelAnalyzer->analyze($file);
		    } catch (NotAClassException $e) {
				// This isn't a class, skip it
			    $bar->advance();
			    continue;
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
}

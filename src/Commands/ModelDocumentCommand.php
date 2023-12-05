<?php

namespace Omniway\ModelDocumenter\Commands;

use Omniway\ModelDocumenter\Exceptions\NotAClassException;
use Omniway\ModelDocumenter\ModelData;
use Omniway\ModelDocumenter\ModelFileWriter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ModelDocumentCommand extends Command {
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'model:document {models?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Adds docblocks with properties and relations to models";

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle() {
		$files = collect((new Filesystem())->allFiles(app_path('Models')))
			->filter(fn ($splFile) => $splFile->getExtension() === 'php')
			->when($this->argument('models'), function ($collection) {
				$models = collect(explode(',', $this->argument('models')))->filter();
				foreach ($models as $model) {
					if (!$collection->contains(fn ($splFile) => $splFile->getFilenameWithoutExtension() === $model)) {
						$this->error("Could not find given model '$model'; aborting");
						die();
					}
				}

				return $collection->filter(function ($splFile) use ($models) {
					return $models->contains($splFile->getFilenameWithoutExtension());
				});
			})
			->map->getPathname();

		$bar = $this->output->createProgressBar(count($files));
		$bar->start();

		foreach ($files as $file) {
		    try {
                $modelData = new ModelData($file);
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

			(new ModelFileWriter($modelData))->writeFile($file);

			$bar->advance();
		}

		$bar->finish();
	}
}

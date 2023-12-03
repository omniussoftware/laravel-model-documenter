<?php

namespace Omniway\ModelDocumenter;

use Omniway\ModelDocumenter\Commands\ModelDocumentCommand;
use Illuminate\Support\ServiceProvider;

class ModelDocumenterProvider extends ServiceProvider {
	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot() {
		if ($this->app->runningInConsole()) {
			$this->commands([ModelDocumentCommand::class]);
		}
	}
}

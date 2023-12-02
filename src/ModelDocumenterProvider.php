<?php

namespace Enz0project\ModelDocumenter;

use Illuminate\Support\ServiceProvider;

class ModelDocumenterProvider extends ServiceProvider {
	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot() {
		$this->publishes([
			__DIR__ . '/config/modeldocumenter.php' => config_path('modeldocumenter.php')
		], 'config');

		if ($this->app->runningInConsole()) {
			$this->commands([ModelDocumenterCommand::class]);
		}
	}
}

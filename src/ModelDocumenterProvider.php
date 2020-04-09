<?php

namespace Enz0project\ModelDocumenter;

use Illuminate\Support\ServiceProvider;

class ModelDocumenterProvider extends ServiceProvider {
	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register() {
		if (!$this->app->bound(DBHelper::class)) {
			$this->app->bind(DBHelper::class, function () {
				return new MySQLDBHelper();
			});
		}

		if (!$this->app->bound(FileHelper::class)) {
			$this->app->bind(FileHelper::class, function () {
				return new DefaultFileHelper();
			});
		}
	}

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

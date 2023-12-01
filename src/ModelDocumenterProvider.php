<?php

namespace Enz0project\ModelDocumenter;

use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
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

		if (!$this->app->bound(ReflectionHelper::class)) {
			$this->app->bind(ReflectionHelper::class, function () {
				return new DefaultReflectionHelper();
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

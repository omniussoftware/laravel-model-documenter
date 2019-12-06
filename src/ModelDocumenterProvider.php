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
	}

	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot() {
		$this->publishes([__DIR__.'/config' => base_path('config')]);
	}
}

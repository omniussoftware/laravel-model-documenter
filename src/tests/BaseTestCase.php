<?php

namespace Enz0project\ModelDocumenter\Tests;

class BaseTestCase extends \Orchestra\Testbench\TestCase {
	protected const PACKAGE_ROOT = __DIR__ . '/../';
	protected const STUBS = __DIR__ . '/../teststubs/';

	protected function setUp(): void {
		parent::setUp();
	}

	protected function getPackageProviders($app) {
		return ['Enz0project\ModelDocumenter\ModelDocumenterProvider'];
	}

	protected function getEnvironmentSetUp($app) {
		parent::getEnvironmentSetUp($app);
		$app['config']->set('modeldocumenter.lineendings', 'crlf');
	}
}
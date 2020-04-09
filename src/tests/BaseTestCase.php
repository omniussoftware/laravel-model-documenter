<?php

namespace Enz0project\ModelDocumenter\Tests;

class BaseTestCase extends \Orchestra\Testbench\TestCase {
	protected function setUp(): void {
		parent::setUp();
	}

	protected function getPackageProviders($app) {
		return ['Enz0project\ModelDocumenter\ModelDocumenterProvider'];
	}
}
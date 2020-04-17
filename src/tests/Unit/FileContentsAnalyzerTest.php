<?php


namespace Enz0project\ModelDocumenter\tests\Unit;


use Enz0project\ModelDocumenter\DefaultFileHelper;
use Enz0project\ModelDocumenter\Interfaces\FileContentsAnalyzer;
use Enz0project\ModelDocumenter\Tests\BaseTestCase;

class FileContentsAnalyzerTest extends BaseTestCase {
	private FileContentsAnalyzer $analyzer;

	protected function setUp(): void {
		parent::setUp();

		$this->analyzer = app()->make(FileContentsAnalyzer::class);
	}

	/** @test */
	public function it_gets_proper_name_from_abstract_class() {
		$fileHelper = new DefaultFileHelper();
		$lines = $fileHelper->getLines(self::STUBS . 'AbstractBaseModel.txt');

		$name = $this->analyzer->getName($lines);

		$this->assertEquals('BaseModel', $name);
	}

	/** @test */
	public function it_gets_proper_name_from_interface() {
		$fileHelper = new DefaultFileHelper();
		$lines = $fileHelper->getLines(self::STUBS . 'InterfaceBaseModel.txt');

		$name = $this->analyzer->getName($lines);

		$this->assertEquals('BaseModelInterface', $name);
	}

	/** @test */
	public function it_gets_proper_name_from_class() {
		$fileHelper = new DefaultFileHelper();
		$lines = $fileHelper->getLines(self::STUBS . 'User.txt');

		$name = $this->analyzer->getName($lines);

		$this->assertEquals('User', $name);
	}
}
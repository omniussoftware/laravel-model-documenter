<?php


namespace Enz0project\ModelDocumenter\tests\Unit;


use Enz0project\ModelDocumenter\Interfaces\FileHelper;
use Enz0project\ModelDocumenter\Tests\BaseTestCase;

class FileHelperTest extends BaseTestCase {
	private FileHelper $fileHelper;

	protected function setUp(): void {
		parent::setUp();
		$this->fileHelper = app()->make(FileHelper::class);
	}

	/** @test */
	public function it_gets_all_lines_from_file() {
		// The lines from teststubs/Post.txt
		$lines = [
			"<?php\r\n",
			"\r\n",
			"namespace App;\r\n",
			"\r\n",
			"use Illuminate\Database\Eloquent\Relations\BelongsTo;\r\n",
			"\r\n",
			"class Post extends BaseModel {\r\n",
			"    public function user(): BelongsTo {\r\n",
			'        return $this->belongsTo(User::class);' . "\r\n",
			"    }\r\n",
			"}\r\n",
			false,
		];

		$fileHelperLines = $this->fileHelper->getLines(self::STUBS . 'Post.txt');

		$this->assertEquals($lines, $fileHelperLines);
	}
}
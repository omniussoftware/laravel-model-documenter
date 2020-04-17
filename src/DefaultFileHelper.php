<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\FileHelper;

class DefaultFileHelper implements FileHelper {
	/**
	 * { @inheritDoc }
	 */
	public function getLines(string $filePath): array {
		$file = fopen($filePath, 'r');

		$lines = [];

		while (!feof($file)) {
			$lines[] = fgets($file);
		}

		fclose($file);

		return $lines;
	}
}
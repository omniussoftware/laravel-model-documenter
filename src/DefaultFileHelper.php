<?php


namespace Enz0project\ModelDocumenter;


class DefaultFileHelper implements FileHelper {
	/**
	 * { @inheritDoc }
	 */
	private function getLines(string $filePath): array {
		$file = fopen($filePath, 'r');

		$lines = [];

		while (!feof($file)) {
			$lines[] = fgets($file);
		}

		fclose($file);

		return $lines;
	}
}
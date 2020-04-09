<?php


namespace Enz0project\ModelDocumenter;


interface FileHelper {
	/**
	 * Reads file content into an array
	 *
	 * @param string $filePath
	 * @return array
	 */
	public function getLines(string $filePath): array;
}
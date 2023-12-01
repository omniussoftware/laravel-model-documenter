<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NotAClassException;
use Illuminate\Support\Str;

class FileContentsAnalyzer {

	/**
	 * Returns name of class
	 */
	public static function getName(array $lines): string {
		foreach ($lines as $line) {
			if (Str::startsWith($line, 'interface')) {
				throw new NotAClassException('This is an interface');
			}

			if (Str::startsWith($line, 'abstract class') || Str::startsWith($line, 'class')) {
				$split = explode(' ', $line);

				return $split[array_search('class', $split, true) + 1];
			}
		}

		throw new \InvalidArgumentException('Could not extract name from lines!');
	}

	/**
	 * Returns namespace of class
	 */
	public static function getNamespace(array $lines): string {
		foreach ($lines as $line) {
			if (Str::startsWith($line, 'namespace ')) {
				return str_replace(['namespace ', ';', "\n", "\r"], '', $line);
			}
		}

		throw new \InvalidArgumentException('Could not extract namespace from lines!');
	}
}
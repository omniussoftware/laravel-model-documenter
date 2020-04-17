<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\FileContentsAnalyzer;
use Illuminate\Support\Str;

class DefaultFileContentsAnalyzer implements FileContentsAnalyzer {

	/**
	 * @inheritDoc
	 */
	public function getName(array $lines): string {
		foreach ($lines as $line) {
			$split = explode(' ', $line);

			if (Str::startsWith($line, 'interface ')) {
				$key = array_search('interface', $split, true);
			} elseif (Str::startsWith($line, 'abstract class ')) {
				// We add 1 here because abstract is one element and class is the next
				$key = array_search('abstract', $split, true) + 1;
			} elseif (Str::startsWith($line, 'class ')) {
				$key = array_search('class', $split, true);
			} else {
				// If line didn't start with the class declaration, skip it
				continue;
			}

			// $key + 1 should always be the class name
			return $split[$key + 1];
		}

		throw new \InvalidArgumentException('Could not extract name from lines!');
	}

	/**
	 * @inheritDoc
	 */
	public function getNamespace(array $lines): string {
		foreach ($lines as $line) {
			if (Str::startsWith($line, 'namespace ')) {
				$split = explode(' ', $line);
				$key = array_search('namespace', $split, true);

				if (count($split) < $key + 1) {
					throw new \InvalidArgumentException('Could not extract namespace from lines!');
				}

				return str_replace([';', "\n", "\r"], '', $split[$key + 1]);
			}
		}
	}
}
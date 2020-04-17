<?php


namespace Enz0project\ModelDocumenter\Interfaces;


interface FileContentsAnalyzer {
	/**
	 * Gets name of the interface/class
	 *
	 * @param array $lines
	 * @return string
	 */
	public function getName(array $lines): string;

	/**
	 * Gets namespace of Model
	 *
	 * @param array $lines
	 * @return string
	 */
	public function getNamespace(array $lines): string;
}
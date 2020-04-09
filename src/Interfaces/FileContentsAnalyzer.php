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
	 * Gets type (interface|abstract class|class) of Model
	 *
	 * @param array $lines
	 * @return int
	 */
	public function getClassType(array $lines): int;

	/**
	 * Gets namespace of Model
	 *
	 * @param array $lines
	 * @return string
	 */
	public function getNamespace(array $lines): string;
}
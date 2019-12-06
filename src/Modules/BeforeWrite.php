<?php


namespace enz0project\ModelDocumenter\Modules;


interface BeforeWrite {
	/**
	 * The replaced version of the final string that will be written to file
	 *
	 * @return string
	 */
	public function linesString(): string;
}
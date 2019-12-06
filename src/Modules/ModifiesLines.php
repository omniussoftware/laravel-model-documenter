<?php


namespace Enz0project\ModelDocumenter\Modules;


interface ModifiesLines {
	/**
	 * Returns the lines array after this module has modified it
	 *
	 * @return array
	 */
	public function modifiedLines(): array;
}
<?php


namespace Enz0project\ModelDocumenter\Modules;


interface WritesLines {
	public function lines(): iterable;
}
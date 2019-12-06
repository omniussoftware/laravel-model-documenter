<?php


namespace enz0project\ModelDocumenter\Modules;


interface WritesLines {
	public function lines(): iterable;
}
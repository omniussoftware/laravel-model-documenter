<?php


namespace Enz0project\ModelDocumenter\Modules;


class BaseModule {
	public function moduleType() {
		if ($this instanceof InsideClass) {
			return InsideClass::class;
		} elseif ($this instanceof AfterClass) {
			return AfterClass::class;
		} elseif ($this instanceof ModifiesClassDocBlock) {
			return ModifiesClassDocBlock::class;
		} elseif ($this instanceof BeforeWrite) {
			return BeforeWrite::class;
		}

		throw new \Exception('Module will never run');
	}
}
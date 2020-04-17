<?php


namespace Enz0project\ModelDocumenter\Models;


class DummyPostClass extends DummyBaseClass {
	public function getTable() {
		return 'posts';
	}

	public function getDates() {
		return [];
	}
}
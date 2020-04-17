<?php


namespace Enz0project\ModelDocumenter\Models;


class DummyUserModel {
	public function getTable() {
		return 'users';
	}

	public function getDates() {
		return [
			'created_at',
			'updated_at',
		];
	}
}
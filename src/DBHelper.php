<?php


namespace Enz0project\ModelDocumenter;


interface DBHelper {
	/**
	 * Fetches column data for a specific table
	 *
	 * @param string $table
	 * @return array
	 */
	public function fetchColumnData(string $table): array;
	public function dbTypeToPHP($column): string;
}
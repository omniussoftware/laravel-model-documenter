<?php


namespace enz0project\ModelDocumenter;


interface PropertyNameHelper {
	public function propertyNameFromColumn(string $column): string;
}
<?php


namespace Enz0project\ModelDocumenter;


interface PropertyNameHelper {
	public function propertyNameFromColumn(string $column): string;
}
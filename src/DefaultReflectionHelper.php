<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use ReflectionClass;

class DefaultReflectionHelper implements ReflectionHelper {
	public function getTableName(ReflectionClass $reflectionClass): string {
		$instance = $reflectionClass->newInstance();
		$tableName = $instance->getTable();

		if (null === $tableName) {
			throw new NoTableException('No table found in file!');
		}

		return $tableName;
	}

	public function getDates(ReflectionClass $reflectionClass): array {
		// TODO: Implement getDates() method.
	}
}
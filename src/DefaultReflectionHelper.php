<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use ReflectionClass;

class DefaultReflectionHelper implements ReflectionHelper {
	/**
	 * { @inheritDoc }
	 */
	public function getTableName(ReflectionClass $reflectionClass): string {
		$instance = $reflectionClass->newInstance();
		$tableName = $instance->getTable();

		if (null === $tableName) {
			throw new NoTableException('No table found in file!');
		}

		return $tableName;
	}

	/**
	 * { @inheritDoc }
	 */
	public function getDates(ReflectionClass $reflectionClass): array {
		$instance = $reflectionClass->newInstance();
		return $instance->getDates();
	}

	/**
	 * @inheritDoc
	 */
	public function getClassType(ReflectionClass $reflectionClass): int {
		if ($reflectionClass->isAbstract()) {
			return ModelData::TYPE_ABSTRACT_CLASS;
		} elseif ($reflectionClass->isInterface()) {
			return ModelData::TYPE_INTERFACE;
		}

		return ModelData::TYPE_CLASS;
	}
}
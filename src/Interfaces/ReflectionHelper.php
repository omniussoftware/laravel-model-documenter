<?php


namespace Enz0project\ModelDocumenter\Interfaces;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use ReflectionClass;
use ReflectionException;

interface ReflectionHelper {
	/**
	 * Gets the table name from the $table property on the model
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string
	 * @throws ReflectionException
	 * @throws NoTableException
	 */
	public function getTableName(ReflectionClass $reflectionClass): string;
	/**
	 * Gets the dates array from a model
	 *
	 * @param ReflectionClass $reflectionClass
	 * @return string
	 * @throws \ReflectionException
	 */
	public function getDates(ReflectionClass $reflectionClass): array;

	/**
	 * @param ReflectionClass $reflectionClass
	 * @return array associative array containing keys 'properties' and 'requiredImports'
	 * @throws \ReflectionException
	 */
	public function getProperties(ReflectionClass $reflectionClass): array;

	/**
	 * @param ReflectionClass $reflectionClass
	 * @param array $lines
	 * @return array associative array containing keys 'relations' and 'requiredImports'
	 */
	public function getRelations(ReflectionClass $reflectionClass, array $lines): array;
}
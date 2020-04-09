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
	public function getDates(ReflectionClass $reflectionClass): array;

}
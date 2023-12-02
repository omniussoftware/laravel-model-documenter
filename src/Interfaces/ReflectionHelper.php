<?php


namespace Enz0project\ModelDocumenter\Interfaces;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use ReflectionClass;
use ReflectionException;

interface ReflectionHelper {
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
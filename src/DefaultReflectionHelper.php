<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use Enz0project\ModelDocumenter\Interfaces\DBHelper;
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

	/**
	 * @inheritDoc
	 */
	public function getProperties(ReflectionClass $reflectionClass): array {
		$dbHelper = app()->make(DBHelper::class);
		$dbProperties = $dbHelper->fetchColumnData(($reflectionClass->newInstance())->getTable());
		$dates = $this->getDates($reflectionClass);
		$propsToReturn = [];
		$requiredImports = [];

		$carbonString = config('modeldocumenter.importCarbon', false) ? 'Carbon' : '\Carbon\Carbon';
		$nullableCarbonString = $carbonString . '|null';

		foreach ($dbProperties as $property) {
			$phpType = $dbHelper->dbTypeToPHP($property);
			$propName = $property->Field;
			// If the prop is an integer and the property is in the $dates array, it is a Carbon
			if ($phpType === 'int' && in_array($propName, $dates)) {
				$phpType = $carbonString;
			} elseif ($phpType === 'int|null' && in_array($propName, $dates)) {
				$phpType = $nullableCarbonString;
			}

			$propsToReturn[$propName] = $phpType;

			// If the model uses a Carbon we need to either import or fully qualify them with namespace
			if (($phpType === $carbonString || $phpType === $nullableCarbonString) && !in_array('Carbon', $requiredImports)) {
				$requiredImports[] = 'Carbon';
			}
		}

		return [
			'properties' => $propsToReturn,
			'requiredImports' => $requiredImports,
		];
	}
}
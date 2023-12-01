<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Exceptions\NoTableException;
use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Illuminate\Support\Str;
use ReflectionClass;

class DefaultReflectionHelper implements ReflectionHelper {
	/**
	 * @inheritDoc
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
	 * @inheritDoc
	 */
	public function getDates(ReflectionClass $reflectionClass): array {
		$instance = $reflectionClass->newInstance();
		return $instance->getDates();
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

	/**
	 * @inheritDoc
	 */
	public function getRelations(ReflectionClass $reflectionClass, array $lines): array {
		$methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
		$traitsInModel = $reflectionClass->getTraits();

		$requiredImports = [];
		$relations = [];

		foreach ($methods as $method) {
			$currMethod = $method;

			// If the class declaring this method isn't the model we're inside now, we skip over it; for now.
			$methodClassName = $method->getDeclaringClass()->getName();

			if ($reflectionClass->getName() !== $methodClassName) {
				continue;
			}

			$methodName = $method->getName();

			// If this method comes from a trait, we skip it for now; it will be handled later
			if ($this->methodIsInTrait($methodName, $traitsInModel)) {
				continue;
			}

			$startLine = $method->getStartLine();
			$endLine = $method->getEndLine();

			for ($i = $startLine; $i < $endLine; $i++) {
				$line = trim($lines[$i]);

				// TODO: Maybe add support for returns where the returned thing is on the line below the 'return' keyword
				if (Str::startsWith($line, 'return ')) {
					$relatedClassName = ModelDocumenterHelper::getRelatedClassName($line);
					if (null !== $relatedClassName) {
						$relations[$methodName] = $relatedClassName;
					}

					// If the model uses a Collection we need to either import or fully qualify them with namespace
					if (Str::startsWith($relatedClassName, 'Collection|') && !in_array('Collection', $requiredImports)) {
						$requiredImports[] = 'Collection';
					}
				}
			}
		}

		return [
			'relations' => $relations,
			'requiredImports' => $requiredImports,
		];
	}

	/**
	 * Checks if any of the models traits have a specific method
	 *
	 * @param $method
	 * @param array $traitsInModel
	 * @return bool
	 */
	protected function methodIsInTrait($method, array $traitsInModel): bool {
		foreach ($traitsInModel as $trait) {
			if ($trait->hasMethod($method)) {
				return true;
			}
		}

		return false;
	}
}
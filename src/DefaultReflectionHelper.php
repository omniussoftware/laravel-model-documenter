<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Illuminate\Support\Str;
use ReflectionClass;

class DefaultReflectionHelper implements ReflectionHelper {
	/**
	 * @inheritDoc
	 */
	public function getProperties(ReflectionClass $reflectionClass): array {
		$carbonString = config('modeldocumenter.importCarbon', false) ? 'Carbon' : '\Carbon\Carbon';
		$reflectedInstance = $reflectionClass->newInstance();
		$dbHelper = app()->make(DBHelper::class);

		$dates = $reflectedInstance->getDates();
		$propsToReturn = [];
		$requiredImports = [];

		$dbProperties = $dbHelper->fetchColumnData($reflectedInstance->getTable());
		foreach ($dbProperties as $property) {
			// If it's in $dates it's always a Carbon
			if (in_array($property->Field, $dates)) {
				$phpType = $carbonString;
				if ($property->Null === 'YES') {
					$phpType .= '|null';
				}
			} else {
				$phpType = $dbHelper->dbTypeToPHP($property);
			}

			// If the model uses a Carbon we need to either import or fully qualify them with namespace
			if (Str::startsWith($phpType, $carbonString) && !in_array('Carbon', $requiredImports)) {
				$requiredImports[] = 'Carbon';
			}
			$propsToReturn[$property->Field] = $phpType;
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
					$relatedClassName = ModelDocumenterHelper::getReturnedClassName($line);
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
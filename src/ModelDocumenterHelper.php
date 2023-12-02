<?php


namespace Enz0project\ModelDocumenter;


use Illuminate\Support\Str;

class ModelDocumenterHelper {

	/**
	 * Gets the class declaration, i.e. "abstract class BaseModel" or "class User"
	 *
	 * @param ModelData $modelData
	 * @return string
	 */
	public static function getClassDeclaration(ModelData $modelData): string {
		if ($modelData->getReflectionClass()->isAbstract()) {
			$type = 'abstract class ';
		} else {
			$type = 'class ';
		}

		return $type . $modelData->getName();
	}

	/**
	 * Gets the class name of the related class from a 'return $this->hasOne(...)' line
	 *
	 * @param string $line
	 * @return string|null
	 */
	public static function getReturnedClassName(string $line): ?string {
		try {
			// If this method does not return a relation, we're not interested in it
			if (!Str::contains($line, ModelLineWriter::$allRelations)) {
				return null;
			}

			// Finds first (, ignores first '", then grabs word characters (and backslashes). Then removes namespace
			preg_match("/^.*?\(['\"]?([\w\\\]+)/", $line, $matches);
			$className = explode('\\', $matches[1]);
			$className = end($className);

			// If its a relation that will return a Collection we need to specify that, i.e. 'Collection|Student[]'
			if (Str::contains($line, ModelLineWriter::$oneOrManyToManyRelations)) {
				$className = 'Collection|' . $className . '[]';
			}
		} catch (\Exception $e) {
			return null;
		}

		return $className ?: null;
	}
}
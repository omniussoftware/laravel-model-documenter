<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\ModelLineWriter;
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
	public static function getRelatedClassName(string $line): ?string {
		try {
			// If this method does not return a relation, we're not interested in it
			if (!Str::contains($line, ModelLineWriter::$allRelations)) {
				return null;
			}

			$className = self::extractClassNameFromString($line);
			// If its a relation that will return a Collection we need to specify that, i.e. 'Collection|Student[]'
			if (Str::contains($line, ModelLineWriter::$oneOrManyToManyRelations)) {
				$className = 'Collection|' . $className . '[]';
			}

		} catch (\Exception $e) {
			return null;
		}

		return $className !== '' ? $className : null;
	}

	/**
	 * Extracts the class name from the string
	 *
	 * @param string $line
	 * @return string
	 */
	protected static function extractClassNameFromString(string $line): string {
		$className = '';

		$split = explode('(', $line);
		$returnString = trim($split[1], '();');

		if (Str::contains($returnString, '::class')) {
			// In case the relation looks like this: 'return $this->hasOne(Student::class)'
			$className = explode('::', $split[1])[0];
			$className = trim($className, '(\'');
		} else {
			// This block handles the relation being defined by a string, i.e. 'return $this->hasOne('App\Models\Student')'
			$fullClassName = explode(',', $returnString)[0];
			$fullClassName = trim($fullClassName, '\'');
			// Without this we'll get weird stuff like <classname>')->where(...)
			$fullClassName = explode('\'', $fullClassName)[0];

			// Split the full name and get only the model name, i.e. App\Models\Student => Student
			$splittedFullName = explode('\\', $fullClassName);
			$className = $splittedFullName[count($splittedFullName) - 1];
		}

		return $className;
	}
}
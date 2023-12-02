<?php


namespace Enz0project\ModelDocumenter;


class ModelData {
	public string $name;
	public array $fileContents;
	public string $classDocBlock;
	public array $properties;
	public array $relations;
	public array $requiredImports;
	public \ReflectionClass $reflectionClass;

	public function __construct(
		string $name,
		array $fileContents,
		?string $classDocBlock,
		?array $properties,
		?array $relations,
		?array $requiredImports,
		\ReflectionClass $reflectionClass
	) {
		$this->name = $name;
		$this->fileContents = $fileContents;
		$this->classDocBlock = $classDocBlock ?? '';
		$this->properties = $properties ?? [];
		$this->relations = $relations ?? [];
		$this->requiredImports = $requiredImports ?? [];
		$this->reflectionClass = $reflectionClass;
	}
}
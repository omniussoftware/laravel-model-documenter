<?php


namespace Enz0project\ModelDocumenter;


class ModelData {
	/** @var string */
	private $name;
	/** @var int */
	private $type;
	/** @var array */
	private $fileContents;
	/** @var string */
	private $classDocBlock;
	/** @var array */
	private $properties;
	/** @var array|null */
	private $relations;
	/** @var array */
	private $requiredImports;
	/** @var \ReflectionClass */
	private $reflectionClass;

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return int
	 */
	public function getType(): int {
		return $this->type;
	}

	/**
	 * @return array
	 */
	public function getFileContents(): array {
		return $this->fileContents;
	}

	/**
	 * @return string
	 */
	public function getClassDocBlock(): string {
		return $this->classDocBlock;
	}

	/**
	 * @return array
	 */
	public function getProperties(): array {
		return $this->properties;
	}

	/**
	 * @return array|null
	 */
	public function getRelations(): ?array {
		return $this->relations;
	}

	/**
	 * @return array
	 */
	public function getRequiredImports(): array {
		return $this->requiredImports;
	}

	/**
	 * @return \ReflectionClass
	 */
	public function getReflectionClass(): \ReflectionClass {
		return $this->reflectionClass;
	}

	/**
	 * ModelData constructor.
	 * @param string $name
	 * @param int $type
	 * @param array $fileContents
	 * @param string $classDocBlock
	 * @param array $properties
	 * @param array|null $relations
	 * @param array $requiredImports
	 * @param \ReflectionClass $reflectionClass
	 */
	public function __construct(
		string $name,
		int $type,
		array $fileContents,
		string $classDocBlock,
		array $properties,
		?array $relations,
		array $requiredImports,
		\ReflectionClass $reflectionClass
	) {
		$this->name = $name;
		$this->type = $type;
		$this->fileContents = $fileContents;
		$this->classDocBlock = $classDocBlock;
		$this->properties = $properties;
		$this->relations = $relations;
		$this->requiredImports = $requiredImports;
		$this->reflectionClass = $reflectionClass;
	}


}
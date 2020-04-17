<?php


namespace Enz0project\ModelDocumenter\tests\Unit;


use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Enz0project\ModelDocumenter\ModelData;
use Enz0project\ModelDocumenter\Models\DummyBaseClass;
use Enz0project\ModelDocumenter\Models\DummyInterface;
use Enz0project\ModelDocumenter\Models\DummyUserModel;
use Enz0project\ModelDocumenter\Tests\BaseTestCase;

class ReflectionHelperTest extends BaseTestCase {
	private ReflectionHelper $reflectionHelper;

	protected function setUp(): void {
		parent::setUp();

		$this->reflectionHelper = app()->make(ReflectionHelper::class);
	}

	/** @test */
	public function it_gets_table_name() {
		$tableName = $this->reflectionHelper->getTableName(new \ReflectionClass(DummyUserModel::class));

		$this->assertEquals($tableName, 'users');
	}

	/** @test */
	public function it_gets_dates() {
		$expectedDates = [
			'created_at',
			'updated_at',
		];

		$dates = $this->reflectionHelper->getDates(new \ReflectionClass(DummyUserModel::class));

		$this->assertEquals($expectedDates, $dates);
	}

	/** @test */
	public function it_gets_abstract_type_correct() {
		$expectedType = ModelData::TYPE_ABSTRACT_CLASS;

		$actualType = $this->reflectionHelper->getClassType(new \ReflectionClass(DummyBaseClass::class));

		$this->assertEquals($expectedType, $actualType);
	}

	/** @test */
	public function it_gets_interface_type_correct() {
		$expectedType = ModelData::TYPE_INTERFACE;

		$actualType = $this->reflectionHelper->getClassType(new \ReflectionClass(DummyInterface::class));

		$this->assertEquals($expectedType, $actualType);
	}

	/** @test */
	public function it_gets_class_type_correct() {
		$expectedType = ModelData::TYPE_CLASS;

		$actualType = $this->reflectionHelper->getClassType(new \ReflectionClass(DummyUserModel::class));

		$this->assertEquals($expectedType, $actualType);
	}
}
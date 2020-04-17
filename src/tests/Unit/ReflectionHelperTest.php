<?php


namespace Enz0project\ModelDocumenter\tests\Unit;


use Enz0project\ModelDocumenter\Interfaces\ReflectionHelper;
use Enz0project\ModelDocumenter\ModelData;
use Enz0project\ModelDocumenter\Models\DummyBaseClass;
use Enz0project\ModelDocumenter\Models\DummyInterface;
use Enz0project\ModelDocumenter\Models\DummyPostClass;
use Enz0project\ModelDocumenter\Models\DummyUserModel;
use Enz0project\ModelDocumenter\Tests\BaseTestCase;
use Illuminate\Support\Facades\DB;

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

	/** @test */
	public function it_gets_properties_from_db() {
		// Simulate that we have a Post model with three columns: id, title and created_at
		$mysqlColumnDataForPost = [
			(object) [
				'Field' => 'id',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'PRI',
				'Default' => null,
				'Extra' => 'auto_increment',
			],
			(object) [
				'Field' => 'title',
				'Type' => 'varchar(255)',
				'Null' => 'NO',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
			(object) [
				'Field' => 'created_at',
				'Type' => 'timestamp',
				'Null' => 'YES',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
		];

		// Mock db facade
		DB::shouldReceive('select')
			->with('DESCRIBE `posts`')
			->andReturn($mysqlColumnDataForPost);

		$data = $this->reflectionHelper->getProperties(new \ReflectionClass(DummyPostClass::class));

		$props = $data['properties'];
		$propKeys = array_keys($props);

		$this->assertCount(3, $props);
		$this->assertEquals($propKeys[0], 'id');
		$this->assertEquals($propKeys[1], 'title');
		$this->assertEquals($propKeys[2], 'created_at');
	}

	/** @test */
	public function it_converts_int_timestamp_to_carbon() {
		// Simulate that we have a Post model with three columns: id, title and created_at
		$mysqlColumnDataForPost = [
			(object) [
				'Field' => 'id',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'PRI',
				'Default' => null,
				'Extra' => 'auto_increment',
			],
			(object) [
				'Field' => 'title',
				'Type' => 'varchar(255)',
				'Null' => 'NO',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
			(object) [
				'Field' => 'created_at',
				'Type' => 'timestamp',
				'Null' => 'NO',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
		];

		// Mock db facade
		DB::shouldReceive('select')
			->with('DESCRIBE `posts`')
			->andReturn($mysqlColumnDataForPost);

		$data = $this->reflectionHelper->getProperties(new \ReflectionClass(DummyPostClass::class));

		$props = $data['properties'];
		$createdAt = $props['created_at'];

		$this->assertEquals('\Carbon\Carbon', $createdAt);
	}

	/** @test */
	public function it_converts_nullable_int_timestamp_to_nullable_carbon() {
		// Simulate that we have a Post model with three columns: id, title and created_at
		$mysqlColumnDataForPost = [
			(object) [
				'Field' => 'id',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'PRI',
				'Default' => null,
				'Extra' => 'auto_increment',
			],
			(object) [
				'Field' => 'title',
				'Type' => 'varchar(255)',
				'Null' => 'NO',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
			(object) [
				'Field' => 'created_at',
				'Type' => 'timestamp',
				'Null' => 'YES',
				'Key' => '',
				'Default' => null,
				'Extra' => '',
			],
		];

		// Mock db facade
		DB::shouldReceive('select')
			->with('DESCRIBE `posts`')
			->andReturn($mysqlColumnDataForPost);

		$data = $this->reflectionHelper->getProperties(new \ReflectionClass(DummyPostClass::class));

		$props = $data['properties'];
		$createdAt = $props['created_at'];

		$this->assertEquals('\Carbon\Carbon|null', $createdAt);
	}
}
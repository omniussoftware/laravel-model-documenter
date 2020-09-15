<?php


namespace Enz0project\ModelDocumenter;


use Enz0project\ModelDocumenter\Interfaces\DBHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MySQLDBHelper implements DBHelper {
	private string $carbonString;

	public function __construct() {
		$this->carbonString = config('modeldocumenter.importCarbon', false) ? 'Carbon' : '\Carbon\Carbon';
	}

	/**
	 * { @inheritDoc }
	 */
	public function fetchColumnData(string $table): array {
		return DB::select("DESCRIBE `$table`");
	}

	/**
	 * { @inheritDoc }
	 */
	public function dbTypeToPHP($column): string {
		$mysqlType = trim($column->Type);
		$nullable = $column->Null === 'YES';
		$phpType = '';

		if (Str::contains($mysqlType, 'int')) {
			$phpType = 'int';
		} elseif (Str::startsWith($mysqlType, 'varchar')
			|| Str::contains($mysqlType, 'text')
			|| Str::startsWith($mysqlType, 'char')
			|| Str::startsWith($mysqlType, 'json')
			|| $mysqlType === 'time'
			|| Str::startsWith($mysqlType, 'enum')) {
			$phpType = 'string';
		} elseif (Str::contains($mysqlType, 'timestamp') || Str::contains($mysqlType, 'datetime')) {
			$phpType = $this->carbonString;
		} elseif (Str::contains($mysqlType, 'decimal')) {
			$phpType = 'float';
		}

		if (strlen($phpType) === 0) {
			throw new \InvalidArgumentException("Could not parse type from $mysqlType");
		}

		if ($nullable) {
			$phpType .= '|null';
		}

		return $phpType;
	}
}
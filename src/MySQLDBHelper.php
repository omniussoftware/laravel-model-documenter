<?php


namespace enz0project\ModelDocumenter;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MySQLDBHelper implements DBHelper {
	/**
	 * { @inheritDoc }
	 */
	public function fetchColumnData(string $table): array {
		return DB::select("DESCRIBE $table");
	}

	/**
	 * { @inheritDoc }
	 */
	public function dbTypeToPHP($column): string {
		$mysqlType = $column->Type;
		$nullable = $column->Null === 'YES';
		$phpType = '';

		if (Str::contains($mysqlType, 'int')) {
			$phpType = 'int';
		} elseif (Str::contains($mysqlType, 'varchar')
				|| Str::contains($mysqlType, 'text')
				|| Str::contains($mysqlType, 'char')
				|| Str::contains($mysqlType, 'enum')) {
			$phpType = 'string';
		} elseif (Str::contains($mysqlType, 'timestamp') || Str::contains($mysqlType, 'datetime')) {
			$phpType = 'Carbon';
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
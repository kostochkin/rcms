<?php

namespace rCMS\Compiler\MAst\Storage;

use \rCMS\Compiler\MAst\Base;
use \rCMS\Compiler\MAst\Base\MAst;


class SQLite implements Storage {
	private $table;

	public function __construct(string $collection_name) {
		$this->table = $collection_name;
	}

	public function create_table(array $fields, MAst $db_var, string $id_name) : array {
		$cols_defs = join(", ", array_merge(["{$id_name} INTEGER PRIMARY KEY"],
			array_map([$this, "field_to_str"], $fields)));
		$query = new Base\MString("CREATE TABLE IF NOT EXISTS `{$this->table}` ({$cols_defs})");
		$prepare = new Base\MApplication(new Base\MObjectAccessor($db_var, new Base\MId(Strings::PDOPREPARE)), [$query]);
		$execute = new Base\MApplication(new Base\MObjectAccessor($prepare, new Base\MId(Strings::PDOEXECUTE)), []);
		return [$execute];
	}

	private function field_to_str($field) {
		switch ($field->type) {
			case "string":
				return "{$field->name} TEXT";
		}
	}

	public function update(array $fields, MAst $db_var, string $id_name, array $properties) : array {
		$flds = join(", ", array_map(function ($x) { return "{$x} = ?"; }, $fields));
		$query = new Base\MString("UPDATE `{$this->table}` SET {$flds} WHERE {$id_name} = ?");
		$prepare = new Base\MApplication(new Base\MObjectAccessor($db_var, new Base\MId(Strings::PDOPREPARE)), [$query]);
		$execute = new Base\MApplication(new Base\MObjectAccessor($prepare, new Base\MId(Strings::PDOEXECUTE)), [new Base\MArray($properties)]);
		return [$execute];
	}

	public function add_record(array $dbcolumns, array $props, MAst $db, MAst $id) : array {
		$columns = join(", ", $dbcolumns);
		$values = join(", ", array_map(function ($x) { return "?"; }, $dbcolumns));
		$query = new Base\MString("INSERT INTO `{$this->table}` ({$columns}) VALUES ({$values})");
		$query_var = new Base\MVar(new Base\MUHygienicId());
		$prepare = new Base\MAssign($query_var, new Base\MApplication(new Base\MObjectAccessor($db, new Base\MId(Strings::PDOPREPARE)),[$query]));
		$execute = new Base\MApplication(new Base\MObjectAccessor($query_var, new Base\MId(Strings::PDOEXECUTE)), [new Base\MArray($props)]);
		$set_new_id = new Base\MAssign($id, 
			new Base\MTypecast("int",
				new Base\MApplication(new Base\MObjectAccessor($db,
					new Base\MId(Strings::PDOLASTINSERTID)), [])));
		return [$prepare, $execute, $set_new_id];
	}

	public function find(array $fields, MAst $id, MAst $db, MAst $row_var, string $by_field = null) : array {
		$jf = join(", ", $fields);
	  $where = "";
		if (!is_null($by_field)) {
			$where = " WHERE {$by_field} = ?";
		}
		$query = new Base\MString("SELECT {$jf} FROM `{$this->table}`{$where}");
		$temp_var = new Base\MVar(new Base\MUHygienicId());
		$prepare = new Base\MAssign($temp_var,
			new Base\MApplication(new Base\MObjectAccessor($db, new Base\MId(Strings::PDOPREPARE)),[$query]));
		$execute = new Base\Mapplication( new Base\MObjectAccessor($temp_var, new Base\MId(Strings::PDOEXECUTE)), [new Base\MArray([$id])]);
		$style = new Base\MStaticAccessor(new Base\MId(Strings::PDO), new Base\MId("FETCH_ASSOC"));
		$row_assign = new Base\MAssign($row_var,
		 	new Base\MApplication(new Base\MObjectAccessor($temp_var, new Base\MId(Strings::PDOFETCH)), [$style]));
		return [$prepare, $execute, $row_assign];
	}

}

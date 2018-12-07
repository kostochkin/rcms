<?php

namespace rCMS\Compiler\MAst\Storage;

use \rCMS\Compiler\MAst\Base\MAst;

interface Storage {
	public function __construct(string $collection_name);
	public function create_table(array $fields, MAst $db_var, string $id_name) : array;
	public function update(array $fields, MAst $db_var, string $id_name, array $properties) : array;
	public function add_record(array $dbcolumns, array $props, MAst $db, MAst $id) : array;
	public function find(array $fields, MAst $id, MAst $db, MAst $row_var, string $by_field) : array;
}

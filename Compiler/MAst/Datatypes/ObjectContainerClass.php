<?php

namespace rCMS\Compiler\MAst\Datatypes;

use \rCMS\Compiler\MAst\Base;

class ObjectContainerClass extends Base\MClass {
	public function __construct($def) {
		parent::__construct(new Base\MId($def->container_name));
		$this->add_var(new Base\Mprivate(new Base\MVar(new Base\MId(Strings::VARDB))));
		$this->add_function(new Base\Mpublic($this->constructor($def)));
		$this->add_function(new Base\Mpublic($this->new_from_representation($def)));
		$this->add_function(new Base\Mpublic($this->find_by_id($def)));
	}

	private function constructor($def) {
		$this_db = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$assign = new Base\MAssign($this_db, new Base\MVar(new Base\MId(Strings::VARDB)));
		$create_table = $def->db->create_table($def->properties, $this_db, Strings::VAROBJID);
		$body = array_merge([$assign], $create_table);
		return new Base\MFunction(new Base\MId("__construct"), [new Base\MVar(new Base\MId(Strings::VARDB))], new Base\MBody($body));
	}

	private function find_by_id($def) {
		$fn_name = new Base\MId("find_by_id");
		$this_db = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$repr = new Base\MVar(new Base\MId(Strings::REPRESENTATION));
		$columns = array_map(function ($x) { return $x->name; }, $def->properties);
		$rowvar = new Base\MVar(new Base\MHygienicId("row"));
		$find = $def->db->find($columns, new Base\MVar(new Base\MId(Strings::VAROBJID)), $this_db, $rowvar, Strings::VAROBJID);
		$api_obj_args = array_map(function ($x) use ($rowvar) {
		 	return new Base\MArrayAccessor($rowvar, new Base\MString($x->name)); }, $def->properties);
		$api_obj = new Base\MNew(new Base\MId($def->name), $api_obj_args);
		$return = new Base\MReturn(new Base\MNew(new Base\MId($def->internal_name), [$this_db, $api_obj, new Base\MVar(new Base\MId(Strings::VAROBJID))]));
		$body = array_merge($find, [$return]); 
		return new Base\MFunction($fn_name, [new Base\MVar(new Base\MId(Strings::VAROBJID))], new Base\MBody($body));

	}

	private function new_from_representation($def) {
		$fn_name = new Base\MId("new_from_representation");
		$fn_var = new Base\MVar(new Base\MId("representation"));
		$typed_fn_var = new Base\MVarType($def->name, $fn_var);
		$db = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$null = new Base\MNull();
		$return = new Base\MReturn(new Base\MNew(new Base\MId($def->internal_name), [$db, $fn_var, $null]));
		return new Base\MFunction($fn_name, [$typed_fn_var], new Base\MBody([$return]));
	}

}


<?php

namespace rCMS\Compiler\MAst\Datatypes;

use \rCMS\Compiler\MAst\Base;


class InternalObjectClass extends Base\MClass {
	public function __construct($def) {
		parent::__construct(new Base\MId($def->internal_name));
		$this->add_function(new Base\Mpublic($this->constructor($def)));
		$this->add_function(new Base\Mpublic($this->representation($def)));
		$this->add_function(new Base\Mpublic($this->id_getter($def)));
		$this->add_function(new Base\Mpublic($this->update_all($def)));
		foreach ($def->properties as $p) {
			$this->add_var(new Base\Mprivate(new Base\MVar(new Base\MId($p->decorated_name))));
			$this->add_function(new Base\Mpublic($this->setter($p, $def->db)));
		}
		$this->add_function(new Base\Mprivate($this->store_to_db($def)));
		$this->add_function(new Base\Mprivate($this->update_in_db($def)));
		$this->add_function(new Base\Mprivate($this->set_all($def)));
		$this->add_var(new Base\Mprivate(new Base\MVar(new Base\MId(Strings::VARDB))));
		$this->add_var(new Base\Mprivate(new Base\MVar(new Base\MId(Strings::VAROBJID))));
	}

	private function representation($def) {
		$args = [];
		foreach ($def->properties as $p)
			$args[] = new Base\MThisAccessor(new Base\MId($p->decorated_name));
		$return = new Base\MReturn(new Base\MNew(new Base\MId($def->name), $args));
		return new Base\MFunction(new Base\MId(Strings::REPRESENTATION), [], new Base\MBody([$return]));
	}

	private function update_all($def) {
		$fn_name = new Base\MId(Strings::UPDATEALL);
		$fn_arg = new Base\MVar(new Base\MId(Strings::REPRESENTATION));
		$set_all = new Base\MApplication(new Base\MThisAccessor(new Base\MId(Strings::SETALL)), [$fn_arg]);
		$update_db = new Base\MApplication(new Base\MThisAccessor(new Base\MId(Strings::UPDATEINDB)), []);
		$return = new Base\MReturn(new Base\MThis());
		$body = [$set_all, $update_db, $return];
		return new Base\MFunction($fn_name, [new Base\MVarType($def->name, $fn_arg)], new Base\MBody($body));
	}
		
	private function setter($pdef, $db) {
		$new_value = new Base\MVar(new Base\MId("new_" . $pdef->name));
		$objvar = new Base\MId($pdef->decorated_name);
		$db_var = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$set_var = new Base\MAssign(new Base\MThisAccessor($objvar), $new_value);
		$execute_query = $db->update([$pdef->name],
			$db_var, Strings::VAROBJID, [new Base\MThisAccessor($objvar), new Base\MThisAccessor(new Base\MId(Strings::VAROBJID))]);
		$return = new Base\MReturn(new Base\MThis());
		$body = new Base\MBody(array_merge([$set_var], $execute_query, [$return]));
		return new Base\Mfunction(new Base\MId("update_" . $pdef->name), [$new_value], $body);
	}

	private function set_all($def) {
		$fn_name = new Base\MId(Strings::SETALL);
		$fn_var = new Base\MVar(new Base\MId(Strings::REPRESENTATION));
		$body = array_map(function ($x) use ($fn_var) {
			$var_name = new Base\MThisAccessor(new Base\MId($x->decorated_name));
			$repr_name = new Base\MObjectAccessor($fn_var, new Base\MId($x->name));
			return new Base\MAssign($var_name, $repr_name);}, $def->properties);
		return new Base\MFunction($fn_name, [new Base\MVarType($def->name, $fn_var)], new Base\MBody($body));
	}

	private function update_in_db($def) {
		$fn_name = new Base\MId("update_in_db");
		$db_var = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$this_id = new Base\MThisAccessor(new Base\MId(Strings::VAROBJID));
		$query_var = new Base\MVar(new Base\MId(Strings::QUERY));
		$fields = array_map(function ($x) { return $x->name; }, $def->properties);
		$props = array_map(function ($x) { return new Base\MThisAccessor(new Base\MId($x->decorated_name)); }, $def->properties);
		$update = $def->db->update($fields, $db_var, Strings::VAROBJID, array_merge($props, [$this_id]));
		return new Base\Mfunction($fn_name, [], new Base\MBody($update));
	}

	private function store_to_db($def) {
		$fn_name = new Base\MId(Strings::STORETODB);
		$this_id = new Base\MThisAccessor(new Base\MId(Strings::VAROBJID));
		$this_db = new Base\MThisAccessor(new Base\MId(Strings::VARDB));
		$query_var = new Base\MVar(new Base\MId(Strings::QUERY));
		$dbcolumns = array_map(function ($x) { return $x->name; }, $def->properties);
		$props = array_map(function ($x) { return new Base\MThisAccessor(new Base\MId($x->decorated_name)); }, $def->properties);
		$store_new_record = $def->db->add_record($dbcolumns, $props, $this_db, $this_id);
		$body = new Base\MBody($store_new_record);
		return new Base\Mfunction($fn_name, [], $body);
	}

	private function id_getter() {
		$acc = new Base\MReturn(new Base\MThisAccessor(new Base\MId(Strings::VAROBJID)));
		return new Base\MFunction(new Base\MId("get_id"), [], new Base\MBody([$acc])); 
	}

	private function constructor($def) {
		$representation = new Base\MVar(new Base\MId(Strings::REPRESENTATION));
		$default_id = new Base\MAssign(new Base\MVar(new Base\MId(Strings::VAROBJID)), new Base\MNull());
		$args = [new Base\MVar(new Base\MId(Strings::VARDB)), new Base\MVarType($def->name, $representation), $default_id];
		$set_all = new Base\MApplication(new Base\MThisAccessor(new Base\MId(Strings::SETALL)), [$representation]);
		$set_db = new Base\MAssign(new Base\MThisAccessor(new Base\MId(Strings::VARDB)), new Base\MVar(new Base\MId(Strings::VARDB)));
		$set_id = new Base\MAssign(new Base\MThisAccessor(new Base\MId(Strings::VAROBJID)), new Base\MVar(new Base\MId(Strings::VAROBJID)));
		$then = new Base\MBody([new Base\MApplication(new Base\MThisAccessor(new Base\MId(Strings::STORETODB)), [])]);
		$if = new Base\MIf(new Base\MIsNullP(new Base\MVar(new Base\MId(Strings::VAROBJID))), $then);
		$body = new Base\MBody([$set_db, $set_id, $set_all, $if]);
		return new Base\MFunction(new Base\MId(Strings::CONSTRUCTOR), $args, $body);
	}

	private function mk_assigns($def) {
		$args = [new Base\MVar(new Base\MId(Strings::VARDB)), new Base\MVar(new Base\MId(Strings::VAROBJID))];
		$assigns = [new Base\MAssign(new Base\MThisAccessor(new Base\MId(Strings::VARDB)), new Base\MVar(new Base\MId(Strings::VARDB))),
		            new Base\MAssign(new Base\MThisAccessor(new Base\MId(Strings::VAROBJID)), new Base\MVar(new Base\MId(Strings::VAROBJID)))];
		foreach ($def->properties as $pdef) {
			$acc = new Base\MThisAccessor(new Base\MId($pdef->decorated_name));
			$var = new Base\MVar(new Base\MId($pdef->name));
			$args[] = $var;
			$assigns[] = new Base\MAssign($acc, $var);
		}
		return [$args, $assigns];
	}
}


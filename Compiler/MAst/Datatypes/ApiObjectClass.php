<?php

namespace rCMS\Compiler\MAst\Datatypes;

use \rCMS\Compiler\MAst\Base;

class ApiObjectClass extends Base\MClass {
	public function __construct($def) {
		parent::__construct(new Base\MId($def->name));
		$c__construct = $this->constructor($def);
		$this->add_function(new Base\Mpublic($c__construct));
		foreach ($def->properties as $p)
		 	$this->add_var(new Base\Mpublic(new Base\MVar(new Base\MId($p->name))));
	}

	public function constructor($def) {
		$res = $this->mk_props($def);
		$args = array_map(function ($x) { return new Base\MVarType("string", $x); }, $res[0]);
		$body = new Base\MBody($res[1]);
		return new Base\MFunction(new Base\MId("__construct"), $args, $body);
	}

	private function mk_props($def) {
		$args = [];
		$assigns = [];
		foreach ($def->properties as $pdef) {
			$id = new Base\MId($pdef->name);
			$var = new Base\MVar($id);
			$acc = new Base\MThisAccessor($id);
			$ass = new Base\MAssign($acc, $var);
			$args[] = $var;
			$assigns[] = $ass;
		}
		return [$args, $assigns];
	}
}


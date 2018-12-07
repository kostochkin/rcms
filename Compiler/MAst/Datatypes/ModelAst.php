<?php

namespace rCMS\Compiler\MAst\Datatypes;

use \rCMS\Compiler\MAst\Base;

function classes_from_definition($def) {
	pretty_print($def);
	$api = new ApiObjectClass($def);
	$int = new InternalObjectClass($def);
	$con = new ObjectContainerClass($def);
	$decl = new Base\MDeclarationSequence([$api, $int, $con]);
#	return class_from_definition($def);
	return $decl;
}

/*

function class_from_definition($def) {
	$c = new MClass(new MId($def->name));
	$c->add_function(new ModelClassConstructor($def));
	$c->add_function(new ModelMaker($def));
	foreach ($def->properties as $pdef) {
		$var = new MVar(new MId($pdef->decorated_name));
		$c->add_var($var);
		$c->add_function(new FunctionGetter($var, $pdef));
		if ($pdef->type != "id") {
			$c->add_function(new FunctionSetter($var, $pdef));
		}
		$norm = get_normalizer($pdef->type);
	  if (!is_null($norm)) $c->add_function(new $norm($var, $pdef));
		$denorm = get_denormalizer($pdef->type);
	  if (!is_null($denorm)) $c->add_function(new $denorm($var, $pdef));
		// Add db var;
	};
	$c->add_function(new StaticGetById(new MVar(new MId(Strings::VARDB)), $def));
	$c->add_var(new MVar(new MId(Strings::VARDB)));
	$c->add_function(new MObjectFunction(new MId("assign_db"), [new MVar(new MId(Strings::VARDB))],
		new MBody([new MAssign(new MThisAccessor(new MId(Strings::VARDB)), new MVar(new MId(Strings::VARDB)))]), false));
	return $c;
}


abstract class Normalizer extends MObjectFunction {
	public function __construct($var, $def) {
		$de = $this->is_denormailzer() ? "de" : "";
		$name = new MId("{$de}normalize_" . $def->name);
		$if = new MIf(new MIsNullP($var), new MBody([new MReturn($var)]));
		$body1 =  $this->make_body($var, $def);
		array_unshift($body1, $if);
		$body = new MBody($body1);
		parent::__construct($name, [$var], $body, false, true);
	}

	protected function is_denormailzer() {
		return false;
	}

	abstract protected function make_body($var, $def);
}

class EnumNormalizer extends Normalizer {
	protected function make_body($var, $def) {
		$cases = [];
		foreach ($def->enum_list as $id => $str) {
			$cases[] = new MCase(new MString($str), new MBodySequence([new MReturn(new MNum($id))]));
		}
		$switch = new MSwitch($var, new MBody($cases));
		return [$switch, new MReturn(new MNull)];
	}
}

class EnumDenormalizer extends Normalizer {
	protected function is_denormailzer() {
		return true;
	}

	protected function make_body($var, $def) {
		$cases = [];
		foreach ($def->enum_list as $id => $str) {
			$cases[] = new MCase(new MNum($id), new MBodySequence([new MReturn(new MString($str))]));
		}
		$switch = new MSwitch($var, new MBody($cases));
		return [$switch, new MReturn(new MNull)];
	}
}

class ArrayNormalizer extends Normalizer {
	protected function make_body($var, $def) {
		return [new MReturn($var)];
	}
}

class ArrayDenormalizer extends Normalizer {
	protected function is_denormailzer() {
		return true;
	}
	protected function make_body($var, $def) {
		return [new MReturn($var)];
	}
}

class RefNormalizer extends Normalizer {
	protected function make_body($var, $def) {
		$fn = new MObjectAccessor($var, new MId("get_id"));
		return [new MReturn(new MApplication($fn))];
	}
}

class RefDenormalizer extends Normalizer {
	protected function is_denormailzer() {
		return true;
	}

	protected function make_body($var, $def) {
		$name = new MId($def->name);
		$fn = new MStaticAccessor($name, new MId("get_by_id"));
		return [new MReturn(new MApplication($fn, [$var, new MVar(new MId(Strings::VARDB))]))];
	}
}

class ModelClassConstructor extends MObjectFunction {
	public function __construct($def) {
		$name = new MId("__construct");
		$pa = $this->make_prop_assigns($def);
		parent::__construct($name, $pa[0], new MBody($pa[1]), false);
	}

	protected function make_prop_assigns($def) {
		$a = [];
		$b = [];
		foreach ($def->properties as $pdef) {
			$e = $this->make_prop_assign($pdef, $def);
			if (!is_null($e)) {
				$a[] = $e[0];
				if (!is_null($e[1])) {
					$b[] = $e[1];
				}
			}
		}
		return [$a, $b];
	}

	protected function make_prop_assign($prop, $def) {
		  $var1 = new MVar(new MId($prop->decorated_name));
			$var2 = new MVar(new MId($prop->name));
		  $acc = new MThisAccessor($var1);
			$ass = new MAssign($acc, $var2);
			return [$var2, $ass];
	}
}

class ModelMaker extends ModelClassConstructor {
	public function __construct($def) {
		$name = new MId("make");
		$pa = $this->make_prop_assigns($def);
		$ppa = $pa[0];
		array_unshift($pa[0], new MVar(new MId(Strings::VARDB)));
		array_unshift($ppa, new MNull());
		$new = new MNew(new MId($def->name), $ppa);
		$newvar = new MVar(new MId("new"));
		$pa[1][] = new MAssign($newvar, $new);
		$pa[1][] = new MApplication(new MObjectAccessor($newvar, new MId("assign_db")), [new MVar(new MId(Strings::VARDB))]);
		$pa[1][] = new MReturn($newvar);
		MObjectFunction::__construct($name, $pa[0], new MBody($pa[1]), true, true);
	}

	protected function make_prop_assign($prop, $def) {
		if ($prop->type != "id") {
			$var = new MVar(new MId($prop->name));
			$norm = get_normalizer($prop->type);
			if (!is_null($norm)) {
				$norm = new $norm($var, $prop);
				$ap = new MApplication($norm->name, [$var]);
				$n = new MAssign($var, $ap);
			} else {
				$n = null;
			}
			return [$var, $n];
		}
	}
}


class FunctionGetter extends MObjectFunction {
	public function __construct($var, $def) {
		$dname = new MId($def->decorated_name);
		$name = new MId("get_" . $def->name);
		$denorm = get_denormalizer($def->type);
		if (!is_null($denorm)) {
			$denorm = new $denorm($var, $def);
			$thisVar = new MThisAccessor($dname);
			$static = new MSelfAccessor($denorm->name);
			$ret = new MApplication($static, [$thisVar]);
		} else {
			$ret = new MThisAccessor($var->name);
		}
		$return = new MReturn($ret);
		$body = new Mbody([$return]);
		parent::__construct($name, [], $body);
	}

}

class FunctionSetter extends MObjectFunction {
	public function __construct($var, $def) {
		$dname = new MId($def->decorated_name);
		$name = new MId("set_" . $def->name);
		$clean_var = new MVar(new MId($def->name));
		$objvar = new MThisAccessor($var->name);
		$norm = get_normalizer($def->type);
		if (!is_null($norm)) {
			$norm = new $norm($var, $def);
			$thisVar = new MThisAccessor($dname);
			$static = new MSelfAccessor($norm->name);
			$assgn = new MApplication($static, [$clean_var]);
		} else {
			$assgn = $clean_var;
		}
		$body = new Mbody([new MAssign($objvar, $assgn)]);
		parent::__construct($name, [$clean_var], $body);
	}
}

class StaticGetById extends MObjectFunction {
	public function __construct($db, $def) {
		$id = new MVar(new MId("id"));
		$name = new MId("get_by_id");
		$body = $this->make_body($id, $db, $def);
		parent::__construct($name, [$id, $db], $body, true, true);
	}

	public function make_body($id, $db, $def) {
		$name = new MId("get_one_" . $def->name);
		$chk_nullp = new MIsNullP($id);
		$sa = new MObjectAccessor($db, $name);
		$app = new MApplication($sa, [$id]);
		$ret = new MReturn($app);
		$if = new MIf($chk_nullp, new MBody([new MReturn($id)]));
		return new MBody([$if, $ret]);
	}
}

 */

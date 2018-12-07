<?php
namespace rCMS\Compiler\Intermediate;

use \rCMS\Compiler\Syntax\Syntax;

/* class Error {
	const OK = 0;
	const NOT_INTEGER = 1;
	const NOT_NUMBER = 2;
	const NOT_STRING = 3;
	const NOT_VALID_ENUM = 4;
	const TOO_BIG = 5;
	const TOO_SMALL = 6;
}
 */

class Datatypes implements \Iterator {
	// Iterator helpers
	private $definitions;
	private $keys;
	private $position;
	// * --------------
	private $reader;
	private $default_storage_class_name;

	public function __construct(Syntax $reader, string $default_storage_class_name) {
		$this->reader = $reader;
		$this->default_storage_class_name = $default_storage_class_name;
		$defs = (array)$reader->dt_definitions();
		array_walk($defs, [$this, "create_new"]);
//		new \rCMS\PrettyPrint($this->definitions);
	}
	
	public function rewind() {
		$this->position = 0;
	}

	public function current() {
		return $this->definitions[$this->key()];
	}
	
	public function next() {
		++$this->position;
	}

	public function valid() {
		return isset($this->keys[$this->position]);
	}

	public function key() {
		return $this->keys[$this->position];
	}

	private function create_new($def, $name) {
		$db = new $this->default_storage_class_name($name);
		$ucd = new Model($name, $def, $db);
		$this->definitions[$name] = $ucd;
		$this->keys[] = $name;
	}

	static public function get_by_name($name) {
		if (array_key_exists($name, self::$definitions)) {
			return self::$definitions[$name];
		} else {
			return null;
		}	
	}

	static public function get_all() {
		return self::$definitions;
	}

}

class Model {

	public $name;
	public $decorated_name;
	public $constants = [];
	public $properties = [];
	public $functions;
	public $db;
	private $definition;

	public function __construct($name, $def, $db) {
		$this->name  = $name;
		$this->container_name = $this->pluralize($name);
		$this->db_table_name = "um_" . $this->container_name;
		$this->internal_name = "{$name}Internal";
		$this->external_name = "{$name}";
		$this->decorated_name = "um_{$name}";
		$this->definition = $def;
		$this->db = new $db($this->db_table_name);
		$this->append_properties();
		# -- Clean $definition. We don't need it anymore.
		$this->definition = null;
	}

	# TODO: There is a dummy pluralizing function. We need cleverer one. Find any useful inflection lib.
	private function pluralize($name) {
		return $name . "s";
	}

	public function append_constant($c) {
		$this->constants[] = $c;
	}

	private function append_properties() {
		foreach(get_object_vars($this->definition->properties) as $n => $p) {
			$this->properties[] = Property::from_definition($n, $p, $this);
		}
	}
}

function safe_property($obj, $property) {
		if (property_exists($obj, $property)) {
			return $obj->$property;
		} else {
			return null;
		}
}

class Property {
	
	public $name;
	public $decorated_name;
	public $description;
	public $format;
	public $constraints = [];
	public $type;
	private $definition;
	 
	public function __construct($name, $def, $cls) {
		$this->name = $name;
		$this->decorated_name = "uf_{$name}";
		$this->definition = $def;
		$this->description = $this->get_def_property("description");
		$this->format = $this->get_def_property("format");
		$this->type = $this->get_def_property("type");
	}

	public function finish_def() {
		$this->definition = null;
	}

	protected function append_constraint($constraint) {
		$this->constraints[] = $constraint;
	}

	protected function get_def_property($property) {
		return safe_property($this->definition, $property);
	}

	static public function from_definition($name, $def, $cls) {
		$type = safe_property($def, "type");
		if (is_null($type)) {
			return new Reference($name, $def, $cls);
		} else {
			switch ($def->type) {
				case "integer":
					return new PropertyInt($name, $def, $cls);
				case "string":
					return PropertyString::construct($name, $def, $cls);
				case "boolean":
					return new PropertyBoolean($name, $def, $cls);
				case "array":
					return new PropertyArray($name, $def, $cls);
				case "number":
					return new PropertyNumber($name, $def, $cls);
				default:
					return new Property($name, $def, $cls);
			}
		}
	}
}

class Reference extends Property {
	public $ref;
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$key = "\$ref";
		$this->ref = $def->$key;
		$this->type = "ref";
		parent::finish_def();
	}
}

class PropertyArray extends Property {
	public $items;
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$items_def = $this->get_def_property("items");
		$this->items = Property::from_definition("{$name}_items", $items_def, $cls);
		parent::finish_def();
	}
}

class PropertyId extends Property {
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		parent::finish_def();
	}
}

class PropertyNumber extends Property {
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$this->append_constraint(ConstraintSimpleFunc("is_numeric"));
		parent::finish_def();
	}
}

class PropertyInt extends Property {
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$this->append_constraint(new ConstraintSimpleFunc("is_int"));
		switch ($this->format) {
			case "int32":
				$this->append_constraint(ConstraintBOP::GE(-2**31));
				$this->append_constraint(ConstraintBOP::LE(2**31 - 1));
				break;
			case "int64":
				$this->append_constraint(ConstraintBOP::GE(intval(-2**63)));
				$this->append_constraint(ConstraintBOP::LE(intval(2**62 - 1 + 2**62)));
				break;
		}
		parent::finish_def();
	}
}

class PropertyString extends Property {
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$this->append_constraint(new ConstraintSimpleFunc("is_string"));
		parent::finish_def();
	}

	static public function construct($name, $def, $cls) {
		$enum = safe_property($def, "enum");
		if (is_null($enum)) {
			return new PropertyString($name, $def, $cls);
		} else {
			return new PropertyStringEnum($name, $def, $cls);
		}
	}
}

class PropertyStringEnum extends Property {
	public $enum_type;
	public $enum_list;
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$this->enum_list = $def->enum;
		$this->enum_type = "string";
		$this->type = "enum";
		parent::finish_def();
	}
}

class PropertyBoolean extends Property {
	public function __construct($name, $def, $cls) {
		parent::__construct($name, $def, $cls);
		$this->append_constraint(new ConstraintSimpleFunc("is_bool"));
		parent::finish_def();
	}
}

abstract class Constraint {
	public $constraint;
	abstract public function render($var);
}

class ConstraintBOP extends Constraint {
	public function __construct($op, $value) {
		$this->constraint = [$op, $value];
	}
	public function render($var) {
		$op = $this->constraint[0];
		$val = $this->constraint[1];
		return "({$var} {$op} ({$val}))";
	}

	static public function LT($min) {
		return new ConstraintBOP("<", $min);
	}
	
	static public function LE($min) {
		return new ConstraintBOP("<=", $min);
	}
	
	static public function GT($min) {
		return new ConstraintBOP(">", $min);
	}
	
	static public function GE($min) {
		return new ConstraintBOP(">=", $min);
	}
}

class ConstraintSimpleFunc extends Constraint {
	public function __construct($func) {
		$this->constraint = $func;
	}
	public function render($var) {
		$func = $this->constraint;
		return "({$func}({$var}))";
	}
}

class ConstraintInArray extends Constraint {
	public function __construct($haystack) {
		$this->constraint = $haystack;
	}
	public function render($var) {
		$hs = implode(", ", array_keys($this->constraint[1]));
		return "(in_array({$var}, {[$hs]}))";
	}
	
	public function get_enum_array() {
		return $this->constraint;
	}
}


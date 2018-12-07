<?php

namespace rCMS\Compiler;

use rCMS\Compiler\MAst\Base;
use rCMS\Compiler\MAst\Storage\Strings;
use rCMS\Compiler\MAst\Datatypes\ApiObjectClass;
use rCMS\Compiler\MAst\Datatypes\InternalObjectClass;
use rCMS\Compiler\MAst\Datatypes\ObjectContainerClass;

class DatatypeCompiler implements Compiler {
	private $datatypes;
	private $sources;
	private $namespace;

	public function __construct(Intermediate\Datatypes $dt, string $namespace) {
		$this->datatypes = $dt;
		$this->namespace = $namespace;
	}

	public function eval() {
		$this->compile();
		foreach ($this->sources as $s) {
			eval($s->to_string());
		};
	}

	public function compile() {
		$this->sources = [];
		foreach ($this->datatypes as $dt) {
			$ns = new Base\MNamespaceDeclaration(new Base\MId($this->namespace));
			$use = new Base\MUseDeclaration(new Base\MId("\\" . Strings::PDO));
 			$api = new ApiObjectClass($dt);
 			$int = new InternalObjectClass($dt);
 			$con = new ObjectContainerClass($dt);
			$this->sources[] = new Base\MDeclarationSequence([$ns, $use, $api, $int, $con]);
		}
	}
}

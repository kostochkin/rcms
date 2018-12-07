<?php

namespace rCMS\Compiler\Syntax;

class Json implements Syntax {
	private $source;

	public function __construct($source) {
		$this->source = $source;
	}

	public function dt_definitions() : array {
		return (array)$this->decode()->definitions;
	}

	private function decode() {
		return json_decode($this->source->get_content());
	}
}


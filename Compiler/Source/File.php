<?php

namespace rCMS\Compiler\Source;


class File implements Source {
	private $filename;

	public function __construct(string $filename) {
		$this->filename = $filename;
	}

	public function get_content() : string {
		return file_get_contents($this->filename);
	}
}

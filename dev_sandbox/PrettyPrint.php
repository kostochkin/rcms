<?php

namespace rCMS;

# Do not delete
class PrettyPrint {
	public function __construct($value) {
		$value = print_r($value, true);
		$value = str_replace("\n", "<br>\n", $value);
		$value = str_replace("    ", "\t", $value);
		$value = str_replace("\t", "<span style=\"color: darkgray\">-</span><span style=\"color: lightgray\">--&gt</span>", $value);
		print($value);
	}
}


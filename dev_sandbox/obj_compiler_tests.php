<?php
namespace rCMS;

error_reporting( E_ALL );
ini_set('display_errors', 1);

require_once("PrettyPrint.php");

spl_autoload_register(function ($name) {
	$expl = explode("\\", $name);
	if (strtolower($expl[0]) === "rcms") {
		$expl[0] = "..";
		$ret = require_once (join('/', $expl) . ".php");
	};
	return false;
});


$ns = 'rCMS\UserTypes';
$test = new Compiler\Source\File("test.json");
$reader = new Compiler\Syntax\Json($test);
$db_class = '\rCMS\Compiler\MAst\Storage\SQLite';
$datatypes = new Compiler\Intermediate\Datatypes($reader, $db_class);
$compiler = new Compiler\DatatypeCompiler($datatypes, $ns);
$compiler->eval();


if (!file_exists('test_database')) {
    mkdir('test_database', 0777, true);
}

$db = new \PDO('sqlite:test_database/test.db');

$cn = "{$ns}\\NamedObjects";
$a = new $cn($db);
$cn1 = "{$ns}\\NamedObject";
$o = new $cn1("Hello", "old", "world");
$int = $a->new_from_representation($o);
new PrettyPrint($int);
$int->update_field2("new " . (string)$int->get_id());
new PrettyPrint($int);
$int2 = $a->find_by_id(1);
new PrettyPrint($int2);
//var_dump($int);


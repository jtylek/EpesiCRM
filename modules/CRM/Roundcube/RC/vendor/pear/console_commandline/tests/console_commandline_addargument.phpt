--TEST--
Test for Console_CommandLine::addArgument() method.
--FILE--
<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tests.inc.php';

$parser = new Console_CommandLine();
$parser->addArgument('arg1');
$parser->addArgument('arg2', array(
    'multiple' => true,
    'description' => 'description of arg2'
));
$arg3 = new Console_CommandLine_Argument('arg3', array(
    'multiple' => true,
    'description' => 'description of arg3'    
));
$parser->addArgument($arg3);
$parser->addArgument('arg4', array('optional' => true));

var_dump($parser->args);

// a bad argument
$parser->addArgument('Some invalid name');

?>
--EXPECTF--
array(4) {
  ["arg1"]=>
  object(Console_CommandLine_Argument)#%d (8) {
    ["multiple"]=>
    bool(false)
    ["optional"]=>
    bool(false)
    ["choices"]=>
    array(0) {
    }
    ["name"]=>
    string(4) "arg1"
    ["help_name"]=>
    string(4) "arg1"
    ["description"]=>
    NULL
    ["default"]=>
    NULL
    ["messages"]=>
    array(0) {
    }
  }
  ["arg2"]=>
  object(Console_CommandLine_Argument)#%d (8) {
    ["multiple"]=>
    bool(true)
    ["optional"]=>
    bool(false)
    ["choices"]=>
    array(0) {
    }
    ["name"]=>
    string(4) "arg2"
    ["help_name"]=>
    string(4) "arg2"
    ["description"]=>
    string(19) "description of arg2"
    ["default"]=>
    NULL
    ["messages"]=>
    array(0) {
    }
  }
  ["arg3"]=>
  object(Console_CommandLine_Argument)#%d (8) {
    ["multiple"]=>
    bool(true)
    ["optional"]=>
    bool(false)
    ["choices"]=>
    array(0) {
    }
    ["name"]=>
    string(4) "arg3"
    ["help_name"]=>
    string(4) "arg3"
    ["description"]=>
    string(19) "description of arg3"
    ["default"]=>
    NULL
    ["messages"]=>
    array(0) {
    }
  }
  ["arg4"]=>
  object(Console_CommandLine_Argument)#%d (8) {
    ["multiple"]=>
    bool(false)
    ["optional"]=>
    bool(true)
    ["choices"]=>
    array(0) {
    }
    ["name"]=>
    string(4) "arg4"
    ["help_name"]=>
    string(4) "arg4"
    ["description"]=>
    NULL
    ["default"]=>
    NULL
    ["messages"]=>
    array(0) {
    }
  }
}

Fatal error: argument name must be a valid php variable name (got: Some invalid name) in %sCommandLine.php on line %d

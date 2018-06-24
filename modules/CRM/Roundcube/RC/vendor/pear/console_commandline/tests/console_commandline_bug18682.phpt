--TEST--
Test for bug #18682: columnWrap() in Default Renderer eats up lines with only a EOL.
--ARGS--
cmd1 --help 2>&1
--FILE--
<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tests.inc.php';

class Renderer extends Console_CommandLine_Renderer_Default {
  protected function description() {
    return $this->columnWrap($this->parser->description, 2);
  }
}

$parser = new Console_CommandLine();
$parser->accept(new Renderer);
$parser->renderer->line_width = 75;
$parser->addCommand('cmd1', array(
    'description' => '
Installs listed packages.

local package.xml example:
php pyrus.phar install package.xml

local package archive example:
php pyrus.phar install PackageName-1.2.0.tar

remote package archive example:
php pyrus.phar install http://www.example.com/PackageName-1.2.0.tgz

Examples of an abstract package:
php pyrus.phar install PackageName
  installs PackageName from the default channel with stability preferred_state
php pyrus.phar pear/PackageName
  installs PackageName from the pear.php.net channel with stability preferred_state
php pyrus.phar install channel://doc.php.net/PackageName
  installs PackageName from the doc.php.net channel with stability preferred_state
php pyrus.phar install PackageName-beta
  installs PackageName from the default channel, beta or stable stability
php pyrus.phar install PackageName-1.2.0
  installs PackageName from the default channel, version 1.2.0'
));
$parser->parse();

?>
--EXPECTF--
  Installs listed packages.

  local package.xml example:
  php pyrus.phar install package.xml

  local package archive example:
  php pyrus.phar install PackageName-1.2.0.tar

  remote package archive example:
  php pyrus.phar install http://www.example.com/PackageName-1.2.0.tgz

  Examples of an abstract package:
  php pyrus.phar install PackageName
    installs PackageName from the default channel with stability
  preferred_state
  php pyrus.phar pear/PackageName
    installs PackageName from the pear.php.net channel with stability
  preferred_state
  php pyrus.phar install channel://doc.php.net/PackageName
    installs PackageName from the doc.php.net channel with stability
  preferred_state
  php pyrus.phar install PackageName-beta
    installs PackageName from the default channel, beta or stable stability
  php pyrus.phar install PackageName-1.2.0
    installs PackageName from the default channel, version 1.2.0

Usage:
  %sconsole_commandline_bug18682.php
  [options] cmd1 [options]

Options:
  -h, --help  show this help message and exit

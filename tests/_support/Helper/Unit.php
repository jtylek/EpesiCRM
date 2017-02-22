<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\TestInterface;

class Unit extends \Codeception\Module
{
    function _after(TestInterface $test)
    {
        \AspectMock\Test::clean();
    }
}

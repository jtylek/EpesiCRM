<?php

/*
 * This file is part of the memio/linter package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Memio\Linter;

use Memio\Model\Method;
use PhpSpec\ObjectBehavior;

class MethodCannotBeBothAbstractAndFinalSpec extends ObjectBehavior
{
    function it_is_a_constraint()
    {
        $this->shouldImplement('Memio\Validator\Constraint');
    }

    function it_is_fine_with_simple_methods(Method $method)
    {
        $method->isAbstract()->willReturn(false);
        $method->isFinal()->willReturn(false);

        $this->validate($method)->shouldHaveType('Memio\Validator\Violation\NoneViolation');
    }

    function it_is_fine_with_abstract_methods(Method $method)
    {
        $method->isAbstract()->willReturn(true);
        $method->isFinal()->willReturn(false);

        $this->validate($method)->shouldHaveType('Memio\Validator\Violation\NoneViolation');
    }

    function it_is_fine_with_final_methods(Method $method)
    {
        $method->isAbstract()->willReturn(false);
        $method->isFinal()->willReturn(true);

        $this->validate($method)->shouldHaveType('Memio\Validator\Violation\NoneViolation');
    }

    function it_is_not_fine_with_abstract_and_final_methods(Method $method)
    {
        $method->isAbstract()->willReturn(true);
        $method->isFinal()->willReturn(true);
        $method->getName()->willReturn('__construct');

        $this->validate($method)->shouldHaveType('Memio\Validator\Violation\SomeViolation');
    }
}

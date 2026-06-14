<?php

/*
 * This file is part of the memio/validator package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Memio\Validator;

use Memio\Model\Argument;
use Memio\Exception\ConstraintFailedException;
use Memio\Validator\Constraint;
use Memio\Validator\Violation\NoneViolation;
use Memio\Validator\Violation\SomeViolation;
use Memio\Validator\Violation\ManyViolations;
use PhpSpec\ObjectBehavior;

class ConstraintValidatorSpec extends ObjectBehavior
{
    const FIRST_VIOLATION = 'Model is invalid';
    const SECOND_VIOLATION = 'Model is even more invalid';

    function let(Constraint $firstConstraint, Constraint $secondConstraint)
    {
        $this->add($firstConstraint);
        $this->add($secondConstraint);
    }

    function it_returns_no_violations_if_model_is_fine(
        Argument $model,
        Constraint $firstConstraint,
        Constraint $secondConstraint,
        NoneViolation $noneViolation1,
        NoneViolation $noneViolation2
    )
    {
        $firstConstraint->validate($model)->willReturn($noneViolation1);
        $secondConstraint->validate($model)->willReturn($noneViolation2);

        $this->validate($model)->raise();
    }

    function it_returns_violations_if_model_is_invalid(
        Argument $model,
        Constraint $firstConstraint,
        Constraint $secondConstraint,
        NoneViolation $noneViolation,
        SomeViolation $someViolation
    )
    {
        $someViolation->getMessage()->willReturn(self::FIRST_VIOLATION);
        $firstConstraint->validate($model)->willReturn($noneViolation);
        $secondConstraint->validate($model)->willReturn($someViolation);

        $invalidModelException = 'Memio\Validator\Exception\InvalidModelException';
        $this->validate($model)->shouldThrow($invalidModelException)->duringRaise();
    }
}

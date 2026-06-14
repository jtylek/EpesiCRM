<?php

/*
 * This file is part of the memio/validator package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Validator\ModelValidator;

use Memio\Model\Argument;
use Memio\Validator\Constraint;
use Memio\Validator\ConstraintValidator;
use Memio\Validator\ModelValidator;
use Memio\Validator\ViolationCollection;

class ArgumentValidator implements ModelValidator
{
    /**
     * @var ConstraintValidator
     */
    private $constraintValidator;

    public function __construct()
    {
        $this->constraintValidator = new ConstraintValidator();
    }

    /**
     * {@inheritDoc}
     */
    public function add(Constraint $constraint)
    {
        $this->constraintValidator->add($constraint);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($model)
    {
        return $model instanceof Argument;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        if (!$this->supports($model)) {
            return new ViolationCollection();
        }

        return $this->constraintValidator->validate($model);
    }
}

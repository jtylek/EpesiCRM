<?php

/*
 * This file is part of the memio/linter package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Linter;

use Memio\Validator\Constraint;
use Memio\Validator\Violation\NoneViolation;
use Memio\Validator\Violation\SomeViolation;

class MethodCannotBeAbstractAndHaveBody implements Constraint
{
    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        if ($model->isAbstract() && null !== $model->getBody()) {
            return new SomeViolation(sprintf('Method "%s" cannot be abstract and have a body', $model->getName()));
        }

        return new NoneViolation();
    }
}

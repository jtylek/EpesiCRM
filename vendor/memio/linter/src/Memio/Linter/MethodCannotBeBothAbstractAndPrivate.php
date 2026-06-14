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

class MethodCannotBeBothAbstractAndPrivate implements Constraint
{
    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        if ($model->isAbstract() && 'private' === $model->getVisibility()) {
            return new SomeViolation(sprintf('Method "%s" cannot be both abstract and private', $model->getName()));
        }

        return new NoneViolation();
    }
}

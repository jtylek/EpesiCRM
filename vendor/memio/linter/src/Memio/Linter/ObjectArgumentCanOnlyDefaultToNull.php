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

use Memio\Model\Type;
use Memio\Validator\Constraint;
use Memio\Validator\Violation\NoneViolation;
use Memio\Validator\Violation\SomeViolation;

class ObjectArgumentCanOnlyDefaultToNull implements Constraint
{
    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        $type = new Type($model->getType());
        $defaultValue = $model->getDefaultValue();
        if (!$type->isObject() || null === $defaultValue || 'null' === $defaultValue) {
            return new NoneViolation();
        }

        return new SomeViolation(sprintf('Object Argument "%s" can only default to null', $model->getName()));
    }
}

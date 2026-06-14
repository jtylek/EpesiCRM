<?php

/*
 * This file is part of the memio/validator package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Validator;

interface Constraint
{
    /**
     * @param mixed $model
     *
     * @return Violation
     */
    public function validate($model);
}

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

class ConstraintValidator
{
    /**
     * @var array
     */
    private $constraints = array();

    /**
     * @param Constraint $constraint
     */
    public function add(Constraint $constraint)
    {
        $this->constraints[] = $constraint;
    }

    /**
     * @param mixed $model
     *
     * @return ViolationCollection
     */
    public function validate($model)
    {
        $violationCollection = new ViolationCollection();
        foreach ($this->constraints as $constraint) {
            $violationCollection->add($constraint->validate($model));
        }

        return $violationCollection;
    }
}

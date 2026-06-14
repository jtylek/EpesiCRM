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

use Memio\Validator\ModelValidator;
use Memio\Validator\ViolationCollection;

class Validator
{
    /**
     * @var array
     */
    private $modelValidators = array();

    /**
     * @param ModelValidator $modelValidator
     */
    public function add(ModelValidator $modelValidator)
    {
        $this->modelValidators[] = $modelValidator;
    }

    /**
     * @param mixed $model
     *
     * @throws \Memio\Validator\Exception\InvalidModelException If model is invalid
     */
    public function validate($model)
    {
        $violations = new ViolationCollection();
        foreach ($this->modelValidators as $modelValidator) {
            if ($modelValidator->supports($model)) {
                $violations->merge($modelValidator->validate($model));
            }
        }
        $violations->raise();
    }
}

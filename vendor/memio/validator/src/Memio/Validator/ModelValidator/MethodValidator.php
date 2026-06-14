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

use Memio\Model\Method;
use Memio\Validator\Constraint;
use Memio\Validator\ConstraintValidator;
use Memio\Validator\ModelValidator;
use Memio\Validator\ViolationCollection;

class MethodValidator implements ModelValidator
{
    /**
     * @var ArgumentValidator
     */
    private $argumentValidator;

    /**
     * @var CollectionValidator
     */
    private $collectionValidator;

    /**
     * @var ConstraintValidator
     */
    private $constraintValidator;

    /**
     * @param ArgumentValidator   $argumentValidator
     * @param CollectionValidator $collectionValidator
     */
    public function __construct(ArgumentValidator $argumentValidator, CollectionValidator $collectionValidator)
    {
        $this->argumentValidator = $argumentValidator;
        $this->collectionValidator = $collectionValidator;

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
        return $model instanceof Method;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        if (!$this->supports($model)) {
            return new ViolationCollection();
        }
        $violationCollection = $this->constraintValidator->validate($model);
        $arguments = $model->allArguments();
        $violationCollection->merge($this->collectionValidator->validate($arguments));
        foreach ($arguments as $argument) {
            $violationCollection->merge($this->argumentValidator->validate($argument));
        }

        return $violationCollection;
    }
}

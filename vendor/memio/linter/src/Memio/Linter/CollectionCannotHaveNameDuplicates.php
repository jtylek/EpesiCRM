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

use Memio\Model\FullyQualifiedName;
use Memio\Validator\Constraint;
use Memio\Validator\Violation\NoneViolation;
use Memio\Validator\Violation\SomeViolation;

class CollectionCannotHaveNameDuplicates implements Constraint
{
    /**
     * {@inheritDoc}
     */
    public function validate($model)
    {
        $firstElement = current($model);
        $fqcn = get_class($firstElement);
        $modelType = FullyQualifiedName::make($fqcn)->getName();
        $nameCount = array();
        foreach ($model as $element) {
            $name = $element->getName();
            $nameCount[$name] = isset($nameCount[$name]) ? $nameCount[$name] + 1 : 1;
        }
        $messages = array();
        foreach ($nameCount as $name => $count) {
            if ($count > 1) {
                $messages[] = sprintf('Collection "%s" cannot have name "%s" duplicates (%s occurences)', $modelType, $name, $count);
            }
        }

        return (empty($messages) ? new NoneViolation() : new SomeViolation(implode("\n", $messages)));
    }
}

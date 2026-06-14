<?php

/*
 * This file is part of the memio/twig-template-engine package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\TwigTemplateEngine\TwigExtension;

use Memio\Model\Argument;
use Memio\Model\Contract;
use Memio\Model\FullyQualifiedName;
use Memio\Model\Type as ModelType;
use Twig_Extension;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Twig_SimpleTest;

class Type extends Twig_Extension
{
    /**
     * {@inheritDoc}
     */
    public function getFilters()
    {
        return array(
            new Twig_SimpleFilter('filter_namespace', array($this, 'filterNamespace')),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('has_typehint', array($this, 'hasTypehint')),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTests()
    {
        return array(
            new Twig_SimpleTest('contract', array($this, 'isContract')),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'type';
    }

    /**
     * @param mixed $model
     *
     * @return bool
     */
    public function isContract($model)
    {
        return $model instanceof Contract;
    }

    /**
     * @param mixed $model
     *
     * @return bool
     */
    public function hasTypehint($model)
    {
        if (!$model instanceof Argument) {
            return false;
        }
        $type = new ModelType($model->getType());

        return $type->hasTypehint();
    }

    /**
     * @param string $stringType
     *
     * @return string
     */
    public function filterNamespace($stringType)
    {
        $type = new ModelType($stringType);
        if (!$type->isObject()) {
            return $stringType;
        }
        $fullyQualifiedName = new FullyQualifiedName($stringType);

        return $fullyQualifiedName->getName();
    }
}

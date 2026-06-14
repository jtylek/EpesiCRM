<?php

/*
 * This file is part of the memio/twig-template-engine package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\TwigTemplateEngine\TwigExtension\Line;

use Memio\Model\Phpdoc\StructurePhpdoc;

class StructurePhpdocLineStrategy implements LineStrategy
{
    /**
     * {@inheritDoc}
     */
    public function supports($model)
    {
        return $model instanceof StructurePhpdoc;
    }

    /**
     * {@inheritDoc}
     */
    public function needsLineAfter($model, $block)
    {
        $hasDescription = (null !== $model->getDescription());
        $hasApiTag = (null !== $model->getApiTag());
        $hasDeprecationTag = (null !== $model->getDeprecationTag());
        if ('description' === $block) {
            return ($hasDescription && ($hasApiTag || $hasDeprecationTag));
        }
        if ('deprecation_tag' === $block) {
            return ($hasApiTag && $hasDeprecationTag);
        }
    }
}

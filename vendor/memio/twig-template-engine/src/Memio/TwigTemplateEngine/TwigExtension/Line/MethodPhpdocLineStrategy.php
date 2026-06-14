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

use Memio\Model\Phpdoc\MethodPhpdoc;

class MethodPhpdocLineStrategy implements LineStrategy
{
    /**
     * {@inheritDoc}
     */
    public function supports($model)
    {
        return $model instanceof MethodPhpdoc;
    }

    /**
     * {@inheritDoc}
     */
    public function needsLineAfter($model, $block)
    {
        $parameterTags = $model->getParameterTags();
        $throwTags = $model->getThrowTags();

        $hasApiTag = (null !== $model->getApiTag());
        $hasParameterTags = (!empty($parameterTags));
        $hasDescription = (null !== $model->getDescription());
        $hasDeprecationTag = (null !== $model->getDeprecationTag());
        $hasReturnTag = (null !== $model->getReturnTag());
        $hasThrowTags = (!empty($throwTags));

        if ('description' === $block) {
            return ($hasDescription && ($hasReturnTag || $hasApiTag || $hasDeprecationTag || $hasParameterTags || $hasThrowTags));
        }
        if ('parameter_tags' === $block) {
            return ($hasParameterTags && ($hasReturnTag || $hasApiTag || $hasDeprecationTag || $hasThrowTags));
        }
        if ('deprecation_tag' === $block) {
            return ($hasDeprecationTag && ($hasReturnTag || $hasApiTag || $hasThrowTags));
        }
        if ('return_tag' === $block) {
            return ($hasReturnTag && ($hasApiTag || $hasThrowTags));
        }
        if ('throw_tags' === $block) {
            return ($hasThrowTags && $hasApiTag);
        }
    }
}

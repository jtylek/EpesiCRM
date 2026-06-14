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

use Memio\PrettyPrinter\Exception\InvalidArgumentException;
use Memio\Model\File;

class FileLineStrategy implements LineStrategy
{
    /**
     * {@inheritDoc}
     */
    public function supports($model)
    {
        return $model instanceof File;
    }

    /**
     * {@inheritDoc}
     */
    public function needsLineAfter($model, $block)
    {
        $fullyQualifiedNames = $model->allFullyQualifiedNames();
        if ('fully_qualified_names' === $block) {
            return (!empty($fullyQualifiedNames));
        }

        throw new InvalidArgumentException('The function needs_line_after does not support given "'.$block.'"');
    }
}

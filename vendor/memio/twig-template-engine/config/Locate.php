<?php

/*
 * This file is part of the memio/twig-template-engine package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\TwigTemplateEngine\Config;

class Locate
{
    /**
     * @return string
     */
    public static function templates()
    {
        return __DIR__.'/../templates';
    }
}

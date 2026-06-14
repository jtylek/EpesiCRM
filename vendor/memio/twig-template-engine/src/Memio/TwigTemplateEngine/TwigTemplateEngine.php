<?php

/*
 * This file is part of the memio/twig-template-engine package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\TwigTemplateEngine;

use Memio\PrettyPrinter\TemplateEngine;
use Twig_Environment;

class TwigTemplateEngine implements TemplateEngine
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    /**
     * @param Twig_Environment $twig
     */
    public function __construct(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * {@inheritDoc}
     */
    public function addPath($path)
    {
        $this->twig->getLoader()->prependPath($path);
    }

    /**
     * {@inheritDoc}
     */
    public function render($template, array $parameters = array())
    {
        return $this->twig->render($template.'.twig', $parameters);
    }
}

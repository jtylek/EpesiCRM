<?php

/*
 * This file is part of the memio/pretty-printer package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\PrettyPrinter\CodeGenerator;

use Memio\Model\FullyQualifiedName;
use Memio\PrettyPrinter\TemplateEngine;

class ModelCodeGenerator implements CodeGenerator
{
    /**
     * @var TemplateEngine
     */
    private $templateEngine;

    /**
     * @param TemplateEngine $templateEngine
     */
    public function __construct(TemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($model)
    {
        if (!is_object($model)) {
            return false;
        }
        $fqcn = get_class($model);

        return 1 === preg_match('/^Memio\\\\Model\\\\/', $fqcn);
    }

    /**
     * {@inheritDoc}
     */
    public function generateCode($model, array $parameters = array())
    {
        $fqcn = get_class($model);
        $name = FullyQualifiedName::make($fqcn)->getName();
        $modelName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        $parameters[$modelName] = $model;

        return $this->templateEngine->render($modelName, $parameters);
    }
}

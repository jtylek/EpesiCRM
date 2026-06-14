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

interface CodeGenerator
{
    /**
     * @param mixed $model
     *
     * @return bool
     */
    public function supports($model);

    /**
     * @param mixed $model
     * @param array $parameters
     *
     * @return string
     */
    public function generateCode($model, array $parameters = array());
}

<?php

/*
 * This file is part of the memio/validator package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Validator\Exception;

/**
 * @api
 */
class InvalidModelException extends \DomainException implements Exception
{
    /**
     * @param array $violations
     */
    public function __construct(array $violations)
    {
        parent::__construct(implode("\n", $violations));
    }
}

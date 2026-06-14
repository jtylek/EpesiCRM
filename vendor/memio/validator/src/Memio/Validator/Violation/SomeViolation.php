<?php

/*
 * This file is part of the memio/validator package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Validator\Violation;

use Memio\Validator\Violation;

class SomeViolation implements Violation
{
    /**
     * @var string
     */
    private $message;

    /**
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage()
    {
        return $this->message;
    }
}

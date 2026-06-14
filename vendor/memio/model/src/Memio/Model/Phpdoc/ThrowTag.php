<?php

/*
 * This file is part of the memio/model package.
 *
 * (c) Loïc Chardonnet <loic.chardonnet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Model\Phpdoc;

class ThrowTag
{
    /**
     * @var string
     */
    private $exception;

    /**
     * @param string $exception
     *
     * @api
     */
    public function __construct($exception)
    {
        $this->exception = $exception;
    }

    /**
     * @param string $exception
     *
     * @return self
     *
     * @api
     */
    public static function make($exception)
    {
        return new self($exception);
    }

    /**
     * @return string
     */
    public function getException()
    {
        return $this->exception;
    }
}

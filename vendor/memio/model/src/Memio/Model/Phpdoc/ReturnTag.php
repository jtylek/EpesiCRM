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

class ReturnTag
{
    /**
     * @var string
     */
    private $type;

    /**
     * @param string $type
     *
     * @api
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * @param string $type
     *
     * @return self
     *
     * @api
     */
    public static function make($type)
    {
        return new self($type);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}

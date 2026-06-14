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

use Memio\Model\Type;

/**
 * @api
 */
class ParameterTag
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $description;

    /**
     * @param string $type
     * @param string $name
     * @param string $description
     *
     * @api
     */
    public function __construct($type, $name, $description = null)
    {
        $this->type = new Type($type);
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $description
     *
     * @return self
     *
     * @api
     */
    public static function make($type, $name, $description = null)
    {
        return new self($type, $name, $description);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type->getName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}

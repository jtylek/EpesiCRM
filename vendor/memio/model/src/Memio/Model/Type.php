<?php

/*
 * This file is part of the memio/model package.
 *
 * (c) Loïc Chardonnet <loic.chardonnet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Memio\Model;

/**
 * @api
 */
class Type
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isObject;

    /**
     * @var bool
     */
    private $hasTypeHint;

    /**
     * @param string $name
     *
     * @api
     */
    public function __construct($name)
    {
        $normalizations = array(
            'boolean' => 'bool',
            'integer' => 'int',
            'NULL' => 'null',
        );
        if (isset($normalizations[$name])) {
            $name = $normalizations[$name];
        }

        $nonObjectTypes = array('string', 'bool', 'int', 'double', 'callable', 'resource', 'array', 'null', 'mixed');
        $isCallableFromPhp54 = ('callable' === $name && version_compare(PHP_VERSION, '5.4.0') >= 0);

        $this->isObject = !in_array($name, $nonObjectTypes, true);
        $this->hasTypeHint = ($isCallableFromPhp54 || $this->isObject || 'array' === $name);
        $this->name = $name;
    }

    /**
     * @param string $name
     *
     * @return self
     *
     * @api
     */
    public static function make($name)
    {
        return new self($name);
    }

    /**
     * @return string
     *
     * @api
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return bool
     *
     * @api
     */
    public function isObject()
    {
        return $this->isObject;
    }

    /**
     * @return bool
     */
    public function hasTypeHint()
    {
        return $this->hasTypeHint;
    }
}

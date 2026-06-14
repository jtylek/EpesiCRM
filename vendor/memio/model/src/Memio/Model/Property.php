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

use Memio\Model\Phpdoc\PropertyPhpdoc;

/**
 * @api
 */
class Property
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var PropertyPhpdoc
     */
    private $propertyPhpdoc;

    /**
     * @var bool
     */
    private $isStatic = false;

    /**
     * @var string
     */
    private $visibility = 'private';

    /**
     * @var string
     */
    private $defaultValue;

    /**
     * @param string $name
     *
     * @api
     */
    public function __construct($name)
    {
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
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param PropertyPhpdoc $propertyPhpdoc
     *
     * @return self
     *
     * @api
     */
    public function setPhpdoc(PropertyPhpdoc $propertyPhpdoc)
    {
        $this->propertyPhpdoc = $propertyPhpdoc;

        return $this;
    }

    /**
     * @return PropertyPhpdoc
     */
    public function getPhpdoc()
    {
        return $this->propertyPhpdoc;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makeStatic()
    {
        $this->isStatic = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isStatic()
    {
        return $this->isStatic;
    }

    /**
     * @return self
     *
     * @api
     */
    public function removeStatic()
    {
        $this->isStatic = false;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makePrivate()
    {
        $this->visibility = 'private';

        return $this;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makeProtected()
    {
        $this->visibility = 'protected';

        return $this;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makePublic()
    {
        $this->visibility = 'public';

        return $this;
    }

    /**
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param string $defaultValue
     *
     * @return self
     *
     * @api
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
}

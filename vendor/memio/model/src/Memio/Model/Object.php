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

use Memio\Model\Phpdoc\StructurePhpdoc;

/**
 * A PHP Class ("class" is a reserved word and cannot be used as classname).
 *
 * @api
 */
class Object implements Structure
{
    /**
     * @var FullyQualifiedName
     */
    private $fullyQualifiedName;

    /**
     * @var StructurePhpdoc
     */
    private $structurePhpdoc;

    /**
     * @var bool
     */
    private $isAbstract = false;

    /**
     * @var bool
     */
    private $isFinal = false;

    /**
     * @var Object
     */
    private $parent;

    /**
     * @var Contract[]
     */
    private $contracts = array();

    /**
     * @var Constant[]
     */
    private $constants = array();

    /**
     * @var Property[]
     */
    private $properties = array();

    /**
     * @var Method[]
     */
    private $methods = array();

    /**
     * @param string $fullyQualifiedName
     *
     * @api
     */
    public function __construct($fullyQualifiedName)
    {
        $this->fullyQualifiedName = new FullyQualifiedName($fullyQualifiedName);
    }

    /**
     * @param string $fullyQualifiedName
     *
     * @return self
     *
     * @api
     */
    public static function make($fullyQualifiedName)
    {
        return new self($fullyQualifiedName);
    }

    /**
     * {@inheritDoc}
     */
    public function getFullyQualifiedName()
    {
        return $this->fullyQualifiedName->getFullyQualifiedName();
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->fullyQualifiedName->getName();
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespace()
    {
        return $this->fullyQualifiedName->getNamespace();
    }

    /**
     * {@inheritDoc}
     * @return self
     */
    public function setPhpdoc(StructurePhpdoc $structurePhpdoc)
    {
        $this->structurePhpdoc = $structurePhpdoc;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPhpdoc()
    {
        return $this->structurePhpdoc;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makeAbstract()
    {
        $this->isAbstract = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAbstract()
    {
        return $this->isAbstract;
    }

    /**
     * @return self
     *
     * @api
     */
    public function removeAbstract()
    {
        $this->isAbstract = false;

        return $this;
    }

    /**
     * @return self
     *
     * @api
     */
    public function makeFinal()
    {
        $this->isFinal = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isFinal()
    {
        return $this->isFinal;
    }

    /**
     * @return self
     *
     * @api
     */
    public function removeFinal()
    {
        $this->isFinal = false;

        return $this;
    }

    /**
     * @param Object $parent
     *
     * @return self
     *
     * @api
     */
    public function extend(Object $parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return (null !== $this->parent);
    }

    /**
     * @return Object
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return self
     *
     * @api
     */
    public function removeParent()
    {
        $this->parent = null;

        return $this;
    }

    /**
     * @param Contract $contract
     *
     * @return self
     *
     * @api
     */
    public function implement(Contract $contract)
    {
        $this->contracts[] = $contract;

        return $this;
    }

    /**
     * @return Contract[]
     */
    public function allContracts()
    {
        return $this->contracts;
    }

    /**
     * @param Constant $constant
     *
     * @return self
     *
     * @api
     */
    public function addConstant(Constant $constant)
    {
        $this->constants[] = $constant;

        return $this;
    }

    /**
     * @return Constant[]
     */
    public function allConstants()
    {
        return $this->constants;
    }

    /**
     * @param Property $property
     *
     * @return self
     *
     * @api
     */
    public function addProperty(Property $property)
    {
        $this->properties[] = $property;

        return $this;
    }

    /**
     * @return Property[]
     */
    public function allProperties()
    {
        return $this->properties;
    }

    /**
     * @param Method $method
     *
     * @return self
     *
     * @api
     */
    public function addMethod(Method $method)
    {
        $this->methods[] = $method;

        return $this;
    }

    /**
     * @return Method[]
     */
    public function allMethods()
    {
        return $this->methods;
    }
}

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
 * A PHP Interface ("interface" is a reserved word and cannot be used as classname).
 *
 * @api
 */
class Contract implements Structure
{
    /**
     * @var string
     */
    private $fullyQualifiedName;

    /**
     * @var StructurePhpdoc
     */
    private $structurePhpdoc;

    /**
     * @var Contract[]
     */
    private $contracts = array();

    /**
     * @var Constant[]
     */
    private $constants = array();

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
     * @param Contract $contract
     *
     * @return self
     *
     * @api
     */
    public function extend(Contract $contract)
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

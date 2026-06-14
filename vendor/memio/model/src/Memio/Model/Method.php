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

use Memio\Model\Phpdoc\MethodPhpdoc;

/**
 * @api
 */
class Method
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var MethodPhpdoc
     */
    private $methodPhpdoc;

    /**
     * @var bool
     */
    private $isAbstract = false;

    /**
     * @var bool
     */
    private $isFinal = false;

    /**
     * @var string
     */
    private $visibility = 'public';

    /**
     * @var bool
     */
    private $isStatic = false;

    /**
     * @var Argument[]
     */
    private $arguments = array();

    /**
     * @var string
     */
    private $body = '';

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
     * @param MethodPhpdoc $methodPhpdoc
     *
     * @return self
     *
     * @api
     */
    public function setPhpdoc(MethodPhpdoc $methodPhpdoc)
    {
        $this->methodPhpdoc = $methodPhpdoc;

        return $this;
    }

    /**
     * @return MethodPhpdoc
     */
    public function getPhpdoc()
    {
        return $this->methodPhpdoc;
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
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
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
    public function removeVisibility()
    {
        $this->visibility = '';

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
     * @param Argument $argument
     *
     * @return self
     *
     * @api
     */
    public function addArgument(Argument $argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * @return Argument[]
     */
    public function allArguments()
    {
        return $this->arguments;
    }

    /**
     * @param string $body
     *
     * @return self
     *
     * @api
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }
}

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

/**
 * @api
 */
class MethodPhpdoc
{
    /**
     * @var ApiTag
     */
    private $apiTag;

    /**
     * @var DeprecationTag
     */
    private $deprecationTag;

    /**
     * @var ReturnTag
     */
    private $returnTag;

    /**
     * @var Description
     */
    private $description;

    /**
     * @var ParameterTag[]
     */
    private $parameterTags = array();

    /**
     * @var ThrowTag[]
     */
    private $throwTags = array();

    /**
     * @return self
     *
     * @api
     */
    public static function make()
    {
        return new self();
    }

    /**
     * @param ApiTag $apiTag
     *
     * @return self
     *
     * @api
     */
    public function setApiTag(ApiTag $apiTag)
    {
        $this->apiTag = $apiTag;

        return $this;
    }

    /**
     * @param ReturnTag $returnTag
     *
     * @return self
     *
     * @api
     */
    public function setReturnTag(ReturnTag $returnTag)
    {
        $this->returnTag = $returnTag;

        return $this;
    }

    /**
     * @param Description $description
     *
     * @return self
     *
     * @api
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param DeprecationTag $deprecationTag
     *
     * @return self
     *
     * @api
     */
    public function setDeprecationTag(DeprecationTag $deprecationTag)
    {
        $this->deprecationTag = $deprecationTag;

        return $this;
    }

    /**
     * @return ApiTag
     */
    public function getApiTag()
    {
        return $this->apiTag;
    }

    /**
     * @return ReturnTag
     */
    public function getReturnTag()
    {
        return $this->returnTag;
    }

    /**
     * @return Description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return DeprecationTag
     */
    public function getDeprecationTag()
    {
        return $this->deprecationTag;
    }

    /**
     * @param ParameterTag $parameterTag
     *
     * @return self
     *
     * @api
     */
    public function addParameterTag(ParameterTag $parameterTag)
    {
        $this->parameterTags[] = $parameterTag;

        return $this;
    }

    /**
     * @return ParameterTag[]
     */
    public function getParameterTags()
    {
        return $this->parameterTags;
    }

    /**
     * @param ThrowTag $throwTag
     *
     * @return self
     *
     * @api
     */
    public function addThrowTag(ThrowTag $throwTag)
    {
        $this->throwTags[] = $throwTag;

        return $this;
    }

    /**
     * @return ThrowTag[]
     */
    public function getThrowTags()
    {
        return $this->throwTags;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        $hasApiTag = (null !== $this->apiTag);
        $hasDescription = (null !== $this->description);
        $hasDeprecationTag = (null !== $this->deprecationTag);
        $hasReturnTag = (null !== $this->returnTag);

        return !$hasApiTag && !$hasDescription && !$hasDeprecationTag && !$hasReturnTag && empty($this->parameterTags) && empty($this->throwTags);
    }
}

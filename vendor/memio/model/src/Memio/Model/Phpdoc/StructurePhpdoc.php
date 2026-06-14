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
class StructurePhpdoc
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
     * @var Description
     */
    private $description;

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
     * @return bool
     */
    public function isEmpty()
    {
        $hasApiTag = (null !== $this->apiTag);
        $hasDescription = (null !== $this->description);
        $hasDeprecationTag = (null !== $this->deprecationTag);

        return !$hasApiTag && !$hasDescription && !$hasDeprecationTag;
    }
}

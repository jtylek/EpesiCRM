<?php

/*
 * This file is part of the memio/model package.
 *
 * (c) Loïc Chardonnet <loic.chardonnet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Memio\Model;

use Memio\Model\Phpdoc\PropertyPhpdoc;
use PhpSpec\ObjectBehavior;

class PropertySpec extends ObjectBehavior
{
    const NAME = 'dateTime';

    function let()
    {
        $this->beConstructedWith(self::NAME);
    }

    function it_has_a_name()
    {
        $this->getName()->shouldBe(self::NAME);
    }

    function it_can_have_phpdoc(PropertyPhpdoc $phpdoc)
    {
        $this->getPhpdoc()->shouldBe(null);
        $this->setPhpdoc($phpdoc);
        $this->getPhpdoc()->shouldBe($phpdoc);
    }

    function it_has_visibility()
    {
        $this->getVisibility()->shouldBe('private');

        $this->makePublic();
        $this->getVisibility()->shouldBe('public');

        $this->makeProtected();
        $this->getVisibility()->shouldBe('protected');

        $this->makePrivate();
        $this->getVisibility()->shouldBe('private');
    }

    function it_can_have_staticness()
    {
        $this->isStatic()->shouldBe(false);

        $this->makeStatic();
        $this->isStatic()->shouldBe(true);

        $this->removeStatic();
        $this->isStatic()->shouldBe(false);
    }

    function it_can_have_a_default_value()
    {
        $this->getDefaultValue()->shouldBe(null);
        $this->setDefaultValue('null');
        $this->getDefaultValue()->shouldBe('null');
    }
}

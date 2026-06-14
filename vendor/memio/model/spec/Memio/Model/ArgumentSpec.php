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

use PhpSpec\ObjectBehavior;

class ArgumentSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith('array', 'lines');
    }

    function it_has_a_type()
    {
        $this->getType()->shouldBe('array');
    }

    function it_has_a_name()
    {
        $this->getName()->shouldBe('lines');
    }

    function it_can_have_default_value()
    {
        $this->getDefaultValue()->shouldBe(null);

        $this->setDefaultValue('null');
        $this->getDefaultValue()->shouldBe('null');

        $this->removeDefaultValue();
        $this->getDefaultValue()->shouldBe(null);
    }
}

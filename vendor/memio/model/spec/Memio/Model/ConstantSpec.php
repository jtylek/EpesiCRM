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

class ConstantSpec extends ObjectBehavior
{
    const NAME = 'MY_CONSTANT';
    const VALUE = "'my string value'";

    function let()
    {
        $this->beConstructedWith(self::NAME, self::VALUE);
    }

    function it_has_a_name()
    {
        $this->getName()->shouldBe(self::NAME);
    }

    function it_has_a_value()
    {
        $this->getValue()->shouldBe(self::VALUE);
    }
}

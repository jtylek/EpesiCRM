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

use Memio\Model\Argument;
use Memio\Model\Phpdoc\MethodPhpdoc;
use PhpSpec\ObjectBehavior;

class MethodSpec extends ObjectBehavior
{
    const NAME = '__construct';

    function let()
    {
        $this->beConstructedWith(self::NAME);
    }

    function it_has_a_name()
    {
        $this->getName()->shouldBe(self::NAME);
    }

    function it_can_have_phpdoc(MethodPhpdoc $phpdoc)
    {
        $this->getPhpdoc()->shouldBe(null);
        $this->setPhpdoc($phpdoc);
        $this->getPhpdoc()->shouldBe($phpdoc);
    }

    function it_can_be_abstract()
    {
        $this->isAbstract()->shouldBe(false);

        $this->makeAbstract();
        $this->isAbstract()->shouldBe(true);

        $this->removeAbstract();
        $this->isAbstract()->shouldBe(false);
    }

    function it_can_be_final()
    {
        $this->isFinal()->shouldBe(false);

        $this->makeFinal();
        $this->isFinal()->shouldBe(true);

        $this->removeFinal();
        $this->isFinal()->shouldBe(false);
    }

    function it_can_have_visibility()
    {
        $this->getVisibility()->shouldBe('public');

        $this->makePrivate();
        $this->getVisibility()->shouldBe('private');

        $this->makeProtected();
        $this->getVisibility()->shouldBe('protected');

        $this->removeVisibility();
        $this->getVisibility()->shouldBe('');

        $this->makePublic();
        $this->getVisibility()->shouldBe('public');
    }

    function it_can_have_staticness()
    {
        $this->isStatic()->shouldBe(false);

        $this->makeStatic();
        $this->isStatic()->shouldBe(true);

        $this->removeStatic();
        $this->isStatic()->shouldBe(false);
    }

    function it_can_have_arguments(Argument $argument)
    {
        $this->allArguments()->shouldBe(array());
        $this->addArgument($argument);
        $this->allArguments()->shouldBe(array($argument));
    }

    function it_can_have_a_body()
    {
        $body =<<<'EOT'
        $length = strlen('Nobody expects the spanish inquisition');
EOT;
        $this->setBody($body);
        $this->getBody()->shouldBe($body);
    }
}

<?php

/*
 * This file is part of the memio/pretty-printer package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Memio\PrettyPrinter\CodeGenerator;

use Memio\Model\Argument;
use PhpSpec\ObjectBehavior;

class EmptyCollectionCodeGeneratorSpec extends ObjectBehavior
{
    function it_is_a_pretty_printer_strategy()
    {
        $this->shouldImplement('Memio\PrettyPrinter\CodeGenerator\CodeGenerator');
    }

    function it_supports_empty_arrays()
    {
        $this->supports(array(), array())->shouldBe(true);
    }

    function it_generates_an_empty_string()
    {
        $this->generateCode(array())->shouldBe('');
    }
}

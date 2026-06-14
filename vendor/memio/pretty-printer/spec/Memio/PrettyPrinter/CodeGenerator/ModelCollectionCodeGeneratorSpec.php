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
use Memio\PrettyPrinter\TemplateEngine;
use PhpSpec\ObjectBehavior;

class ModelCollectionCodeGeneratorSpec extends ObjectBehavior
{
    function let(TemplateEngine $templateEngine)
    {
        $this->beConstructedWith($templateEngine);
    }

    function it_is_a_pretty_printer_strategy()
    {
        $this->shouldImplement('Memio\PrettyPrinter\CodeGenerator\CodeGenerator');
    }

    function it_supports_array_of_models()
    {
        $argument = new Argument('string', 'filename');
        $arguments = array($argument);

        $this->supports($arguments, array())->shouldBe(true);
    }

    function it_generates_code_using_collection_templates(TemplateEngine $templateEngine)
    {
        $argument = new Argument('string', 'filename');
        $arguments = array($argument);

        $templateEngine->render('collection/argument_collection', array('argument_collection' => $arguments))->shouldBeCalled();

        $this->generateCode($arguments);
    }
}

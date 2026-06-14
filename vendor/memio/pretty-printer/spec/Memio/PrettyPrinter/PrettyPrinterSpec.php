<?php

/*
 * This file is part of the memio/pretty-printer package.
 *
 * (c) Loïc Faugeron <faugeron.loic@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Memio\PrettyPrinter;

use Memio\Model\Argument;
use Memio\Model\FullyQualifiedName;
use Memio\Model\Method;
use Memio\PrettyPrinter\TemplateEngine;
use PhpSpec\ObjectBehavior;

class PrettyPrinterSpec extends ObjectBehavior
{
    const TEMPLATE_PATH = '/tmp/templates';

    function let(TemplateEngine $templateEngine)
    {
        $this->beConstructedWith($templateEngine);
    }

    function it_allows_template_over_loading(TemplateEngine $templateEngine)
    {
        $templateEngine->addPath(self::TEMPLATE_PATH)->shouldBeCalled();

        $this->addTemplatePath(self::TEMPLATE_PATH);
    }

    function it_handles_one_worded_model_class_names(TemplateEngine $templateEngine)
    {
        $argument = new Argument('string', 'filename');
        $templateEngine->render('argument', array('argument' => $argument))->shouldBeCalled();

        $this->generateCode($argument);
    }

    function it_handles_many_worded_model_class_names(TemplateEngine $templateEngine)
    {
        $fullyQualifiedName = new FullyQualifiedName('Memio\PrettyPrinter\MyClass');
        $templateEngine->render('fully_qualified_name', array('fully_qualified_name' => $fullyQualifiedName))->shouldBeCalled();

        $this->generateCode($fullyQualifiedName);
    }

    function it_passes_extra_parameters_to_template(TemplateEngine $templateEngine)
    {
        $argument = new Argument('int', 'total');
        $templateEngine->render('argument', array('extra' => 'parameter', 'argument' => $argument))->shouldBeCalled();

        $this->generateCode($argument, array('extra' => 'parameter'));
    }

    function it_handles_collections(TemplateEngine $templateEngine)
    {
        $collection = array(new Argument('bool', 'isObject'));
        $templateEngine->render('collection/argument_collection', array('argument_collection' => $collection))->shouldBeCalled();

        $this->generateCode($collection);
    }

    function it_handles_empty_collections()
    {
        $this->generateCode(array())->shouldBe('');
    }

    function it_throws_exception_when_no_strategy_support_the_given_arguments()
    {
        $invalidArgumentException = 'Memio\PrettyPrinter\Exception\InvalidArgumentException';

        $this->shouldThrow($invalidArgumentException)->duringGenerateCode('nope');
    }
}

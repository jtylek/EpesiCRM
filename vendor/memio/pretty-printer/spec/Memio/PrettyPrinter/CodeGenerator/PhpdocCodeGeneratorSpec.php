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

use Memio\Model\Phpdoc\LicensePhpdoc;
use Memio\PrettyPrinter\TemplateEngine;
use PhpSpec\ObjectBehavior;

class PhpdocCodeGeneratorSpec extends ObjectBehavior
{
    function let(TemplateEngine $templateEngine)
    {
        $this->beConstructedWith($templateEngine);
    }

    function it_is_a_pretty_printer_strategy()
    {
        $this->shouldImplement('Memio\PrettyPrinter\CodeGenerator\CodeGenerator');
    }

    function it_supports_phpdocs()
    {
        $licensePhpdoc = new LicensePhpdoc('Memio', 'author','author@example.com');

        $this->supports($licensePhpdoc, array())->shouldBe(true);
    }

    function it_generates_code_using_phpdoc_templates(TemplateEngine $templateEngine)
    {
        $licensePhpdoc = new LicensePhpdoc('Memio', 'author','author@example.com');

        $templateEngine->render('phpdoc/license_phpdoc', array('license_phpdoc' => $licensePhpdoc))->shouldBeCalled();

        $this->generateCode($licensePhpdoc);
    }
}

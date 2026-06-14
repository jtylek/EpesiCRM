# Memio's Models [![Travis CI](https://travis-ci.org/memio/model.png)](https://travis-ci.org/memio/model) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/79504ca3-5a36-44b5-93ef-eedd23a34c08/mini.png)](https://insight.sensiolabs.com/projects/79504ca3-5a36-44b5-93ef-eedd23a34c08)

Describe PHP code (classes/interfaces with their constants, properties, methods,
method arguments and even PHPdoc) by constructing "Model" objects.

> **Note**: This package is part of [Memio](http://memio.github.io/memio), a highly opinionated PHP code generator.
> Have a look at [the main repository](http://github.com/memio/memio).

## Installation

Install it using [Composer](https://getcomposer.org/download):

    composer require memio/model:^1.0

## Example

Let's say we want to describe the following method:

```php
    /**
     * @param ValueObject $valueObject
     * @param int         $type
     * @param boolean     $option
     *
     * @api
     */
    public function doSomething(ValueObject $valueObject, $type = self::TYPE_ONE, $option = true);
```

In order to do so, we'd need to write the following:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Memio\Model\Argument;
use Memio\Model\Method;
use Memio\Model\Phpdoc\ApiTag;
use Memio\Model\Phpdoc\MethodPhpdoc;
use Memio\Model\Phpdoc\ParameterTag;

$method = Method::make('doSomething')
    ->setPhpdoc(MethodPhpdoc::make()
        ->addParameterTag(new ParameterTag('Vendor\Project\ValueObject', 'valueObject'))
        ->addParameterTag(new ParameterTag('int', 'type'))
        ->addParameterTag(new ParameterTag('bool', 'option'))

        ->addApiTag(new ApiTag())
    )
    ->addArgument(new Argument('Vendor\Project\ValueObject', 'valueObject'))
    ->addArgument(Argument::make('int', 'type')
        ->setDefaultValue('self::TYPE_ONE')
    )
    ->addArgument(Argument::make('bool', 'option')
        ->setDefaultValue('true')
    )
;
```

Usually models aren't described manually like this, they would be built dynamically:

```php
// Let's say we've received the following two parameters:
$methodName = 'doSomething';
$arguments = array(new \Vendor\Project\ValueObject(), ValueObject::TYPE_ONE, true);

$method = new Method($methodName);
$phpdoc = MethodPhpdoc::make()->setApiTag(new ApiTag());
$index = 1;
foreach ($arguments as $rawArgument) {
    $type = is_object($rawArgument) ? get_class($argument) : gettype($rawArgument);
    $name = 'argument'.$index++;
    $argument = new Argument($type, $name);

    $method->addArgument($argument);
    $phpdoc->addParameterTag(new ParameterTag($type, $name));
}
$method->setPhpdoc($phpdoc);
```

We can build dynamically the models using a configuration file, user input, existing
source code... Possibilities are infinite!

Once built these models can be further tweaked, and converted to another format:
an array, source code, etc... Again, the possibilities are infinite!

Have a look at [the main respository](http://github.com/memio/memio) to discover the full power of Medio.

## Want to know more?

Memio uses [phpspec](http://phpspec.net/), which means the tests also provide the documentation.
Not convinced? Then clone this repository and run the following commands:

    composer install
    ./vendor/bin/phpspec run -n -f pretty

You can see the current and past versions using one of the following:

* the `git tag` command
* the [releases page on Github](https://github.com/memio/memio/releases)
* the file listing the [changes between versions](CHANGELOG.md)

And finally some meta documentation:

* [copyright and MIT license](LICENSE)
* [versioning and branching models](VERSIONING.md)
* [contribution instructions](CONTRIBUTING.md)

## Roadmap

* get rid of `Type`
* extract `Import` (use statement) from `FullyQualifiedName`
* get rid of `FullyQualifiedName`
* support more PHPdoc stuff
* support annotations

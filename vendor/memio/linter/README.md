# Memio's Linter [![Travis CI](https://travis-ci.org/memio/linter.png)](https://travis-ci.org/memio/linter) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/47d30f7a-5ee9-43e3-9ff8-b8a139ed5999/mini.png)](https://insight.sensiolabs.com/projects/47d30f7a-5ee9-43e3-9ff8-b8a139ed5999)

A set of [Memio constraints](http://github.com/memio/validator) that check
[Memio models](http://github.com/memio/model) for syntax errors and the likes.

> **Note**: This package is part of [Memio](http://memio.github.io/memio), a highly opinionated PHP code generator.
> Have a look at [the main repository](http://github.com/memio/memio).

## Installation

Install it using [Composer](https://getcomposer.org/download):

    composer require memio/linter:^1.0

## Example

Usually we'd have to install [Memio](http://github.com/memio/memio) and use its
`Build::linter()` method to get a linter validator.

The atlernative would be to build manually the validator as follow:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Memio\Validator\Constraint\ArgumentCannotBeScalar;
use Memio\Validator\Constraint\CollectionCannotHaveNameDuplicates;
use Memio\Validator\Constraint\ConcreteObjectMethodsCannotBeAbstract;
use Memio\Validator\Constraint\ContractMethodsCanOnlyBePublic;
use Memio\Validator\Constraint\ContractMethodsCannotBeFinal;
use Memio\Validator\Constraint\ContractMethodsCannotBeStatic;
use Memio\Validator\Constraint\ContractMethodsCannotHaveBody;
use Memio\Validator\Constraint\MethodCannotBeAbstractAndHaveBody;
use Memio\Validator\Constraint\MethodCannotBeBothAbstractAndFinal;
use Memio\Validator\Constraint\MethodCannotBeBothAbstractAndPrivate;
use Memio\Validator\Constraint\MethodCannotBeBothAbstractAndStatic;
use Memio\Validator\Constraint\ObjectArgumentCanOnlyDefaultToNull;
use Memio\Validator\ModelValidator\ArgumentValidator;
use Memio\Validator\ModelValidator\CollectionValidator;
use Memio\Validator\ModelValidator\MethodValidator;
use Memio\Validator\ModelValidator\ContractValidator;
use Memio\Validator\ModelValidator\ObjectValidator;
use Memio\Validator\ModelValidator\FileValidator;
use Memio\Validator\Validator;

$argumentValidator = new ArgumentValidator();
$argumentValidator->add(new ArgumentCannotBeScalar());

$collectionValidator = new CollectionValidator();
$collectionValidator->add(new CollectionCannotHaveNameDuplicates());

$methodValidator = new MethodValidator($argumentValidator, $collectionValidator);
$methodValidator->add(new MethodCannotBeAbstractAndHaveBody());
$methodValidator->add(new MethodCannotBeBothAbstractAndFinal());
$methodValidator->add(new MethodCannotBeBothAbstractAndPrivate());
$methodValidator->add(new MethodCannotBeBothAbstractAndStatic());

$contractValidator = new ContractValidator($collectionValidator, $methodValidator);
$contractValidator->add(new ContractMethodsCanOnlyBePublic());
$contractValidator->add(new ContractMethodsCannotBeFinal());
$contractValidator->add(new ContractMethodsCannotBeStatic());
$contractValidator->add(new ContractMethodsCannotHaveBody());

$objectValidator = new ObjectValidator($collectionValidator, $methodValidator);
$objectValidator->add(new ConcreteObjectMethodsCannotBeAbstract());
$objectValidator->add(new ObjectArgumentCanOnlyDefaultToNull());

$fileValidator = new FileValidator($contractValidator, $objectValidator);

$linter = new Validator();
$linter->add($argumentValidator);
$linter->add($collectionValidator);
$linter->add($methodValidator);
$linter->add($contractValidator);
$linter->add($objectValidator);
$linter->add($fileValidator);

$linter->validator($anyModels);
```

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

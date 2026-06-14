# Memio's Validator [![Travis CI](https://travis-ci.org/memio/validator.png)](https://travis-ci.org/memio/validator) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/e5794d5b-5305-4569-bc9b-caeecf9ae982/mini.png)](https://insight.sensiolabs.com/projects/e5794d5b-5305-4569-bc9b-caeecf9ae982)

A validator library for Memio: allows to define `Constraints` to check if the built
[Memio models](http://github.com/memio/model) are valid
(e.g. `Method` cannot be both abstract and final).

> **Note**: This package is part of [Memio](http://memio.github.io/memio), a highly opinionated PHP code generator.
> Have a look at [the main repository](http://github.com/memio/memio).

## Installation

Install it using [Composer](https://getcomposer.org/download):

    composer require memio/validator:^1.0

## Example

Let's say we want to check that `Arguments` aren't scalar. In order to do so,
the first thing we'll need to do is to write a `Constraint`:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Memio\Validator\Constraint;
use Memio\Validator\Violation\NoneViolation;
use Memio\Validator\Violation\SomeViolation;

class ArgumentCannotBeScalar implements Constraint
{
    public function validate($model)
    {
        $type = $model->getType();
        if (in_array($type, array('array', 'bool', 'callable', 'double', 'int', 'mixed', 'null', 'resource', 'string'), true)) {
            return new SomeViolation(sprintf('Argument "%s" cannot be scalar', $model->getName()));
        }

        return new NoneViolation();
    }
}
```

> **Note**: In Memio, all `Constraints` are named after their error message.
> This isn't a hard coded rule, they can have any name.

We then need to register our rule in an `ArgumentValidator`:

```php
// ...

$argumentValidator = new ArgumentValidator();
$argumentValidator->add(new ArgumentCannotBeScalar());
```

`ArgumentValidator` is a `ModelValidator` called by `Validator` if the given model
is an `Argument`. However, if the given model is a `Method` we'd like `Validator`
to check our `Constraint` against its `Arguments`. To do so, we need to assemble
`ModelValidators` as follow:

```php
// ...
//
$collectionValidator = new CollectionValidator();
$methodValidator = new MethodValidator($argumentValidator, $collectionValidator);
$contractValidator = new ContractValidator($collectionValidator, $methodValidator);
$objectValidator = new ObjectValidator($collectionValidator, $methodValidator);
$fileValidator = new FileValidator($contractValidator, $objectValidator);
```

Finally, we need to create a validator and register our `ModelValidators` in it:

```php
// ...
//
$myValidator = new Validator();
$myValidator->add($argumentValidator);
$myValidator->add($collectionValidator);
$myValidator->add($methodValidator);
$myValidator->add($contractValidator);
$myValidator->add($objectValidator);
$myValidator->add($fileValidator);
```

This way we can build specialized validators: one that'd check syntax errors, one that'd
check business rules, etc... Possibilities are endless!

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

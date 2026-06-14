# Memio's PrettyPrinter [![SensioLabsInsight](https://insight.sensiolabs.com/projects/c8194cd1-0f80-4bce-9ab2-8368db5411b3/mini.png)](https://insight.sensiolabs.com/projects/c8194cd1-0f80-4bce-9ab2-8368db5411b3) [![Travis CI](https://travis-ci.org/memio/pretty-printer.png)](https://travis-ci.org/memio/pretty-printer)

`PrettyPrinter` is a code generator (printer) that takes a Model and calls the
appropriate `TemplateEngine` to actually generate the corresponding code,
using highly opinionated coding standards (pretty).

`PrettyPrinter` returns a string that can be saved in a file, displayed on a
console output or displayed in a web page. Possibilities are endless!

> **Note**: This package is part of [Memio](http://memio.github.io/memio).
> Have a look at [the main repository](http://github.com/memio/memio).

## Installation

Install it using [Composer](https://getcomposer.org/download):

    composer require memio/pretty-printer:~1.0

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

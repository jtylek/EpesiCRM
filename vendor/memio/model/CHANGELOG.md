# CHANGELOG


## 1.4.1: Cancelled FQCN

* reverted the FQCN change

## 1.4.0: Improved FQCN

* improve `FullyQualifiedName` according to PHP Name resolution rules
* added support for PHP 7

## 1.3.5: Improved PHPdoc

* used `@return self`
* used `Object[]` when dealing with collections of objects

## 1.3.4: Fixed PHPdoc

* fixed PHPdoc

## 1.3.3: Fixed dependencies

* removed SpecGen to fix dependency cycles
* fixed CS

## 1.3.2: ParameterTag::make()

* fixed `ParameterTag#make`

## 1.3.1: SpecGen

* replaced phpspec extension with SpecGen

## 1.3.0: Argument type FQCN

* changed Argument.type to  be a Fully Qualified Class Name

## 1.2.0: @return and @throws PHPdoc tags

* added `return` PHPdoc tag
* added `throws` PHPdoc tag

## 1.1.0: Type name normalization

* integer, boolean and NULL will be normalized to int, bool and null

## 1.0.1: Small doc fixes

* fixed typos in documentation

## 1.0.0: Import

* imported models from [memio/memio](http://github.com/memio/memio) v1.0.0-rc7

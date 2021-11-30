CHANGELOG
---------

### v3.6.2, 2021.04.09

- Fixed string interpolation

### v3.6.1, 2020.11.07

- Fixed serialization error [#84](https://github.com/opis/closure/issues/84)

### v3.6.0, 2020.10.12

- Initial PHP 8 Support [#67](https://github.com/opis/closure/issues/67).

### v3.5.7, 2020.09.06

- Fixed issue [#76](https://github.com/opis/closure/issues/76).
- Fixed issue [#78](https://github.com/opis/closure/issues/78).

### v3.5.6, 2020.08.11

- Fixed issue [#70](https://github.com/opis/closure/issues/70)

### v3.5.5, 2020.06.17

- Fixed a false-positive when using `Opis\Closure\ReflectionClosure::isScopeRequired` method

### v3.5.4, 2020.06.07

- Fixed a false-positive when using `Opis\Closure\ReflectionClosure::isScopeRequired` method
- Fixed a bug related to `T_STRING_VARNAME`

### v3.5.3, 2020.05.25

- Improved parser
- The class scope optimisation is no longer used. We always bind now to the closure's original class scope.
When the class scope was `null`, the optimisation failed to work as expected and kept the wrong `SerializableClosure` scope.

### v3.5.2, 2020.05.21

- Removed extra semicolon in short closures, since is not part of the closure's body.

### v3.5.1, 2019.11.30

- Bugfix. See #47

### v3.5.0, 2019.11.29

- Added support for short closures (arrow functions)
- Added `isShortClosure` method to `Opis\Closure\ReflectionClosure`

### v3.4.2, 2019.11.29

- Added `stream_set_option()`

### v3.4.1, 2019.10.19

- Fixed a [bug](https://github.com/opis/closure/issues/40) that prevented serialization to work correctly.

### v3.4.0, 2019.09.03

- Added `createClosure` static method in `Opis\Closure\SerializableClosure`.
This method creates a new closure from arbitrary code, emulating `create_function`,
but without using eval

### v3.3.1, 2019.07.10

- Use `sha1` instead of `md5` for hashing file names in `Opis\Closure\ReflectionClosure` class

### v3.3.0, 2019.05.31

- Fixed a bug that prevented signed closures to properly work when the serialized string
contains invalid UTF-8 chars. Starting with this version `json_encode` is no longer used
when signing a closure. Backward compatibility is maintained and all closures that were 
previously signed using the old method will continue to work.

### v3.2.0, 2019.05.05

- Since an unsigned closure can be unserialized when no security provider is set, 
there is no reason to treat differently a signed closure in the same situation.
Therefore, the `Opis\Closure\SecurityException` exception  is no longer thrown when 
unserializing a signed closure, if no security provider is set.

### v3.1.6, 2019.02.22

- Fixed a bug that occurred when trying to set properties of classes that were not defined in user-land.
Those properties are now ignored.

### v3.1.5, 2019.01.14

- Improved parser

### v3.1.4, 2019.01.14

- Added support for static methods that are named using PHP keywords or magic constants.
Ex: `A::new()`, `A::use()`, `A::if()`, `A::function()`, `A::__DIR__()`, etc.
- Used `@internal` to mark classes & methods that are for internal use only and
backward compatibility is not guaranteed.

### v3.1.3, 2019.01.07

- Fixed a bug that prevented traits to be correctly resolved when used by an
anonymous class
- Fixed a bug that occurred when `$this` keyword was used inside an anonymous class

### v3.1.2, 2018.12.16

* Fixed a bug regarding comma trail in group-use statements. See [issue 23](https://github.com/opis/closure/issues/23)

### v3.1.1, 2018.10.02

* Fixed a bug where `parent` keyword was treated like a class-name and scope was not added to the
serialized closure
* Fixed a bug where return type was not properly handled for nested closures
* Support for anonymous classes was improved

### v3.1.0, 2018.09.20

* Added `transformUseVariables` and `resolveUseVariables` to
`Opis\Closure\SerializableClosure` class.
* Added `removeSecurityProvider` static method to 
`Opis\Closure\SerializableClosure` class. 
* Fixed some security related issues where a user was able to unserialize an unsigned
closure, even when a security provider was in use.

### v3.0.12, 2018.02.23

* Bugfix. See [issue 20](https://github.com/opis/closure/issues/20)

### v3.0.11, 2018.01.22

* Bugfix. See [issue 18](https://github.com/opis/closure/issues/18)

### v3.0.10, 2018.01.04

* Improved support for PHP 7.1 & 7.2

### v3.0.9, 2018.01.04

* Fixed a bug where the return type was not properly resolved. 
See [issue 17](https://github.com/opis/closure/issues/17)
* Added more tests

### v3.0.8, 2017.12.18

* Fixed a bug. See [issue 16](https://github.com/opis/closure/issues/16)

### v3.0.7, 2017.10.31

* Bugfix: static properties are ignored now, since they are not serializable

### v3.0.6, 2017.10.06

* Fixed a bug introduced by accident in 3.0.5

### v3.0.5, 2017.09.18

* Fixed a bug related to nested references

### v3.0.4, 2017.09.18

* \[*internal*\] Refactored `SerializableClosure::mapPointers` method
* \[*internal*\] Added a new optional argument to `SerializableClosure::unwrapClosures`
* \[*internal*\] Removed `SerializableClosure::getClosurePointer` method
* Fixed various bugs

### v3.0.3, 2017.09.06

* Fixed a bug related to nested object references 
* \[*internal*\] `Opis\Closure\ClosureScope` now extends `SplObjectStorage`
* \[*internal*\] The `storage` property was removed from `Opis\Closure\ClosureScope`
* \[*internal*\] The `instances` and `objects` properties were removed from `Opis\Closure\ClosureContext`

### v3.0.2, 2017.08.28

* Fixed a bug where `$this` object was not handled properly inside the 
`SerializableClosre::serialize` method. 

### v3.0.1, 2017.04.13

* Fixed a bug in 'ignore_next' state

### v3.0.0, 2017.04.07

* Dropped PHP 5.3 support
* Moved source files from `lib` to `src` folder
* Removed second parameter from `Opis\Closure\SerializableClosure::from` method and from constructor
* Removed `Opis\Closure\{SecurityProviderInterface, DefaultSecurityProvider, SecureClosure}` classes
* Refactored how signed closures were handled
* Added `wrapClosures` and `unwrapClosures` static methods to `Opis\Closure\SerializableClosure` class
* Added `Opis\Colosure\serialize` and `Opis\Closure\unserialize` functions
* Improved serialization. You can now serialize arbitrary objects and the library will automatically wrap all closures

### v2.4.0, 2016.12.16

* The parser was refactored and improved
* Refactored `Opis\Closure\SerializableClosure::__invoke` method
* `Opis\Closure\{ISecurityProvider, SecurityProvider}` were added
* `Opis\Closure\{SecurityProviderInterface, DefaultSecurityProvider, SecureClosure}` were deprecated
and they will be removed in the next major version
* `setSecretKey` and `addSecurityProvider` static methods were added to `Opis\Closure\SerializableClosure`

### v2.3.2, 2016.12.15

* Fixed a bug that prevented namespace resolution to be done properly

### v2.3.1, 2016.12.13

* Hotfix. See [PR](https://github.com/opis/closure/pull/7)

### v2.3.0, 2016.11.17

* Added `isBindingRequired` and `isScopeRequired` to the `Opis\Closure\ReflectionClosure` class
* Automatically detects when the scope and/or the bound object of a closure needs to be serialized.

### v2.2.1, 2016.08.20

* Fixed a bug in `Opis\Closure\ReflectionClosure::fetchItems`

### v2.2.0, 2016.07.26

* Fixed CS
* `Opis\Closure\ClosureContext`, `Opis\Closure\ClosureScope`, `Opis\Closure\SelfReference`
 and `Opis\Closure\SecurityException` classes were moved into separate files
* Added support for PHP7 syntax
* Fixed some bugs in `Opis\Closure\ReflectionClosure` class
* Improved closure parser
* Added an analyzer for SuperClosure library

### v2.1.0, 2015.09.30

* Added support for the missing `__METHOD__`, `__FUNCTION__` and `__TRAIT__` magic constants
* Added some security related classes and interfaces: `Opis\Closure\SecurityProviderInterface`,
`Opis\Closure\DefaultSecurityProvider`, `Opis\Closure\SecureClosure`, `Opis\Closure\SecurityException`.
* Fiexed a bug in `Opis\Closure\ReflectionClosure::getClasses` method
* Other minor bugfixes
* Added support for static closures
* Added public `isStatic` method to `Opis\Closure\ReflectionClosure` class


### v2.0.1, 2015.09.23

* Removed `branch-alias` property from `composer.json`
* Bugfix. See [issue #6](https://github.com/opis/closure/issues/6)

### v2.0.0, 2015.07.31

* The closure parser was improved
* Class names are now automatically resolved
* Added support for the `#trackme` directive which allows tracking closure's residing source

### v1.3.0, 2014.10.18

* Added autoload file
* Changed README file

### Opis Closure 1.2.2

* Started changelog

# Release History

## 3.0.3 (2024-11-09)
* Make Hint a Stringable class for easier debugging.
* Improve InvalidArgumentException messages for easier debugging.

## 3.0.2 (2024-07-22)
* Add generic template types to the Hint object.
* Fix behavior of ALLOW_OBJECT hint for non-empty lists.

## 3.0.1 (2024-06-14)
* Refactor JsonCodec::codecFor() to be non-recursive.  This simplifies
  the implementation of extensions without worrying about recursive
  calls to the subclass method.
* Gracefully handle embedded stdClass objects passed to
  ::newFromJsonArray().

## 3.0.0 (2024-06-12)
* Add new class hint mechanism to generalize the method added in 2.1.0
  to augment hints.  Class hints include:
  * `Hint::LIST` to indicate an array of objects of the hinted class
  * `Hint::STDCLASS` to indicate an `stdClass` object whose properties are
    objects of the hinted class
  * `Hint::USE_SQUARE` and `Hint::ALLOW_OBJECT` to customize encoding
    of list-like objects.
  * `Hint::INHERITED` to allow a superclass codec to handle instantiation of
    its subclass objects.
* The signatures of any method accepting or returning a class hint have
  been broadened to include the new `Hint` type.  This is a breaking
  change for methods where a hint was an argument.

## 2.2.3 (2024-06-03)
* Ensure class aliases are resolved when looking up type hints and
  class codecs (T353883).
* Update dev dependencies.

## 2.2.2 (2023-11-17)
* Allow psr/container ^2.0.2 (as well as ^1.1.2)
* Export JsonCodec::TYPE_ANNOTATION to subclasses who might wish to
  reassign where the type information is put.

## 2.2.1 (2023-10-03)
* Allow symfony/polyfill-php81 ^1.27.0.

## 2.2.0 (2023-10-03)
* Add additional protected methods to JsonCodec to allow subclasses to
  further customize the encoding used.
* Allow objects to have numeric properties.
* Ensure objects with numeric properties are consistently encoded using
  the '{...}' JSON syntax.

## 2.1.0 (2023-10-02)
* Allow ::jsonClassHintFor() to return a class-string suffixed with
  `[]` to indicate a list or homogeneous array of the given type.

## 2.0.0 (2023-10-02)
* JsonCodec::addCodecFor() is added to provide the ability to
  serialize/deserialize objects which don't implement JsonCodecable.

## 1.0.0

* Initial release.

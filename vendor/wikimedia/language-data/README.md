# Language data and utilities

[![npm][npm]][npm-url]
[![node-build][node-build]][node-build-url]
[![php-build][php-build]][php-build-url]

This library contains language related data, and utility libraries written in PHP and Node.js to
interact with that data.

The language related data comprises of the following,

1. The script in which a language is written
2. The script code
3. The language code
4. The regions in which the language is spoken
5. The autonym - language name written in its own script
6. The directionality of the text

This data is populated from the current version of
[CLDR supplemental data](http://unicode.org/repos/cldr/trunk/common/supplemental/supplementalData.xml)
and various other sources.

## Documentation

1. [Full documentation](https://language-data.readthedocs.io/en/latest/index.html)
2. [Using the PHP library](https://language-data.readthedocs.io/en/latest/index.html#using-the-php-library)
   * [PHP API documentation](https://language-data.readthedocs.io/en/latest/api/languagedata/languageutil.html)
3. [Using the Node.js library](https://language-data.readthedocs.io/en/latest/index.html#using-the-node-js-library)
4. [Adding Languages](https://language-data.readthedocs.io/en/latest/user/adding_new_language.html)

[npm]: https://img.shields.io/npm/v/@wikimedia/language-data.svg
[npm-url]: https://npmjs.com/package/@wikimedia/language-data
[node-build]: https://github.com/wikimedia/language-data/workflows/Node.js%20build/badge.svg
[node-build-url]: https://github.com/wikimedia/language-data/actions?query=workflow%3A%22Node.js+build%22
[php-build]: https://github.com/wikimedia/language-data/workflows/PHP%20build/badge.svg
[php-build-url]: https://github.com/wikimedia/language-data/actions?query=workflow%3A%22PHP+build%22

## Release schedule
Similar to [MLEB](https://www.mediawiki.org/wiki/MediaWiki_Language_Extension_Bundle), this library
will have a quarterly release schedule, and will be released along with MLEB. Intermediate releases
will be made for important bug fixes.

## Changelog
The full changelog is available in [CHANGELOG.md](https://github.com/wikimedia/language-data/blob/master/CHANGELOG.md).

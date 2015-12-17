MediaWiki-Vendor
================

[Composer] managed libraries required or recommended for use with [MediaWiki].
This repository is maintained for use on the Wikimedia Foundation production
and testing clusters, but may be useful for anyone wishing to avoid directly
managing MediaWiki dependencies with Composer.


Usage
-----

Checkout this library into $IP/vendor using `git clone <URL>` or add the
repository as a git submodule using `git submodule add <URL> vendor` followed
by `git submodule update --init`.


Adding or updating libraries
----------------------------

0. Read the [documentation] on the process for adding new libraries.
1. Edit the composer.json file
2. Run `composer update` to download files and update the autoloader files.
3. Add and commit changes as a gerrit patch.
4. Review and merge changes.


[Composer]: https://getcomposer.org/
[MediaWiki]: https://www.mediawiki.org/wiki/MediaWiki
[documentation]: https://www.mediawiki.org/wiki/Manual:External_libraries

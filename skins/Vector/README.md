Vector Skin
========================

Installation
------------

See <https://www.mediawiki.org/wiki/Skin:Vector>.

### Configuration options

See [skin.json](skin.json).

Development
-----------

### Node version

It is recommended to use [nvm](https://github.com/nvm-sh/nvm) to use the version of node defined
in `.nvmrc` during local development. This ensures consistency amongst development environments.

### Coding conventions

We strive for compliance with MediaWiki conventions:

<https://www.mediawiki.org/wiki/Manual:Coding_conventions>

Additions and deviations from those conventions that are more tailored to this
project are noted at:

<https://www.mediawiki.org/wiki/Reading/Web/Coding_conventions>

URL query parameters
--------------------

- `useskinversion`: Like `useskin` but for overriding the Vector skin version
  user preference and configuration. E.g.,
  http://localhost:8181?useskin=vector&useskinversion=2.

Skin preferences
----------------

Vector defines skin-specific user preferences. These are exposed on
Special:Preferences when the `VectorShowSkinPreferences` configuration is
enabled. The user's preference state for skin preferences is used for skin
previews and any other operation unless specified otherwise.

### Version

Vector defines a "version" preference to enable users who prefer the December
2019 version of Vector to continue to do so without any visible changes. This
version is called "Legacy Vector." The related preference defaults are
configurable via the configurations prefixed with `VectorDefaultSkinVersion`.
Version preference and configuration may be overridden by the `useskinversion`
URL query parameter.

### Pre-commit tests

A pre-commit hook is installed when executing `npm install`. By default, it runs
`npm test` which is useful for automatically validating everything that can be
in a reasonable amount of time. If you wish to defer these tests to be executed
by continuous integration only, set the `PRE_COMMIT` environment variable to `0`:

```bash
$ export PRE_COMMIT=0
$ git commit
```

Or more succinctly:

```bash
$ PRE_COMMIT=0 git commit
```

Skipping the pre-commit tests has no impact on Gerrit change identifier hooks.

### Hooks
See [hooks.txt](hooks.txt).

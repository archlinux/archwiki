# Selenium tests

## Getting started

See <https://www.mediawiki.org/wiki/Selenium> for how to best
run these locally. Below the internal prerequisites are documented,
but you might not need to install these yourself.

## Prerequisites

- [Chromium](https://www.chromium.org/Home) or [Chrome](https://www.google.com/chrome)
- [Node.js](https://nodejs.org)

## Usage

There are three supported modes of running the tests.

#### Headless

The Selenium tests default to browser headless mode in CI (passing --headless to the browser). In wdio.conf.js
that is configured by `useBrowserHeadless: true`. To run headless on your local machine, you need pass that parameter.

Run the test: `npm run selenium-test --useBrowserHeadless`

### Visible browser

By default you will see the browser window on your local machine.

Run the test: `npm run selenium-test`


### Video recording

To capture a video, the tests have to run in the context of an X11 server. The wdio-mediawiki package
start and stop XVFB automatically but since there's a bug in wdio you still need to export a DISPLAY-
Recording videos is currently supported only on Linux, and is configured by the `recordVideo`setting. To
record a video you need to have `recordVideo: true`.

Example test run in [Fresh](https://gerrit.wikimedia.org/g/fresh).

    fresh-node -env -net
    export DISPLAY=:100
    npm run selenium-test -- --recordVideo

## Filter

Run a specific spec:

    npm run selenium-test -- --spec tests/selenium/specs/page.js

To filter by test case, e.g. with the name containing "preferences":

    npm run selenium-test -- --mochaOpts.grep preferences

## Configuration

The following environment variables decide where to find MediaWiki and how to login:

- `MW_SERVER`: The value of `$wgServer`.
- `MW_SCRIPT_PATH`: The value of `$wgScriptPath`.
- `MEDIAWIKI_USER`: Username of a wiki account with sysop rights.
- `MEDIAWIKI_PASSWORD`: Password for this user.

## Further reading

- [Selenium](https://www.mediawiki.org/wiki/Selenium) on mediawiki.org

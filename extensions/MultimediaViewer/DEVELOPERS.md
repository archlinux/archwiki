# Developer tooling

CI will test your patches for code style and quality issues.

You can run this locally with `npm lint`, or `grunt lint` if you have `grunt` installed globally.

If you wish, you can have the linting code try to auto-fix trivial style errors by passing the `fix` option in: `grunt lint --fix`.

For an extensive series of fixes in this area, you may wish to add shell aliases like `alias lintfix='grunt lint --fix' ; alias jsfix='grunt eslint --fix' ; alias cssfix='grunt stylelint --fix'` to your `~/.bashrc` file or its equivalent, which will add three shorter commands to fix everything, just JavaScript, and just style files respectively. You could also use shorted custom commands if you wish.

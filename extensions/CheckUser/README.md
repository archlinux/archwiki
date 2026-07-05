# CheckUser

CheckUser is an extension that allows privileged users to check which IP addresses, User-Agent strings,
and Client Hints are used by a given username and which usernames are used by a given IP.

For more information, see https://www.mediawiki.org/wiki/Extension:CheckUser

## Coding Conventions

### Line Length

**Hard limit: 120 characters** (enforced by the linter)

CheckUser's `.phpcs.xml` sets `absoluteLineLimit` to 120, making lines over 120 a hard
linter **error**. An advisory warning is also emitted for lines over 100 characters on
newly added lines (via `scripts/phpcs-diff.php`, which runs as part of `composer test`).

Practical rules for code review:
- Lines ≤ 120 chars — **fully compliant; do not leave review comments about line length**
- Lines > 120 chars — **linter will error; must be fixed before merging**

Shorter lines are always welcome, but reviewers must not request
changes solely based on line length as long as the linter passes.

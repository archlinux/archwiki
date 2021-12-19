# Release History

## 2.0.0 (2021-08-20)

* Require PHP >=7.2.9, up from 7.2.0.
* Add a "spec" action, which exposes a swagger spec for service-checker.
* Remove non-functional allowlist code (T277981).
* Documentation has been added to <https://www.mediawiki.org/wiki/Shellbox>.

## 1.0.4 (2021-02-26)

* Raise priority of limit.sh if it uses a cgroup (T274942).

## 1.0.3 (2021-02-10)

* Only allow path to limit.sh when command has other allowed paths (T274474).
* build: Exclude config folder from Composer package.

## 1.0.2 (2021-02-07)

* Don't pass through the fake CGI environment to subprocesses.

## 1.0.1 (2021-02-03)

* build: Exclude public_html folder from Composer package.

## 1.0.0 (2021-02-02)

* Initial release.

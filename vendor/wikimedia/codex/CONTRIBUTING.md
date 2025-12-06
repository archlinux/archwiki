# Maintainers guide

## Release process

### Preparation

- Ensure all necessary patches have been merged
- If necessary, copy any new Codex i18n messages over from MediaWiki and commit
  them here
- Make sure that you have no uncommited changes locally, and that you are on the
  latest version of the `main` branch.

### Release Commit

Check out a new branch (`tag-x.y.z` is a good naming convention) and update the
`CHANGELOG.md` file to reflect the new changes being included in this release.

To generate a simple list of changes since the last release, use a command like
this one:

```bash
git log --pretty=oneline v0.1.0..HEAD | sort | uniq
```

Related changes can be grouped under headings like `Added`, `Changed`,
`Deprecated`, etc.

Version numbers should follow [SemVer](https://semver.org/). Breaking and
deprecating changes should be clearly flagged as such in the changelog.

Commit this change and push the patch to Gerrit for review. The title should
be something like:

```
Tag: v1.2.3
```

### Publish the tag

Once the release commit has been merged, checkout the main branch and pull down
the latest changes. Then create a `vX.Y.Z` tag and push it.

```bash
git tag v1.2.3
git push --tags origin v1.2.3
```

Once the new tag has been published, the new version will become available in
Packagist (used by Composer) automatically.

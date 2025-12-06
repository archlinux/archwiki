# How Cite reports errors to users

Articles with syntax errors in `<ref>` or `<references>` tags show up in the tracking category "[Pages with reference errors](https://www.mediawiki.org/wiki/Category:Pages_with_reference_errors)" (internationalized category name given by [cite-tracking-category-cite-error](https://www.mediawiki.org/wiki/MediaWiki:cite-tracking-category-cite-error)). This is similar to how many other extensions behave.

In addition, error messages show up in the rendered HTML output as part of the article. While such a behavior is not entirely unique to the Cite extension it's not standardized in any way.

Generally, errors in the Cite syntax either suppress and replace the expected output with an error message (referred to as "fatal" below) or an error message gets inserted into the output (referred to as "warning" below).

## Invalid attributes

MediaWiki takes care of parsing extension tags like `<ref>` and forwards the content to the responsible extension as a raw string (possibly null for self-closing tags) along with a map of named attributes. Most other extensions ignore unknown attributes in this map. The way `<ref>` and `<references>` report unknown, unsupported attributes as invalid is unique to the Cite extension and not standardized in any way.

## Sanitization

- Whitespace around all attribute values is trimmed. This is done by core's sanitizer and not under control of the Cite extension.
- As above, whitespace sequences inside of attribute values are normalized to a single space by core's sanitizer. This is notable in e.g. `name="a  b"`.
- Empty attributes are ignored as if the attribute is not there. The only exception are attributes that are invalid in certain contexts (see "invalid attributes" above).
- When the `group="…"` attribute isn't present, the `<ref>` is assigned to the default group (internally represented as an empty string). List-defined `<ref>` inherit the group of the `<references>` tag in which they're defined.
- The capitalization of `dir="…"` arguments is ignored and normalized to be lowercase.
- The `responsive=""` attribute is reduced to a boolean and never reports an error. Only the string "0" means disabled, all other strings (including the empty string) mean enabled.

## Validation
### Fatal errors

Fatal errors can show up in two places:
- When a `<ref>` tag somewhere in the document triggers a fatal error, the normal rendering of the `<ref>` tag is suppressed and replaced by the error message. Historically, the Cite extension reported almost all errors like this.
- Fatal errors triggered by a `<references>` tag appear below the reference list. See `Cite::$mReferencesErrors` in the legacy parser.
- So called list-defined `<ref>` tags inside of `<references>` require to combine both approaches. Since the normal rendering of the `<ref>` tag is suppressed there is nothing errors can be attached to. Instead they appear after the reference list.

So far, the Parsoid implementation doesn't have a concept of "fatals". Everything is a warning (see below).

### Warnings

Warnings are shown as part of a `<ref>` tag's normal rendering in the reference list and don't suppress it. For example, an invalid `dir="top"` is skipped and the normal process continues as if the attribute is not there. Instead, a warning message is attached to the `<ref>`'s rendering.

Historically, error message keys have been prefixed `cite_error_…` and `cite_warning_…`. This naming scheme is obsolete and (almost) meaningless and cannot be used to tell which error is considered a "fatal" or "warning". Instead, please refer to the implementation of the `Validator` class or the collection of parser test cases.

## History

Historically, it was possible for error messages to show up in several more places, annotating or replacing different parts of the HTML output. This was mostly due to different authors adding new functionality to the extensions at different points in time. The last of these inconsistencies got cleaned up in 2025.

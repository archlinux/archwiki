# Link anchors

The numeric markers in the text can be clicked to jump down to the corresponding item in the reference list, and back. This is done with `<a href="#…">` links targetting matching `id="…"` anchors.

> **Warning:** You know that e.g. `<` and `"` need to be "HTML escaped" in both `href="…"` and `id="…"`. In addition, the browser will apply URL decoding to `href="…"`. You need to URL encode `href="…"` but not `id="…"` to make them work together seamlessly. `AnchorFormatter` implements this.

## Down to the footnote

The anchor to jump down to a list item is prefixed `cite_note-`, followed by:
* For unnamed references: The numeric id of the reference, e.g. `cite_note-1`.
* For named references: The reference's name, a dash, and the numeric id, e.g. `cite_note-Britannica-2`.

> **Warning:** A reference's name alone is not necessarily unique because of additional underscore normalization (see `AnchorFormatter::normalizeFragmentIdentifier`). For example, `name="a b"` and `name="a__b"` are different names that both become `cite_note-a_b-…` as an anchor. Only the additional id makes them unique.

This is implemented in `AnchorFormatter::getNoteIdentifier`, used by both parsers.

## Back up to the article text

The anchor to jump back up to one of possibly multiple footnote markers is prefixed `cite_ref-`, followed by:
* For unnamed references: The numeric id of the reference, e.g. `cite_ref-1`.
* For named references that are not re-used: The reference's name, an underscore, numeric id, a dash, and a zero, e.g. `cite_ref-Britannica_2-0`.
* For named references that are re-used: As above, except with any number in place of the zero.

This is implemented in `AnchorFormatter::getBacklinkIdentifier`, used by both parsers.

**Additional notes:**
* As above, the name alone is not necessarily unique.
* The underscore normalization happens for historical reasons because the legacy parser creates links by parsing wikitext like `[[#cite_note-a b-1]]`. The MediaWiki parser enforces some normalizations on such wikitext. Parsoid re-implements this behavior only for compatibility, but doesn't really need to for any technical reason.
* The numeric identifier is global per document and increments for any `<ref>` tag in the document, no matter which group. This is **not** the same as the number visible in the footnote marker.
* Re-uses are 0-based. This is only for historical reasons and could as well be changed to be 1-based.

## History

* Historically, it was possible to customize the `cite_note-` and `cite_ref-` prefixes. This was (almost) unused and [removed](https://gerrit.wikimedia.org/r/987766) in 2024.
* Incomplete `<ref follow=…>` (relevant for Wikisource) got anchors in just another format, e.g. `cite_note-Britannica`. This was unused and [removed](https://gerrit.wikimedia.org/r/1130986) in 2025.

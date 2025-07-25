<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;

/**
 * Individual item in a {@see RefGroup}.
 *
 * @license GPL-2.0-or-later
 */
class RefGroupItem {

	/**
	 * Pointer to the contents of the ref, accessible with the
	 * {@see ParsoidExtensionAPI::getContentDOM}, to be used when serializing the references group.
	 * It gets set when extracting the ref from a node and not $missingContent.  Note that that
	 * might not be the first one for named refs.  Also, for named refs, it's used to detect
	 * multiple conflicting definitions.
	 */
	public ?string $contentId = null;
	/**
	 * Used when the content comes from an attribute eg. subreference details.
	 */
	public ?Node $externalFragment = null;

	public string $dir = '';
	/**
	 * Name of the group (or empty for the default group) which this <ref> belongs to.
	 */
	public string $group = '';
	/**
	 * The original name="…" attribute of a <ref>, or null for anonymous, unnamed references.
	 * Guaranteed to never be empty or "0". These are not valid names.
	 */
	public ?string $name = null;

	/**
	 * Sequence number per {@see $group}, starting from 1. To be used in the footnote marker,
	 * e.g. "[1]".
	 */
	public int $numberInGroup = 1;

	/**
	 * Sequence number per subref set, starting from 1.  Used in
	 * hierarchical footnote numbering, eg. "[1.1]".
	 */
	public ?int $subrefIndex = null;

	/**
	 * Global, unique sequence number for each <ref>, no matter which group, starting from 1.
	 * 0 is invalid. Currently unused.
	 */
	public int $globalId;

	/**
	 * True if this was a main ref artificially split from a main+details in the article.
	 */
	public bool $isMainWithDetails = false;

	/**
	 * @var Element[] Collection of footnote markers that have been generated so far for the same
	 * reference. Mainly used to track errors and render them in the reference list, instead of next
	 * to (or instead of) the footnote marker. Can be empty in case of not-yet used or unused
	 * list-defined references, or sub-reference parents.
	 */
	public array $nodes = [];

	/** @var string[] */
	public array $embeddedNodes = [];

	public int $visibleNodes = 0;

}

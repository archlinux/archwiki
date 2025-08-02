<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\PropGuard;

/**
 * A pseudo-element used as a marker or bookmark in the list of active formatting elements
 */
class Marker implements FormattingElement {
	use PropGuard;

	/** @var self|null */
	public $nextAFE;
	/** @var self|null */
	public $prevAFE;
	/** @var self|null */
	public $nextNoah;
	/** @var string */
	public $type;

	public function __construct( string $type ) {
		$this->type = $type;
	}
}

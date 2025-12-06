<?php

namespace MediaWiki\Extension\Notifications;

/**
 * Implements the ContainmentList interface for php arrays.  Possible source
 * of arrays includes $wg* global variables initialized from extensions or global
 * wiki config.
 */
class ArrayList implements ContainmentList {
	public function __construct( protected array $list ) {
	}

	/**
	 * @inheritDoc
	 */
	public function getValues() {
		return $this->list;
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey() {
		return '';
	}
}

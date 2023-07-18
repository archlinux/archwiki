<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

interface HeadingItem extends ThreadItem {
	/**
	 * @return int Heading level (1-6)
	 */
	public function getHeadingLevel(): int;

	/**
	 * @return bool
	 */
	public function isPlaceholderHeading(): bool;
}

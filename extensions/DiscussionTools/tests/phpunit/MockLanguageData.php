<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use MediaWiki\Extension\DiscussionTools\LanguageData;

class MockLanguageData extends LanguageData {

	public function __construct(
		private readonly array $data,
	) {
	}

	public function getLocalData(): array {
		return $this->data;
	}
}

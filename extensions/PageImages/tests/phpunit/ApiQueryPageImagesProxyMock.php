<?php

namespace PageImages\Tests;

use MediaWiki\Api\ApiPageSet;
use PageImages\ApiQueryPageImages;

class ApiQueryPageImagesProxyMock extends ApiQueryPageImages {

	/** @inheritDoc */
	public function __construct(
		private readonly ApiPageSet $pageSet,
	) {
	}

	/** @inheritDoc */
	public function getPageSet() {
		return $this->pageSet;
	}

	/** @inheritDoc */
	public function getTitles(): array {
		return parent::getTitles();
	}
}

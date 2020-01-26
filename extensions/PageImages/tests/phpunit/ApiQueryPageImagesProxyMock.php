<?php

namespace PageImages\Tests;

use ApiPageSet;
use ApiQueryPageImages;

class ApiQueryPageImagesProxyMock extends ApiQueryPageImages {

	/** @inheritDoc */
	public function __construct( ApiPageSet $pageSet ) {
		$this->pageSet = $pageSet;
	}

	/** @inheritDoc */
	public function getPageSet() {
		return $this->pageSet;
	}

	/** @inheritDoc */
	public function getTitles() {
		return parent::getTitles();
	}

	/** @inheritDoc */
	public static function getPropNames( $license ) {
		return parent::getPropNames( $license );
	}
}

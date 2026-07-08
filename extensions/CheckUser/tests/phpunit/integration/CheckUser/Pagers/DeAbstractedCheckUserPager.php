<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;

/**
 * A helper class for AbstractCheckUserPagerTest.php.
 * Because AbstractCheckUserPager is an abstract class
 * it has to be extended first if an object is wanted.
 *
 * This implements the abstracted methods by returning
 * an empty response or a predefined response (as testing
 * these methods should be done for classes that extend
 * AbstractCheckUserPager).
 */
class DeAbstractedCheckUserPager extends AbstractCheckUserPager {

	public array $mockGetQueryInfoResponse = [
		'tables' => [],
		'fields' => [],
		'conds' => [],
		'options' => [],
		'join_conds' => [],
	];

	/** @inheritDoc */
	public function formatRow( $row ) {
		return '';
	}

	public function getQueryInfo( ?string $table = null ): array {
		return $this->mockGetQueryInfoResponse;
	}

	protected function getQueryInfoForCuChanges(): array {
		return $this->mockGetQueryInfoResponse;
	}

	protected function getQueryInfoForCuLogEvent(): array {
		return $this->mockGetQueryInfoResponse;
	}

	protected function getQueryInfoForCuPrivateEvent(): array {
		return $this->mockGetQueryInfoResponse;
	}
}

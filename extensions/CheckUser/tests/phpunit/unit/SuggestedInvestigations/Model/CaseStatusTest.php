<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus
 */
class CaseStatusTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideNewFromStringName */
	public function testNewFromStringName( $status, $expectedCaseStatus ): void {
		$this->assertSame( $expectedCaseStatus, CaseStatus::newFromStringName( $status ) );
	}

	public static function provideNewFromStringName(): array {
		return [
			'Status is "open"' => [ 'open', CaseStatus::Open ],
			'Status is "invalid"' => [ 'invalid', CaseStatus::Invalid ],
			'Status is "closed"' => [ 'closed', CaseStatus::Resolved ],
			'Status is "resolved"' => [ 'resolved', CaseStatus::Resolved ],
			'Status is unknown string' => [ 'abcdef', null ],
		];
	}
}

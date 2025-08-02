<?php

namespace MediaWiki\CheckUser\Tests\Unit\ClientHints;

use LogicException;
use MediaWiki\CheckUser\ClientHints\ClientHintsBatchFormatterResults;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWikiUnitTestCase;
use TypeError;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\ClientHints\ClientHintsBatchFormatterResults
 */
class ClientHintsBatchFormatterResultsTest extends MediaWikiUnitTestCase {
	use CheckUserClientHintsCommonTraitTest;

	/** @dataProvider provideGetStringForReferenceId */
	public function testGetStringForReferenceId(
		$referenceIdsToIndexMap, $formattedStringsArray, $referenceId, $referenceType, $expectedReturnValue
	) {
		$objectUnderTest = new ClientHintsBatchFormatterResults( $referenceIdsToIndexMap, $formattedStringsArray );
		$this->assertSame(
			$expectedReturnValue,
			$objectUnderTest->getStringForReferenceId( $referenceId, $referenceType ),
			'Return value of ::getStringForReferenceId was not as expected.'
		);
	}

	public static function provideGetStringForReferenceId() {
		yield 'Empty result list for cu_changes reference ID' => [
			[], [],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Missing reference ID' => [
			[ 0 => [ 1 => 0 ] ],
			[ '' ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Missing mapping ID' => [
			[ 1 => [ 2 => 0 ] ],
			[ '' ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Missing ClientHintsData object' => [
			[ 0 => [ 2 => 1 ] ],
			[ '' ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
			null,
		];

		yield 'Present reference ID for empty formatted string' => [
			[ 1 => [ 2 => 0 ] ],
			[ '' ],
			2,
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
			'',
		];

		yield 'Present reference ID for example formatted string' => [
			[ 2 => [ 123 => 0, 1234 => 1 ] ],
			[
				'Brand: Not.A/Brand 99.0.0.0, Brand: Google Chrome 115.0.5790.171, Brand: Chromium 115.0.5790.171, ' .
				'Platform: Windows 15.0.0 and Mobile: No',
				''
			],
			123,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
			'Brand: Not.A/Brand 99.0.0.0, Brand: Google Chrome 115.0.5790.171, Brand: Chromium 115.0.5790.171, ' .
			'Platform: Windows 15.0.0 and Mobile: No',
		];
	}

	/** @dataProvider provideInvalidReferenceTypes */
	public function testGetStringForReferenceIdOnInvalidReferenceType( $invalidMapId, $expectedExceptionName ) {
		$this->expectException( $expectedExceptionName );
		$this->testGetStringForReferenceId( [], [], 1, $invalidMapId, null );
	}

	public static function provideInvalidReferenceTypes() {
		return [
			'String type' => [ "testing", TypeError::class ],
			'Negative number' => [ -1, LogicException::class ],
			'Out of bounds integer' => [ 121212312, LogicException::class ],
		];
	}
}

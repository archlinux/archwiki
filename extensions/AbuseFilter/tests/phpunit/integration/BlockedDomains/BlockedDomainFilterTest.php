<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\BlockedDomains;

use MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainFilter;
use MediaWiki\Extension\AbuseFilter\BlockedDomains\IBlockedDomainStorage;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\BlockedDomains\BlockedDomainFilter
 */
class BlockedDomainFilterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideDomains
	 */
	public function test( string $domain, ?string $expectedError ) {
		$manager = $this->createMock( VariablesManager::class );
		$manager->method( 'getVar' )->willReturn(
			AFPData::newFromPHPVar( "https://$domain" )
		);

		$storage = $this->createMock( IBlockedDomainStorage::class );
		$storage->method( 'loadComputed' )->willReturn( array_flip( [
			'blocked',
			'bad.tld',
		] ) );

		$filter = new BlockedDomainFilter( $manager, $storage );
		$status = $filter->filter(
			new VariableHolder(),
			$this->getTestUser()->getUser(),
			$this->createMock( Title::class )
		);

		if ( $expectedError ) {
			$this->assertStatusError( $expectedError, $status );
		} else {
			$this->assertStatusGood( $status );
		}
	}

	public static function provideDomains() {
		return [
			[ '', null ],
			[ 'a', null ],

			[ 'blocked', 'abusefilter-blocked-domains-attempted' ],
			[ 'also.blocked', 'abusefilter-blocked-domains-attempted' ],
			[ '.blocked', 'abusefilter-blocked-domains-attempted' ],
			[ 'blocked.not', null ],

			[ 'bad', null ],
			[ 'bad.tld', 'abusefilter-blocked-domains-attempted' ],
			[ 'also.bad.tld', 'abusefilter-blocked-domains-attempted' ],
			[ '.bad.tld', 'abusefilter-blocked-domains-attempted' ],
			[ 'bad.tld.not', null ],
		];
	}

}

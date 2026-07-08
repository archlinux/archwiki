<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Jobs;

use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\StoreClientHintsDataJob
 * @group CheckUser
 */
class StoreClientHintsDataJobTest extends MediaWikiUnitTestCase {

	use CheckUserClientHintsCommonTestTrait;

	public function testShouldCreateValidSpecification() {
		$clientHintsData = $this->getExampleClientHintsDataObjectFromJsApi();

		$spec = StoreClientHintsDataJob::newSpec( $clientHintsData, 1234, 'privatelog' );

		$this->assertSame( StoreClientHintsDataJob::TYPE, $spec->getType() );
		$this->assertClientHintsDataObjectsEqual(
			$clientHintsData,
			ClientHintsData::newFromSerialisedJsonArray( $spec->getParams()['clientHintsData'] )
		);
		$this->assertSame( 1234, $spec->getParams()['referenceId'] );
		$this->assertSame( 'privatelog', $spec->getParams()['referenceType'] );
	}
}

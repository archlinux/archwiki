<?php

namespace MediaWiki\CheckUser\Tests\Unit\Jobs;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob
 * @group CheckUser
 */
class StoreClientHintsDataJobTest extends MediaWikiUnitTestCase {

	use CheckUserClientHintsCommonTraitTest;

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

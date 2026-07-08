<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Services;

// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseCrossWikiJobDispatcherTest extends MediaWikiUnitTestCase {

	public function testDispatchReturnsEarlyWhenCentralAuthNotAvailable(): void {
		$jobQueueGroupFactory = $this->createNoOpMock( JobQueueGroupFactory::class );

		$dispatcher = new SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher(
			$jobQueueGroupFactory,
			new NullLogger(),
			false,
			'testwiki'
		);
		$dispatcher->dispatch( 'TestUser' );
	}

}

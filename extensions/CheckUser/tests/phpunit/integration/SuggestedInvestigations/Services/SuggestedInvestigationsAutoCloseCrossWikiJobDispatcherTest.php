<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use CentralAuthTestUser;
use LogicException;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsAutoCloseCrossWikiJobDispatcherTest extends MediaWikiIntegrationTestCase {

	private const USERNAME = 'CrossWikiTestUser';

	private JobQueueGroupFactory&MockObject $jobQueueGroupFactory;

	private LoggerInterface&MockObject $logger;

	private SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher $crossWikiAutoCloseJobDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		// Create a central auth global user attached to an "other-wiki"
		$centralAuthTestUser = new CentralAuthTestUser(
			self::USERNAME,
			'password',
			[ 'gu_id' => 123456789 ],
			[ [ 'other-wiki', 'primary' ] ]
		);
		$centralAuthTestUser->save( $this->getDb() );

		$this->jobQueueGroupFactory = $this->createMock( JobQueueGroupFactory::class );
		$this->logger = $this->createMock( LoggerInterface::class );

		$this->crossWikiAutoCloseJobDispatcher = new SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher(
			$this->jobQueueGroupFactory,
			$this->logger,
			true,
			WikiMap::getCurrentWikiId()
		);
	}

	public function testDispatchPushesJobsToOtherWikis(): void {
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->once() )
			->method( 'lazyPush' )
			->with( $this->isInstanceOf( IJobSpecification::class ) );

		$this->jobQueueGroupFactory->expects( $this->once() )
			->method( 'makeJobQueueGroup' )
			->with( 'other-wiki' )
			->willReturn( $jobQueueGroup );

		$this->logger->expects( $this->never() )
			->method( 'warning' );

		$this->crossWikiAutoCloseJobDispatcher->dispatch( self::USERNAME );
	}

	public function testDispatchLogsWhenMakeJobQueueGroupThrows(): void {
		$this->jobQueueGroupFactory->expects( $this->once() )
			->method( 'makeJobQueueGroup' )
			->with( 'other-wiki' )
			->willThrowException( new LogicException( 'no such wiki' ) );

		$this->logger->expects( $this->once() )
			->method( 'warning' );

		$this->crossWikiAutoCloseJobDispatcher->dispatch( self::USERNAME );
	}

}

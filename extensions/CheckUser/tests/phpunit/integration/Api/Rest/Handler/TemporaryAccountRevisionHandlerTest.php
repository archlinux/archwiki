<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionHandler;
use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\ArchiveSelectQueryBuilder;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSelectQueryBuilder;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionHandler
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\TemporaryAccountRevisionTrait
 */
class TemporaryAccountRevisionHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;
	use MockServiceDependenciesTrait;

	/**
	 * By default, services are mocked for a successful Response.
	 * They can be overridden via $options.
	 *
	 * @param array $options
	 * @return TemporaryAccountRevisionHandler
	 */
	private function getTemporaryAccountRevisionHandler( array $options = [] ): TemporaryAccountRevisionHandler {
		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$userNameUtils = $this->createMock( UserNameUtils::class );
		$userNameUtils->method( 'isTemp' )
			->willReturn( true );

		$actorStore = $this->createMock( ActorStore::class );
		$actorStore->method( 'findActorIdByName' )
			->willReturn( 1234 );
		$actorStore->method( 'getUserIdentityByName' )
			->willReturn( new UserIdentityValue( 1234, '*Unregistered 1' ) );

		$mockRevisionStore = $this->getMockRevisionStore();
		$mockRevisionStore->method( 'newRevisionsFromBatch' )
			->willReturnCallback( function ( $rows ) {
				$revisions = [];
				$rows->rewind();
				foreach ( $rows as $row ) {
					$mockRevision = $this->createMock( RevisionRecord::class );
					$mockRevision->method( 'userCan' )
						->willReturn( true );
					$mockRevision->method( 'getId' )
						->willReturn( $row->rev_id );
					$revisions[] = $mockRevision;
				}
				return StatusValue::newGood( $revisions );
			} );

		$checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$checkUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		$services = $this->getServiceContainer();
		return new TemporaryAccountRevisionHandler( ...array_values( array_merge(
			[
				'config' => $services->getMainConfig(),
				'jobQueueGroup' => $this->createMock( JobQueueGroup::class ),
				'permissionManager' => $permissionManager,
				'preferencesFactory' => $services->getPreferencesFactory(),
				'userNameUtils' => $userNameUtils,
				'dbProvider' => $services->getDBLoadBalancerFactory(),
				'actorStore' => $actorStore,
				'blockManager' => $services->getBlockManager(),
				'revisionStore' => $mockRevisionStore,
				'checkUserPermissionManager' => $checkUserPermissionManager,
				'readOnlyMode' => $services->getReadOnlyMode(),
			],
			$options
		) ) );
	}

	private function getMockRevisionStore() {
		// Mock the RevisionStore to say that all revisions can be viewed by the authority (we need to do this as
		// the revisions are not inserted to the DB).
		$mockRevision = $this->createMock( RevisionRecord::class );
		$mockRevision->method( 'userCan' )
			->willReturn( true );
		$mockRevision->method( 'getId' )
			->willReturn( 1000 );
		// Create a mock RevisionStore to return the mock select query builder and also
		// mock ::newRevisionsFromBatch.
		$mockRevisionStore = $this->createMock( RevisionStore::class );
		$mockRevisionStore->method( 'newSelectQueryBuilder' )
			->willReturn( $this->getMockRevisionOrArchiveQueryBuilder(
				RevisionSelectQueryBuilder::class,
				'rev_id'
			) );
		$mockRevisionStore->method( 'newArchiveSelectQueryBuilder' )
			->willReturn( $this->getMockRevisionOrArchiveQueryBuilder(
				ArchiveSelectQueryBuilder::class,
				'ar_rev_id'
			) );
		return $mockRevisionStore;
	}

	/**
	 * Creates a mock ArchiveSelectQueryBuilder or RevisionSelectQueryBuilder that
	 * returns mock revision rows from ::fetchResultSet. These mock rows are controlled
	 * by the IDs that are requested in the query.
	 *
	 * @param class-string<RevisionSelectQueryBuilder|ArchiveSelectQueryBuilder> $className
	 * @param string $revColumnName
	 * @return ArchiveSelectQueryBuilder|RevisionSelectQueryBuilder|MockObject
	 */
	private function getMockRevisionOrArchiveQueryBuilder( string $className, string $revColumnName ) {
		/** @var MockObject|RevisionSelectQueryBuilder|ArchiveSelectQueryBuilder $mockSelectQueryBuilder */
		$mockSelectQueryBuilder = $this->getMockBuilder( $className )
			->onlyMethods( [ 'fetchResultSet' ] )
			->setConstructorArgs( [ $this->createMock( IReadableDatabase::class ) ] )
			->getMock();
		$mockSelectQueryBuilder->method( 'fetchResultSet' )
			->willReturnCallback( static function () use ( $mockSelectQueryBuilder, $revColumnName ) {
				return new FakeResultWrapper( array_map(
					static function ( $revId ) use ( $revColumnName ) {
						return [ $revColumnName => $revId ];
					},
					array_values( array_filter(
						$mockSelectQueryBuilder->getQueryInfo()['conds'][$revColumnName],
						static function ( $revId ) {
							return in_array( $revId, [ 10, 100, 1000 ] );
						}
					) )
				) );
			} );
		return $mockSelectQueryBuilder;
	}

	/**
	 * @return Authority
	 */
	private function getAuthorityForSuccess(): Authority {
		return $this->getTestUser()->getAuthority();
	}

	private function getRequestData( array $options = [] ): RequestData {
		return new RequestData( [
			'pathParams' => [
				'name' => $options['name'] ?? '*Unregistered 1',
				'ids' => $options['ids'] ?? [ 10 ],
			],
		] );
	}

	/**
	 * @dataProvider provideExecute
	 */
	public function testExecute( $expected, $options ) {
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler(),
			$this->getRequestData( $options ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			$expected,
			$data['ips'],
			true
		);
	}

	public static function provideExecute() {
		return [
			'One revision' => [
				[
					'10' => '1.2.3.4',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => 10,
				],
			],
			'Multiple revisions' => [
				[
					'10' => '1.2.3.4',
					'100' => '1.2.3.5',
					'1000' => '1.2.3.5',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => [ 1000, 10, 100 ],
				],
			],
			'Nonexistent revisions included' => [
				[
					'10' => '1.2.3.4',
				],
				[
					'name' => '*Unregistered 1',
					'ids' => [ 9999, 10 ],
				],
			],
		];
	}

	public function testFailsWithoutValidToken() {
		$handler = $this->newServiceInstance( TemporaryAccountRevisionHandler::class, [] );
		$validator = $this->createMock( Validator::class );
		$this->expectException( LocalizedHttpException::class );
		$request = new RequestData();
		$config = [
			'path' => '/foo'
		];
		$this->initHandler( $handler, $request, $config, [], null, $this->getSession( false ) );
		// Invoking the method to be tested
		$this->validateHandler( $handler );
	}

	public function testErrorOnMissingRevisionIds() {
		$this->expectExceptionCode( 400 );
		$this->expectExceptionMessage( 'paramvalidator-missingparam' );
		$this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler(),
			$this->getRequestData( [
				'ids' => []
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
	}

	public function testWhenRevisionPerformersAreSuppressed() {
		// Pretend the authority does not have the rights to view any of the revisions.
		$mockRevision = $this->createMock( RevisionRecord::class );
		$mockRevision->method( 'userCan' )
			->willReturn( false );
		// Create a mock RevisionStore that mocks ::newRevisionsFromBatch to return two revisions
		// from the revision table.
		$mockRevisionStore = $this->getMockRevisionStore();
		$mockRevisionStore->method( 'newRevisionsFromBatch' )
			->willReturn( StatusValue::newGood( [ $mockRevision, $mockRevision ] ) );
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler( [
				'revisionStore' => $mockRevisionStore
			] ),
			$this->getRequestData( [
				'name' => '*Unregistered 1',
				'ids' => [ 10, 100 ],
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals( [], $data['ips'] );
	}

	public function testWhenRevisionPerformersAreSuppressedWithOneRevisionDeleted() {
		// Pretend the authority does not have the rights to view any of the revisions.
		$mockSuppressedRevision = $this->createMock( RevisionRecord::class );
		$mockSuppressedRevision->method( 'userCan' )
			->willReturn( false );
		// Mock that the revision with ID 1000 can be viewed.
		$mockRevision = $this->createMock( RevisionRecord::class );
		$mockRevision->method( 'userCan' )
			->willReturn( true );
		$mockRevision->method( 'getId' )
			->willReturn( 1000 );
		// Create a mock RevisionStore that mocks ::newRevisionsFromBatch to return two revisions
		// from the revision table (the second which can be viewed) and then to return one revision
		// from the archive table.
		$mockRevisionStore = $this->getMockRevisionStore();
		$mockRevisionStore->method( 'newRevisionsFromBatch' )
			->willReturnOnConsecutiveCalls(
				StatusValue::newGood( [ $mockSuppressedRevision, $mockRevision ] ),
				StatusValue::newGood( [ $mockSuppressedRevision ] )
			);
		$data = $this->executeHandlerAndGetBodyData(
			$this->getTemporaryAccountRevisionHandler( [
				'revisionStore' => $mockRevisionStore
			] ),
			$this->getRequestData( [
				'name' => '*Unregistered 1',
				'ids' => [ 10, 100, 1000 ],
			] ),
			[],
			[],
			[],
			[],
			$this->getAuthorityForSuccess()
		);
		$this->assertArrayEquals(
			[ 1000 => '1.2.3.5' ],
			$data['ips'],
			false,
			true
		);
	}

	public function addDBData() {
		$testData = [
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_this_oldid' => 10,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20200101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 100,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20210101000000' ),
			],
			[
				'cuc_actor'      => 1234,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_this_oldid' => 1000,
				'cuc_timestamp'  => $this->getDb()->timestamp( '20220101000000' ),
			],
		];

		$commonData = [
			'cuc_type'       => RC_EDIT,
			'cuc_agent'      => 'foo user agent',
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_comment_id' => 0,
			'cuc_last_oldid' => 0,
		];

		$queryBuilder = $this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->caller( __METHOD__ );
		foreach ( $testData as $row ) {
			$queryBuilder->row( $row + $commonData );
		}
		$queryBuilder->execute();
	}
}

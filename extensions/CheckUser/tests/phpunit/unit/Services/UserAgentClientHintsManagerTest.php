<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use LogicException;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager
 */
class UserAgentClientHintsManagerTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;
	use CheckUserClientHintsCommonTestTrait;

	/** @dataProvider provideValidTypes */
	public function testGetMapIdByType( string $type, int $expectedMapId ) {
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$this->assertSame(
			$expectedMapId,
			$objectToTest->getMapIdByType( $type ),
			'::getMapIdType did not return the correct map ID'
		);
	}

	public static function provideValidTypes() {
		return [
			'Revision type' => [ 'revision', 0 ],
			'Log type' => [ 'log', 1 ],
			'Private log type' => [ 'privatelog', 2 ],
		];
	}

	/** @dataProvider provideInvalidTypes */
	public function testGetMapIdUsingInvalidType( string $invalidType ) {
		$this->expectException( LogicException::class );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$objectToTest->getMapIdByType( $invalidType );
	}

	public static function provideInvalidTypes() {
		return [
			'Invalid string type' => [ 'invalidtype1234' ],
		];
	}

	private function getObjectUnderTest(
		IDatabase $dbwMock,
		IReadableDatabase $dbrMock,
		?LoggerInterface $logger = null
	): UserAgentClientHintsManager {
		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getPrimaryDatabase' )
			->willReturn( $dbwMock );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturn( $dbrMock );

		return $this->newServiceInstance( UserAgentClientHintsManager::class, [
			'dbProvider' => $dbProvider,
			'logger' => $logger ?? LoggerFactory::getInstance( 'CheckUser' ),
		] );
	}

	public function testReturnsEarlyOnNoDataToInsert() {
		// There should be no accesses to the DB if no data to insert
		$dbrMock = $this->createNoOpMock( IReadableDatabase::class );
		$dbwMock = $this->createNoOpMock( IDatabase::class );
		$objectToTest = $this->getObjectUnderTest( $dbwMock, $dbrMock );

		$status = $objectToTest->insertClientHintValues(
			ClientHintsData::newFromJsApi( [] ),
			1,
			'revision'
		);
		$this->assertStatusGood(
			$status,
			'Status should be good if no Client Hints data is present in the ClientHintsData object.'
		);
	}

	public function testDeleteMappingRowsWithEmptyReferenceIdsList() {
		$clientHintReferenceIds = new ClientHintsReferenceIds( [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [],
			UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [],
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT => [],
		] );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		/** @var Status $result */
		$result = $objectToTest->deleteMappingRows( $clientHintReferenceIds );
		$this->assertSame(
			0,
			$result,
			'No mapping rows should have been deleted, but ::deleteMappingRows reported ' .
			'deleting some mapping rows.'
		);
	}

	public function testIsMapRowOrphanedForInvalidMappingId() {
		$this->expectException( LogicException::class );
		$objectToTest = TestingAccessWrapper::newFromObject(
			$this->newServiceInstance( UserAgentClientHintsManager::class, [] )
		);
		$objectToTest->isMapRowOrphaned( 123, 123 );
	}
}

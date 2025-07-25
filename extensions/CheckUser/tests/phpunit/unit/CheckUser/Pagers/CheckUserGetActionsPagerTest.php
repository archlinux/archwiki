<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Language\Language;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for CheckUserGetActionsPager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager
 */
class CheckUserGetActionsPagerTest extends CheckUserPagerUnitTestBase {

	/** @inheritDoc */
	protected function getPagerClass(): string {
		return CheckUserGetActionsPager::class;
	}

	/** @dataProvider provideIsNavigationBarShown */
	public function testIsNavigationBarShown( $numRows, $shown ) {
		$object = $this->getMockBuilder( CheckUserGetActionsPager::class )
			->onlyMethods( [ 'getNumRows' ] )
			->disableOriginalConstructor()
			->getMock();
		$object->expects( $this->once() )
			->method( 'getNumRows' )
			->willReturn( $numRows );
		$object = TestingAccessWrapper::newFromObject( $object );
		if ( $shown ) {
			$this->assertTrue(
				$object->isNavigationBarShown(),
				'Navigation bar is not showing when it\'s supposed to'
			);
		} else {
			$this->assertFalse(
				$object->isNavigationBarShown(),
				'Navigation bar is showing when it is not supposed to'
			);
		}
	}

	public static function provideIsNavigationBarShown() {
		return [
			[ 0, false ],
			[ 2, true ]
		];
	}

	/** @dataProvider provideGetQueryInfoForCuChanges */
	public function testGetQueryInfoForCuChanges( $displayClientHints, $expectedQueryInfo ) {
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuChanges',
			[
				'commentStore' => new CommentStore( $this->createMock( Language::class ) ),
				'displayClientHints' => $displayClientHints
			],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuChanges() {
		return [
			'Returns expected keys to arrays and includes cu_changes in tables' => [
				false,
				[
					// Fields should be an array
					'fields' => [],
					// Assert at least cu_changes in the table list
					'tables' => [ 'cu_changes' ],
					// Should be all of these as arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
			'When displaying client hints' => [
				true,
				[
					// Fields should be an array with Client Hints fields set.
					'fields' => [
						'client_hints_reference_id' => 'cuc_this_oldid',
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES,
					],
					// Tables array should have at least cu_log_event
					'tables' => [ 'cu_changes' ],
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuLogEvent */
	public function testGetQueryInfoForCuLogEvent( $displayClientHints, $databaseType, $expectedQueryInfo ) {
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockDbr->expects( $this->once() )
			->method( 'getType' )
			->willReturn( $databaseType );
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuLogEvent',
			[
				'commentStore' => new CommentStore( $this->createMock( Language::class ) ),
				'displayClientHints' => $displayClientHints,
				'mDb' => $mockDbr,
			],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuLogEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				false,
				'mysql',
				[
					# Fields should be an array
					'fields' => [],
					# Tables array should have at least cu_log_event
					'tables' => [ 'cu_log_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
			'When displaying client hints' => [
				true,
				'mysql',
				[
					# Fields should be an array with Client Hints fields set.
					'fields' => [
						'client_hints_reference_id' => 'cule_log_id',
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT,
					],
					# Tables array should have at least cu_log_event
					'tables' => [ 'cu_log_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
			'When using postgres DB' => [
				false,
				'postgres',
				[
					# Fields should be an array with type casted to a smallint when using postgres DB.
					'fields' => [ 'type' => 'CAST(3 AS smallint)' ],
					# Tables array should have at least cu_log_event
					'tables' => [ 'cu_log_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	/** @dataProvider provideGetQueryInfoForCuPrivateEvent */
	public function testGetQueryInfoForCuPrivateEvent( $displayClientHints, $databaseType, $expectedQueryInfo ) {
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockDbr->expects( $this->once() )
			->method( 'getType' )
			->willReturn( $databaseType );
		$this->commonGetQueryInfoForTableSpecificMethod(
			'getQueryInfoForCuPrivateEvent',
			[
				'commentStore' => new CommentStore( $this->createMock( Language::class ) ),
				'displayClientHints' => $displayClientHints,
				'mDb' => $mockDbr,
			],
			$expectedQueryInfo
		);
	}

	public static function provideGetQueryInfoForCuPrivateEvent() {
		return [
			'Returns expected keys to arrays and includes cu_log_event in tables' => [
				false,
				'mysql',
				[
					# Fields should be an array
					'fields' => [ 'type' => RC_LOG ],
					# Tables array should have at least cu_private_event
					'tables' => [ 'cu_private_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
			'When displaying client hints' => [
				true,
				'mysql',
				[
					# Fields should be an array with Client Hints fields set.
					'fields' => [
						'client_hints_reference_id' => 'cupe_id',
						'client_hints_reference_type' => UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT,
					],
					# Tables array should have at least cu_private_event
					'tables' => [ 'cu_private_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
			'When using postgres DB' => [
				false,
				'postgres',
				[
					# Fields should be an array with type casted to a smallint when using postgres DB.
					'fields' => [ 'type' => 'CAST(3 AS smallint)' ],
					# Tables array should have at least cu_private_event
					'tables' => [ 'cu_private_event' ],
					# All other values should be arrays
					'conds' => [],
					'options' => [],
					'join_conds' => [],
				]
			],
		];
	}

	public function testGetActionTextForNonLogEvent() {
		$objectUnderTest = $this->getMockBuilder( CheckUserGetActionsPager::class )
			->onlyMethods( [] )
			->disableOriginalConstructor()
			->getMock();
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$this->assertSame(
			'',
			$objectUnderTest->getActionText( null ),
			'::getActionText did not return the correct actiontext.'
		);
	}
}

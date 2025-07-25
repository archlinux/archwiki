<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\CheckUser;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Api\CheckUser\ApiQueryCheckUserActionsResponse
 * @group Database
 */
class ApiQueryCheckUserActionsResponseTest extends MediaWikiIntegrationTestCase {

	private function getObjectUnderTest(): TestingAccessWrapper {
		$mockApiQueryCheckUser = $this->createMock( ApiQueryCheckUser::class );
		$mockApiQueryCheckUser->method( 'extractRequestParams' )
			->willReturn( [
				'request' => 'actions', 'target' => 'Test', 'reason' => '', 'timecond' => '-3 months', 'limit' => '50'
			] );
		/** @var ApiQueryCheckUserResponseFactory $responseFactory */
		$responseFactory = $this->getServiceContainer()->get( 'ApiQueryCheckUserResponseFactory' );
		return TestingAccessWrapper::newFromObject( $responseFactory->newFromRequest(
			$mockApiQueryCheckUser
		) );
	}

	/** @dataProvider provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters */
	public function testGetSummaryForLogEntry( $logParametersAsArray, $logParametersAsBlob ) {
		$moveLogEntry = new ManualLogEntry( 'move', 'move' );
		$moveLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$moveLogEntry->setTarget( $this->getExistingTestPage() );
		$moveLogEntry->setParameters( $logParametersAsArray );
		$testingRow = (object)[
			'log_type' => $moveLogEntry->getType(),
			'log_action' => $moveLogEntry->getSubtype(),
			'log_deleted' => 0,
			'user_text' => $moveLogEntry->getPerformerIdentity()->getName(),
			'user' => $moveLogEntry->getPerformerIdentity()->getId(),
			'title' => null,
			'page' => $moveLogEntry->getTarget()->getArticleID(),
			'log_params' => $logParametersAsBlob,
			'type' => RC_LOG,
			'timestamp' => $this->getDb()->timestamp( '20230405060708' ),
			'comment_text' => 'test',
			'comment_data' => FormatJson::encode( [] ),
		];
		$actualSummaryText = $this->getObjectUnderTest()->getSummary(
			$testingRow,
			null,
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
				->getManualLogEntryFromRow( $testingRow, $moveLogEntry->getPerformerIdentity() )
		);
		$this->assertSame(
			// The expected summary text is the action text from the move log entry, followed by the the comment text
			// in parantheses.
			$this->getServiceContainer()->getLogFormatterFactory()
				->newFromEntry( $moveLogEntry )->getPlainActionText() . ' ' . wfMessage( 'parentheses', 'test' ),
			$actualSummaryText,
			'The summary text returned by ::getSummary was not as expected'
		);
	}

	public static function provideFormatRowLogNotFromCuChangesWhenReadingNewWithLogParameters() {
		return [
			'Legacy log parameters' => [
				[ '4::target' => 'Testing', '5::noredir' => '0' ],
				LogPage::makeParamBlob( [ '4::target' => 'Testing', '5::noredir' => '0' ] ),
			],
			'Normal log parameters' => [
				[ '4::target' => 'Testing', '5::noredir' => '0' ],
				LogEntryBase::makeParamBlob( [ '4::target' => 'Testing', '5::noredir' => '0' ] ),
			]
		];
	}

	public function testGetSummaryForNoCommentOrActionText() {
		$actualSummaryText = $this->getObjectUnderTest()->getSummary(
			(object)[
				'user_text' => '127.0.0.1',
				'user' => 0,
				'title' => null,
				'page' => 0,
				'type' => RC_EDIT,
				'timestamp' => $this->getDb()->timestamp( '20230405060708' ),
				'comment_text' => '',
				'comment_data' => FormatJson::encode( [] ),
			],
			null,
			null
		);
		$this->assertNull(
			$actualSummaryText,
			'The summary text returned by ::getSummary was not as expected.'
		);
	}

	public function testGetSummaryForHiddenCommentUserAndActionText() {
		$moveLogEntry = new ManualLogEntry( 'move', 'move' );
		$moveLogEntry->setPerformer( UserIdentityValue::newAnonymous( '127.0.0.1' ) );
		$moveLogEntry->setTarget( $this->getExistingTestPage() );
		$moveLogEntry->setDeleted( LogPage::DELETED_COMMENT | LogPage::DELETED_ACTION | LogPage::DELETED_USER );
		$moveLogEntry->setParameters( [] );
		$testingRow = (object)[
			'log_type' => $moveLogEntry->getType(),
			'log_action' => $moveLogEntry->getSubtype(),
			'log_deleted' => $moveLogEntry->getDeleted(),
			'user_text' => $moveLogEntry->getPerformerIdentity()->getName(),
			'user' => $moveLogEntry->getPerformerIdentity()->getId(),
			'title' => null,
			'page' => $moveLogEntry->getTarget()->getArticleID(),
			'log_params' => LogEntryBase::makeParamBlob( [] ),
			'type' => RC_LOG,
			'timestamp' => $this->getDb()->timestamp( '20230405060708' ),
			'comment_text' => 'test',
			'comment_data' => FormatJson::encode( [] ),
		];
		$actualSummaryText = $this->getObjectUnderTest()->getSummary(
			$testingRow,
			null,
			$this->getServiceContainer()->get( 'CheckUserLookupUtils' )
				->getManualLogEntryFromRow( $testingRow, $moveLogEntry->getPerformerIdentity() )
		);
		$this->assertSame(
			$this->getServiceContainer()->getLogFormatterFactory()->newFromEntry( $moveLogEntry )->getPlainActionText(),
			$actualSummaryText,
			'The summary text returned by ::getSummary was not as expected.'
		);
	}

	/** @dataProvider providePartialQueryBuilderMethodsThatCastTypeColumn */
	public function testGetPartialQueryBuildersForPostgres( $partialQueryBuilderMethod ) {
		// Tests that the cast of RC_LOG to smallint is performed when the DB is postgres.
		// Mock that the $mockDbr returns 'postgres' as the result of IReadableDatabase::getType
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockDbr->method( 'getType' )
			->willReturn( 'postgres' );
		// Make $mockDbr::newSelectQueryBuilder perform exactly the same thing
		// as in a real database, but instead using the $mockDbr.
		$mockDbr->method( 'newSelectQueryBuilder' )
			->willReturn( new SelectQueryBuilder( $mockDbr ) );
		// Get the object under test and set the mock database as the dbr object property.
		/** @var SelectQueryBuilder $partialQueryBuilder */
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->dbr = $mockDbr;
		$partialQueryBuilder = $objectUnderTest->$partialQueryBuilderMethod();
		$this->assertSame(
			'CAST(' . RC_LOG . ' AS smallint)',
			$partialQueryBuilder->getQueryInfo()['fields']['type'],
			'The select query builder returned by ::' . $partialQueryBuilderMethod . ' was not as expected.'
		);
	}

	public static function providePartialQueryBuilderMethodsThatCastTypeColumn() {
		return [
			'::getPartialQueryBuilderForCuLogEvent' => [ 'getPartialQueryBuilderForCuLogEvent' ],
			'::getPartialQueryBuilderForCuPrivateEvent' => [ 'getPartialQueryBuilderForCuPrivateEvent' ],
		];
	}
}

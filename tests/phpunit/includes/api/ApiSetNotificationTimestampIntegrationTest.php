<?php

/**
 * @author Addshore
 * @covers ApiSetNotificationTimestamp
 * @group API
 * @group medium
 * @group Database
 */
class ApiSetNotificationTimestampIntegrationTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[ 'watchlist', 'watchlist_expiry' ]
		);
	}

	public function testStuff() {
		$user = $this->getTestUser()->getUser();
		$page = WikiPage::factory( Title::newFromText( 'UTPage' ) );

		$watchlistManager = $this->getServiceContainer()->getWatchlistManager();
		$watchlistManager->addWatch( $user,  $page->getTitle() );

		$result = $this->doApiRequestWithToken(
			[
				'action' => 'setnotificationtimestamp',
				'timestamp' => '20160101020202',
				'pageids' => $page->getId(),
			],
			null,
			$user
		);

		$this->assertEquals(
			[
				'batchcomplete' => true,
				'setnotificationtimestamp' => [
					[ 'ns' => 0, 'title' => 'UTPage', 'notificationtimestamp' => '2016-01-01T02:02:02Z' ]
				],
			],
			$result[0]
		);

		$watchedItemStore = $this->getServiceContainer()->getWatchedItemStore();
		$this->assertEquals(
			$watchedItemStore->getNotificationTimestampsBatch( $user, [ $page->getTitle() ] ),
			[ [ 'UTPage' => '20160101020202' ] ]
		);
	}

}

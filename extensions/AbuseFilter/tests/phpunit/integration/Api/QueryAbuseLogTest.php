<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use ApiTestCase;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Api\QueryAbuseLog
 * @group medium
 * @todo Extend this
 */
class QueryAbuseLogTest extends ApiTestCase {

	/**
	 * @covers ::__construct
	 */
	public function testConstruct() {
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'abuselog',
		], null, null, self::getTestUser()->getUser() );
		$this->addToAssertionCount( 1 );
	}

}

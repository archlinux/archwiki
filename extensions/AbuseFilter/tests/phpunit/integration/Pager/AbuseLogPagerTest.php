<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Pager;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterStore;
use MediaWiki\Extension\AbuseFilter\Pager\AbuseLogPager;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Page\WikiPage;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseLogPager
 * @group Database
 */
class AbuseLogPagerTest extends MediaWikiIntegrationTestCase {
	use FilterFromSpecsTestTrait;
	use MockAuthorityTrait;

	private WikiPage $page;

	public function addDBDataOnce(): void {
		$this->page = $this->getExistingTestPage( 'AbuseLogPagerTest' );

		$performer = $this->getTestSysop()->getUserIdentity();
		$filter1 = $this->getFilterFromSpecs( [
			'id' => '1',
			'rules' => 'user_name = "1.2.3.5"',
			'name' => 'Filter 1',
			'privacy' => Flags::FILTER_PUBLIC,
			'userIdentity' => $performer,
			'timestamp' => $this->getDb()->timestamp( '20190825000000' ),
		] );

		$sysOpsAuthority = new UltimateAuthority( $performer );
		$filterStore = $this->getServiceContainer()->get(
			FilterStore::SERVICE_NAME
		);
		$this->assertStatusGood(
			$filterStore->saveFilter(
				$sysOpsAuthority,
				null,
				$filter1,
				MutableFilter::newDefault()
			)
		);

		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$userWhoHitFilter = $this->getTestUser()->getUser();

		$logger = AbuseFilterServices::getAbuseLoggerFactory()->newLogger(
			$this->page->getTitle(),
			$userWhoHitFilter,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_unnamed_ip' => '1.2.3.4',
				'user_name' => $userWhoHitFilter->getName(),
			] )
		);
		$logger->addLogEntries( [ 1 => [ 'warn' ] ] );

		// Verify that the expected number of DB rows were created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_history' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_log' )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
	}

	public function testLogEntriesContainLogIdsAsDataAttributes(): void {
		$this->setUserLang( 'qqx' );

		$services = $this->getServiceContainer();
		$pager = new AbuseLogPager(
			RequestContext::getMain(),
			$services->getLinkRenderer(),
			[],
			$services->getLinkBatchFactory(),
			$services->getPermissionManager(),
			AbuseFilterServices::getPermissionManager( $services ),
			AbuseFilterServices::getVariablesBlobStore( $services ),
			$this->page->getTitle(),
			[]
		);

		// Disabling 'abusefilter-log-detail' for the accessing authority prevents
		// running the logic for populating action links in the pager, which in
		// turn makes the pager skip a call to SpecialPage::getTitleFor() for
		// $this->page, which would fail unless we add additional setup logic to
		// this test.
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setAuthority(
			$this->mockRegisteredAuthorityWithoutPermissions(
				[ 'abusefilter-log-detail' ]
			)
		);
		$pager->setContext( $context );

		$html = $pager->getBody();
		$this->assertStringContainsString(
			'<li data-afl-log-id="',
			$html,
			'Elements in the list of log entries contain log IDs'
		);
		$this->assertCount(
			1,
			DOMCompat::querySelectorAll(
				DOMUtils::parseHTML( $html ),
				'li[data-afl-log-id="1"]'
			),
			'Could not find log entry with a data-afl-log-id equal to 1'
		);
	}
}

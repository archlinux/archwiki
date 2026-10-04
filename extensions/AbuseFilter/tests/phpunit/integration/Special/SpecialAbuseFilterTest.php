<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Special;

use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewImport;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewList;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch;
use MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTools;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\Special\AbuseFilterSpecialPage
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewExamine
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewImport
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewList
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewRevert
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTestBatch
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewTools
 */
class SpecialAbuseFilterTest extends SpecialPageTestBase {

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialAbuseFilter {
		$services = $this->getServiceContainer();
		$sp = new SpecialAbuseFilter(
			$services->getService( ServiceNames::PermManager ),
			$services->getObjectFactory()
		);
		$sp->setLinkRenderer(
			$services->getLinkRendererFactory()->create()
		);
		return $sp;
	}

	/**
	 * @dataProvider provideInstantiateView
	 */
	public function testInstantiateView( string $viewClass, array $params = [] ) {
		$sp = $this->newSpecialPage();
		$view = $sp->instantiateView( $viewClass, $params );
		$this->assertInstanceOf( $viewClass, $view );
	}

	public static function provideInstantiateView(): array {
		return [
			[ AbuseFilterViewDiff::class ],
			[ AbuseFilterViewEdit::class, [ 'filter' => 1 ] ],
			[ AbuseFilterViewExamine::class ],
			[ AbuseFilterViewHistory::class ],
			[ AbuseFilterViewImport::class ],
			[ AbuseFilterViewList::class ],
			[ AbuseFilterViewRevert::class ],
			[ AbuseFilterViewTestBatch::class ],
			[ AbuseFilterViewTools::class ],
		];
	}
}

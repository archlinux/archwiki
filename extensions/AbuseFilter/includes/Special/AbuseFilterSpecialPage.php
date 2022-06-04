<?php

namespace MediaWiki\Extension\AbuseFilter\Special;

use HtmlArmor;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use SpecialPage;
use TitleValue;
use Xml;

/**
 * Parent class for AbuseFilter special pages.
 */
abstract class AbuseFilterSpecialPage extends SpecialPage {

	/** @var AbuseFilterPermissionManager */
	protected $afPermissionManager;

	/**
	 * @param string $name
	 * @param string $restriction
	 * @param AbuseFilterPermissionManager $afPermissionManager
	 */
	public function __construct(
		$name,
		$restriction,
		AbuseFilterPermissionManager $afPermissionManager
	) {
		parent::__construct( $name, $restriction );
		$this->afPermissionManager = $afPermissionManager;
	}

	/**
	 * Add topbar navigation links
	 *
	 * @param string $pageType
	 */
	protected function addNavigationLinks( $pageType ) {
		$user = $this->getUser();

		$linkDefs = [
			'home' => 'AbuseFilter',
			'recentchanges' => 'AbuseFilter/history',
			'examine' => 'AbuseFilter/examine',
		];

		if ( $this->afPermissionManager->canViewAbuseLog( $user ) ) {
			$linkDefs += [
				'log' => 'AbuseLog'
			];
		}

		if ( $this->afPermissionManager->canUseTestTools( $user ) ) {
			$linkDefs += [
				'test' => 'AbuseFilter/test',
				'tools' => 'AbuseFilter/tools'
			];
		}

		$links = [];

		foreach ( $linkDefs as $name => $page ) {
			// Give grep a chance to find the usages:
			// abusefilter-topnav-home, abusefilter-topnav-recentchanges, abusefilter-topnav-test,
			// abusefilter-topnav-log, abusefilter-topnav-tools, abusefilter-topnav-examine
			$msgName = "abusefilter-topnav-$name";

			$msg = $this->msg( $msgName )->parse();

			if ( $name === $pageType ) {
				$links[] = Xml::tags( 'strong', null, $msg );
			} else {
				$links[] = $this->getLinkRenderer()->makeLink(
					new TitleValue( NS_SPECIAL, $page ),
					new HtmlArmor( $msg )
				);
			}
		}

		$linkStr = $this->msg( 'parentheses' )
			->rawParams( $this->getLanguage()->pipeList( $links ) )
			->text();
		$linkStr = $this->msg( 'abusefilter-topnav' )->parse() . " $linkStr";

		$linkStr = Xml::tags( 'div', [ 'class' => 'mw-abusefilter-navigation' ], $linkStr );

		$this->getOutput()->setSubtitle( $linkStr );
	}
}

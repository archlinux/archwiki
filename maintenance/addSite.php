<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script for adding a site definition into the sites table.
 *
 * The sites table is cached in the local-server cache,
 * so you should reload your webserver and other long-running MediaWiki
 * PHP processes after running this script.
 *
 * @since 1.29
 *
 * @license GPL-2.0-or-later
 * @author Florian Schmidt
 */
class AddSite extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Add a site definition into the sites table.' );

		$this->addArg( 'globalid', 'The global id of the site to add, e.g. "wikipedia".', true );
		$this->addArg( 'group', 'In which group this site should be sorted in.', true );
		$this->addOption( 'language', 'The language code of the site, e.g. "de".', false, true );
		$this->addOption( 'interwiki-id', 'The interwiki ID of the site.', false, true );
		$this->addOption( 'navigation-id', 'The navigation ID of the site.', false, true );
		$this->addOption( 'pagepath', 'The URL to pages of this site, e.g.' .
			' https://example.com/wiki/\$1.', false, true );
		$this->addOption( 'filepath', 'The URL to files of this site, e.g. https://example' .
			'.com/w/\$1.', false, true );
	}

	/**
	 * Imports the site described by the parameters (see self::__construct()) passed to this
	 * maintenance sccript into the sites table of MediaWiki.
	 * @return bool
	 */
	public function execute() {
		$siteStore = MediaWikiServices::getInstance()->getSiteStore();
		if ( method_exists( $siteStore, 'reset' ) ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$siteStore->reset();
		}

		$globalId = $this->getArg( 0 );
		$group = $this->getArg( 1 );
		$language = $this->getOption( 'language' );
		$interwikiId = $this->getOption( 'interwiki-id' );
		$navigationId = $this->getOption( 'navigation-id' );
		$pagepath = $this->getOption( 'pagepath' );
		$filepath = $this->getOption( 'filepath' );

		if ( !is_string( $globalId ) || !is_string( $group ) ) {
			$this->error( 'Arguments globalid and group need to be strings.' );
			return false;
		}

		if ( $siteStore->getSite( $globalId ) !== null ) {
			$this->error( "Site with global id $globalId already exists." );
			return false;
		}

		$site = new MediaWikiSite();
		$site->setGlobalId( $globalId );
		$site->setGroup( $group );
		if ( $language !== null ) {
			$site->setLanguageCode( $language );
		}
		if ( $interwikiId !== null ) {
			$site->addInterwikiId( $interwikiId );
		}
		if ( $navigationId !== null ) {
			$site->addNavigationId( $navigationId );
		}
		if ( $pagepath !== null ) {
			$site->setPagePath( $pagepath );
		}
		if ( $filepath !== null ) {
			$site->setFilePath( $filepath );
		}

		$siteStore->saveSites( [ $site ] );

		if ( method_exists( $siteStore, 'reset' ) ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$siteStore->reset();
		}

		$this->output(
			'Done. Reload the web server and other long-running PHP processes '
			. "to refresh the local-server cache of the sites table.\n"
		);
	}
}

$maintClass = AddSite::class;
require_once RUN_MAINTENANCE_IF_MAIN;

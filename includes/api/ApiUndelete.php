<?php
/**
 * Copyright © 2007 Roan Kattouw "<Firstname>.<Lastname>@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\MainConfigNames;
use MediaWiki\Page\UndeletePage;
use MediaWiki\Page\UndeletePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\Watchlist\WatchlistManager;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * @ingroup API
 */
class ApiUndelete extends ApiBase {

	use ApiWatchlistTrait;

	/** @var UndeletePageFactory */
	private $undeletePageFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param WatchlistManager $watchlistManager
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param UndeletePageFactory $undeletePageFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		WatchlistManager $watchlistManager,
		UserOptionsLookup $userOptionsLookup,
		UndeletePageFactory $undeletePageFactory,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $mainModule, $moduleName );

		// Variables needed in ApiWatchlistTrait trait
		$this->watchlistExpiryEnabled = $this->getConfig()->get( MainConfigNames::WatchlistExpiry );
		$this->watchlistMaxDuration =
			$this->getConfig()->get( MainConfigNames::WatchlistExpiryMaxDuration );
		$this->watchlistManager = $watchlistManager;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->undeletePageFactory = $undeletePageFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function execute() {
		$this->useTransactionalTimeLimit();

		$params = $this->extractRequestParams();

		$user = $this->getUser();
		$block = $user->getBlock( Authority::READ_LATEST );
		if ( $block && $block->isSitewide() ) {
			$this->dieBlocked( $block );
		}

		$titleObj = Title::newFromText( $params['title'] );
		if ( !$titleObj || $titleObj->isExternal() ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['title'] ) ] );
		}

		// Convert timestamps
		if ( !isset( $params['timestamps'] ) ) {
			$params['timestamps'] = [];
		}
		if ( !is_array( $params['timestamps'] ) ) {
			$params['timestamps'] = [ $params['timestamps'] ];
		}
		foreach ( $params['timestamps'] as $i => $ts ) {
			$params['timestamps'][$i] = wfTimestamp( TS_MW, $ts );
		}

		$undeletePage = $this->undeletePageFactory->newUndeletePage(
				$this->wikiPageFactory->newFromTitle( $titleObj ),
				$this->getAuthority()
			)
			->setUndeleteOnlyTimestamps( $params['timestamps'] ?? [] )
			->setUndeleteOnlyFileVersions( $params['fileids'] ?: [] )
			->setTags( $params['tags'] ?: [] );

		if ( $params['undeletetalk'] ) {
			$undeletePage->setUndeleteAssociatedTalk( true );
		}

		$status = $undeletePage->undeleteIfAllowed( $params['reason'] );
		if ( $status->isOK() ) {
			// in case there are warnings
			$this->addMessagesFromStatus( $status );
		} else {
			$this->dieStatus( $status );
		}

		$restoredRevs = $status->getValue()[UndeletePage::REVISIONS_RESTORED];
		$restoredFiles = $status->getValue()[UndeletePage::FILES_RESTORED];

		if ( $restoredRevs === 0 && $restoredFiles === 0 ) {
			// BC for code that predates UndeletePage
			$this->dieWithError( 'apierror-cantundelete' );
		}

		if ( $restoredFiles ) {
			$this->getHookRunner()->onFileUndeleteComplete(
				$titleObj, $params['fileids'],
				$this->getUser(), $params['reason'] );
		}

		$watchlistExpiry = $this->getExpiryFromParams( $params );
		$this->setWatch( $params['watchlist'], $titleObj, $user, null, $watchlistExpiry );

		$info = [
			'title' => $titleObj->getPrefixedText(),
			'revisions' => $restoredRevs,
			'fileversions' => $restoredFiles,
			'reason' => $params['reason']
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $info );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'reason' => '',
			'tags' => [
				ParamValidator::PARAM_TYPE => 'tags',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'timestamps' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'fileids' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'undeletetalk' => false,
		] + $this->getWatchlistParams();
	}

	public function needsToken() {
		return 'csrf';
	}

	protected function getExamplesMessages() {
		return [
			'action=undelete&title=Main%20Page&token=123ABC&reason=Restoring%20main%20page'
				=> 'apihelp-undelete-example-page',
			'action=undelete&title=Main%20Page&token=123ABC' .
				'&timestamps=2007-07-03T22:00:45Z|2007-07-02T19:48:56Z'
				=> 'apihelp-undelete-example-revisions',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Undelete';
	}
}

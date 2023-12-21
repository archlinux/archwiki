<?php
/**
 * Implements Special:Unwatchedpages
 *
 * Copyright © 2005 Ævar Arnfjörð Bjarmason
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
 * @ingroup SpecialPage
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 */

namespace MediaWiki\Specials;

use HtmlArmor;
use ILanguageConverter;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Linker\Linker;
use MediaWiki\SpecialPage\QueryPage;
use MediaWiki\Title\Title;
use Skin;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * A special page that displays a list of pages that are not on anyone's watchlist.
 *
 * @ingroup SpecialPage
 */
class SpecialUnwatchedPages extends QueryPage {

	private LinkBatchFactory $linkBatchFactory;
	private ILanguageConverter $languageConverter;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IConnectionProvider $dbProvider
	 * @param LanguageConverterFactory $languageConverterFactory
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		IConnectionProvider $dbProvider,
		LanguageConverterFactory $languageConverterFactory
	) {
		parent::__construct( 'Unwatchedpages', 'unwatchedpages' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->setDatabaseProvider( $dbProvider );
		$this->languageConverter = $languageConverterFactory->getLanguageConverter( $this->getContentLanguage() );
	}

	public function isExpensive() {
		return true;
	}

	public function isSyndicated() {
		return false;
	}

	/**
	 * Pre-cache page existence to speed up link generation
	 *
	 * @param IDatabase $db
	 * @param IResultWrapper $res
	 */
	public function preprocessResults( $db, $res ) {
		if ( !$res->numRows() ) {
			return;
		}

		$batch = $this->linkBatchFactory->newLinkBatch();
		foreach ( $res as $row ) {
			$batch->add( $row->namespace, $row->title );
		}
		$batch->execute();

		$res->seek( 0 );
	}

	public function getQueryInfo() {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		return [
			'tables' => [ 'page', 'watchlist' ],
			'fields' => [
				'namespace' => 'page_namespace',
				'title' => 'page_title',
				'value' => 'page_namespace'
			],
			'conds' => [
				'wl_title' => null,
				'page_is_redirect' => 0,
				'page_namespace != ' . $dbr->addQuotes( NS_MEDIAWIKI ),
			],
			'join_conds' => [ 'watchlist' => [
				'LEFT JOIN', [ 'wl_title = page_title',
					'wl_namespace = page_namespace' ] ] ]
		];
	}

	protected function sortDescending() {
		return false;
	}

	protected function getOrderFields() {
		return [ 'page_namespace', 'page_title' ];
	}

	/**
	 * Add the JS
	 * @param string|null $par
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->getOutput()->addModules( 'mediawiki.special.unwatchedPages' );
		$this->addHelpLink( 'Help:Watchlist' );
	}

	/**
	 * @param Skin $skin
	 * @param stdClass $result Result row
	 * @return string
	 */
	public function formatResult( $skin, $result ) {
		$nt = Title::makeTitleSafe( $result->namespace, $result->title );
		if ( !$nt ) {
			return Html::element( 'span', [ 'class' => 'mw-invalidtitle' ],
				Linker::getInvalidTitleDescription( $this->getContext(), $result->namespace, $result->title ) );
		}

		$text = $this->languageConverter->convertHtml( $nt->getPrefixedText() );

		$linkRenderer = $this->getLinkRenderer();

		$plink = $linkRenderer->makeKnownLink( $nt, new HtmlArmor( $text ) );
		$wlink = $linkRenderer->makeKnownLink(
			$nt,
			$this->msg( 'watch' )->text(),
			[ 'class' => 'mw-watch-link' ],
			[ 'action' => 'watch' ]
		);

		return $this->getLanguage()->specialList( $plink, $wlink );
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialUnwatchedPages::class, 'SpecialUnwatchedPages' );

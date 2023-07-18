<?php
/**
 * Implements Special:Specialpages
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
 */

use MediaWiki\Html\Html;
use MediaWiki\Parser\ParserOutputFlags;
use Wikimedia\Parsoid\Core\SectionMetadata;
use Wikimedia\Parsoid\Core\TOCData;

/**
 * A special page that lists special pages
 *
 * @ingroup SpecialPage
 */
class SpecialSpecialpages extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'Specialpages' );
	}

	public function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();
		$this->outputHeader();
		$out->setPreventClickjacking( false );
		$out->addModuleStyles( 'mediawiki.special' );

		$groups = $this->getPageGroups();

		if ( $groups === false ) {
			return;
		}

		$this->addHelpLink( 'Help:Special pages' );
		$this->outputPageList( $groups );
	}

	private function getPageGroups() {
		$pages = $this->getSpecialPageFactory()->getUsablePages( $this->getUser() );

		if ( $pages === [] ) {
			// Yeah, that was pointless. Thanks for coming.
			return false;
		}

		// Put them into a sortable array
		$groups = [];
		/** @var SpecialPage $page */
		foreach ( $pages as $page ) {
			$group = $page->getFinalGroupName();
			$groups[$group][$page->getDescription()] = [
				$page->getPageTitle(),
				$page->isRestricted(),
				$page->isCached()
			];
		}

		// Sort
		foreach ( $groups as $group => $sortedPages ) {
			ksort( $groups[$group] );
		}

		// Always move "other" to end
		if ( array_key_exists( 'other', $groups ) ) {
			$other = $groups['other'];
			unset( $groups['other'] );
			$groups['other'] = $other;
		}

		return $groups;
	}

	private function outputPageList( $groups ) {
		$out = $this->getOutput();

		// Legend
		$includesRestrictedPages = false;
		$includesCachedPages = false;
		foreach ( $groups as $group => $sortedPages ) {
			foreach ( $sortedPages as $desc => [ $title, $restricted, $cached ] ) {
				if ( $cached ) {
					$includesCachedPages = true;
				}
				if ( $restricted ) {
					$includesRestrictedPages = true;
				}
			}
		}

		$notes = [];
		if ( $includesRestrictedPages ) {
			$restricedMsg = $this->msg( 'specialpages-note-restricted' );
			if ( !$restricedMsg->isDisabled() ) {
				$notes[] = $restricedMsg->parse();
			}
		}
		if ( $includesCachedPages ) {
			$cachedMsg = $this->msg( 'specialpages-note-cached' );
			if ( !$cachedMsg->isDisabled() ) {
				$notes[] = $cachedMsg->parse();
			}
		}
		if ( $notes !== [] ) {
			$legendHeading = $this->msg( 'specialpages-note-top' )->parse();

			$legend = Html::rawElement(
				'div',
				[ 'class' => [ 'mw-changeslist-legend', 'mw-collapsible', 'mw-specialpages-notes' ] ],
				$legendHeading .
					Html::rawElement( 'div', [ 'class' => 'mw-collapsible-content' ], implode( "\n", $notes ) )
			);

			$out->addHTML( $legend );
			$out->addModuleStyles( 'mediawiki.special.changeslist.legend' );
		}

		// Format table of contents
		$tocData = new TOCData();
		$tocLength = 0;
		foreach ( $groups as $group => $sortedPages ) {
			if ( !str_contains( $group, '/' ) ) {
				++$tocLength;
				$tocData->addSection( new SectionMetadata(
					1,
					2,
					$this->msg( "specialpages-group-$group" )->escaped(),
					$this->getLanguage()->formatNum( $tocLength ),
					(string)$tocLength,
					null,
					null,
					"mw-specialpagesgroup-$group",
					"mw-specialpagesgroup-$group"
				) );
			}
		}

		$pout = new ParserOutput;
		$pout->setTOCData( $tocData );
		$pout->setOutputFlag( ParserOutputFlags::SHOW_TOC );
		$pout->setText( Parser::TOC_PLACEHOLDER );
		$out->addParserOutput( $pout );

		// Format contents
		foreach ( $groups as $group => $sortedPages ) {
			if ( str_contains( $group, '/' ) ) {
				[ $group, $subGroup ] = explode( '/', $group, 2 );
				$out->addHTML( Html::element(
					'h3',
					[ 'class' => "mw-specialpagessubgroup" ],
					$this->msg( "specialpages-group-$group-$subGroup" )->text()
				) . "\n" );
			} else {
				$out->addHTML( Html::element(
					'h2',
					[ 'class' => "mw-specialpagesgroup", 'id' => "mw-specialpagesgroup-$group" ],
					$this->msg( "specialpages-group-$group" )->text()
				) . "\n" );
			}
			$out->addHTML(
				Html::openElement( 'div', [ 'class' => 'mw-specialpages-list' ] )
				. '<ul>'
			);
			foreach ( $sortedPages as $desc => [ $title, $restricted, $cached ] ) {
				$pageClasses = [];
				if ( $cached ) {
					$pageClasses[] = 'mw-specialpagecached';
				}
				if ( $restricted ) {
					$pageClasses[] = 'mw-specialpagerestricted';
				}

				$link = $this->getLinkRenderer()->makeKnownLink( $title, $desc );
				$out->addHTML( Html::rawElement(
						'li',
						[ 'class' => $pageClasses ],
						$link
					) . "\n" );
			}
			$out->addHTML(
				Html::closeElement( 'ul' ) .
				Html::closeElement( 'div' )
			);
		}
	}
}

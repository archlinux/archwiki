<?php
/**
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

namespace MediaWiki\Linter;

use MediaWiki\Content\TextContent;
use MediaWiki\Deferred\DataUpdate;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

class LintUpdate extends DataUpdate {

	private WikiPageFactory $wikiPageFactory;
	private ParserOutputAccess $parserOutputAccess;
	private RenderedRevision $renderedRevision;

	public function __construct(
		WikiPageFactory $wikiPageFactory,
		ParserOutputAccess $parserOutputAccess,
		RenderedRevision $renderedRevision
	) {
		parent::__construct();
		$this->wikiPageFactory = $wikiPageFactory;
		$this->parserOutputAccess = $parserOutputAccess;
		$this->renderedRevision = $renderedRevision;
	}

	public function doUpdate() {
		$rev = $this->renderedRevision->getRevision();
		$mainSlot = $rev->getSlot( SlotRecord::MAIN, RevisionRecord::RAW );

		$page = $this->wikiPageFactory->newFromTitle( $rev->getPage() );

		if ( $page->getLatest() !== $rev->getId() ) {
			// The given revision is no longer the latest revision.
			return;
		}

		$content = $mainSlot->getContent();
		if ( !$content instanceof TextContent ) {
			// Linting is only defined for text
			return;
		}

		$pOptions = $page->makeParserOptions( 'canonical' );
		$pOptions->setUseParsoid();
		$pOptions->setRenderReason( 'LintUpdate' );

		LoggerFactory::getInstance( 'Linter' )->debug(
			'{method}: Parsing {page}',
			[
				'method' => __METHOD__,
				'page' => $page->getTitle()->getPrefixedDBkey(),
				'touched' => $page->getTouched()
			]
		);

		// Don't update the parser cache, to avoid flooding it.
		// This matches the behavior of RefreshLinksJob.
		// However, unlike RefreshLinksJob, we don't parse if we already
		// have the output in the cache. This avoids duplicating the effort
		// of ParsoidCachePrewarmJob / DiscussionTools
		// (note that even with OPT_NO_UPDATE_CACHE we still update the
		// *local* cache, which prevents wasting effort on duplicate parses)
		$this->parserOutputAccess->getParserOutput(
			$page, $pOptions, $rev,
			ParserOutputAccess::OPT_NO_UPDATE_CACHE
		);
	}
}

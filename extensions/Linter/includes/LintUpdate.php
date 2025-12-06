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
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\Stats\StatsFactory;

class LintUpdate extends DataUpdate {

	private StatsFactory $statsFactory;
	private WikiPageFactory $wikiPageFactory;
	private ParserOutputAccess $parserOutputAccess;
	private RenderedRevision $renderedRevision;

	public function __construct(
		StatsFactory $statsFactory,
		WikiPageFactory $wikiPageFactory,
		ParserOutputAccess $parserOutputAccess,
		RenderedRevision $renderedRevision
	) {
		parent::__construct();
		$this->statsFactory = $statsFactory;
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
		$status = $this->parserOutputAccess->getParserOutput(
			$page, $pOptions, $rev,
			ParserOutputAccess::OPT_NO_UPDATE_CACHE
		);
		if ( $status->isOK() ) {
			self::updateParserPerformanceStats(
				$this->statsFactory,
				$status->getValue(),
				/* this is parsoid output: */
				true
			);
		}
	}

	/** Collect statistics for the given ParserOutput. */
	public static function updateParserPerformanceStats(
		StatsFactory $statsFactory, ParserOutput $po, bool $useParsoid
	) {
		$limitReport = $po->getLimitReportData();
		$lnSize = strval( ceil( log(
			( $limitReport['limitreport-revisionsize'][0] ?? 0 ) +
			( $limitReport['limitreport-postexpandincludesize'][0] ?? 0 ) +
			// add 1 to avoid log(0)
			1,
			// log_10(size) or "how many digits in the size"
			10
		) ) );
		$labels = [
			'parser' => $useParsoid ? 'parsoid' : 'legacy',
			// T393400: add label w/ buckets based on
			// original wikitext size (revision size + post-include size)
			'wikitext_size_exp' => $lnSize,
		];
		$cpuTime = $po->getTimeProfile( 'cpu' );
		$wallTime = $po->getTimeProfile( 'wall' );
		if ( $cpuTime === null || $wallTime === null ) {
			return;
		}
		$statCounter = $statsFactory
			->getCounter( "lintupdate_parse_count" )
			->setLabels( $labels )
			->increment();
		// Collect timing comparison data (T393399/T393400)
		// Average time can be computed by dividing this counter (over some
		// time period) by the $statsCounter with label 'cache_miss' (for
		// the same time period).
		$statCpuTime = $statsFactory
			->getCounter( "lintupdate_parse_cpu_seconds" )
			->setLabels( $labels )
			->incrementBy( $cpuTime );
		$statWallTime = $statsFactory
			->getCounter( "lintupdate_parse_wall_seconds" )
			->setLabels( $labels )
			->incrementBy( $wallTime );

		// Collect HTML size comparison data
		$statHtmlSize = $statsFactory
			->getCounter( "lintupdate_parse_html_bytes" )
			->setLabels( $labels )
			->incrementBy( strlen( $po->getRawText() ?? '' ) );
	}
}

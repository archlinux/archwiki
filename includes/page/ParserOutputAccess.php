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
namespace MediaWiki\Page;

use IBufferingStatsdDataFactory;
use InvalidArgumentException;
use MediaWiki\Logger\Spi as LoggerSpi;
use MediaWiki\Parser\RevisionOutputCache;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionRenderer;
use ParserCache;
use ParserOptions;
use ParserOutput;
use PoolCounterWork;
use PoolWorkArticleView;
use PoolWorkArticleViewCurrent;
use PoolWorkArticleViewOld;
use Status;
use TitleFormatter;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\ILBFactory;

/**
 * Service for getting rendered output of a given page.
 *
 * This is a high level service, encapsulating concerns like caching
 * and stampede protection via PoolCounter.
 *
 * @since 1.36
 * @ingroup Page
 */
class ParserOutputAccess {

	/**
	 * @var int Do not check the cache before parsing (force parse)
	 */
	public const OPT_NO_CHECK_CACHE = 1;

	/** @var int Alias for NO_CHECK_CACHE */
	public const OPT_FORCE_PARSE = self::OPT_NO_CHECK_CACHE;

	/**
	 * @var int Do not update the cache after parsing.
	 */
	public const OPT_NO_UPDATE_CACHE = 2;

	/**
	 * @var int Bypass audience check for deleted/suppressed revisions.
	 *      The caller is responsible for ensuring that unauthorized access is prevented.
	 *      If not set, output generation will fail if the revision is not public.
	 */
	public const OPT_NO_AUDIENCE_CHECK = 4;

	/**
	 * @var int Do not check the cache before parsing,
	 *      and do not update the cache after parsing (not cacheable).
	 */
	public const OPT_NO_CACHE = self::OPT_NO_UPDATE_CACHE | self::OPT_NO_CHECK_CACHE;

	/** @var string Do not read or write any cache */
	private const CACHE_NONE = 'none';

	/** @var string Use primary cache */
	private const CACHE_PRIMARY = 'primary';

	/** @var string Use secondary cache */
	private const CACHE_SECONDARY = 'secondary';

	/** @var ParserCache */
	private $primaryCache;

	/**
	 * @var RevisionOutputCache
	 */
	private $secondaryCache;

	/**
	 * In cases that an extension tries to get the same ParserOutput of
	 * the page right after it was parsed (T301310).
	 * @var ParserOutput[]
	 */
	private $localCache = [];

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionRenderer */
	private $revisionRenderer;

	/** @var IBufferingStatsdDataFactory */
	private $statsDataFactory;

	/** @var ILBFactory */
	private $lbFactory;

	/** @var LoggerSpi */
	private $loggerSpi;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var TitleFormatter */
	private $titleFormatter;

	/**
	 * @param ParserCache $primaryCache
	 * @param RevisionOutputCache $secondaryCache
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionRenderer $revisionRenderer
	 * @param IBufferingStatsdDataFactory $statsDataFactory
	 * @param ILBFactory $lbFactory
	 * @param LoggerSpi $loggerSpi
	 * @param WikiPageFactory $wikiPageFactory
	 * @param TitleFormatter $titleFormatter
	 */
	public function __construct(
		ParserCache $primaryCache,
		RevisionOutputCache $secondaryCache,
		RevisionLookup $revisionLookup,
		RevisionRenderer $revisionRenderer,
		IBufferingStatsdDataFactory $statsDataFactory,
		ILBFactory $lbFactory,
		LoggerSpi $loggerSpi,
		WikiPageFactory $wikiPageFactory,
		TitleFormatter $titleFormatter
	) {
		$this->primaryCache = $primaryCache;
		$this->secondaryCache = $secondaryCache;
		$this->revisionLookup = $revisionLookup;
		$this->revisionRenderer = $revisionRenderer;
		$this->statsDataFactory = $statsDataFactory;
		$this->lbFactory = $lbFactory;
		$this->loggerSpi = $loggerSpi;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * Use a cache?
	 *
	 * @param PageRecord $page
	 * @param RevisionRecord|null $rev
	 *
	 * @return string One of the CACHE_XXX constants.
	 */
	private function shouldUseCache(
		PageRecord $page,
		?RevisionRecord $rev
	) {
		if ( $rev && !$rev->getId() ) {
			// The revision isn't from the database, so the output can't safely be cached.
			return self::CACHE_NONE;
		}

		// NOTE: Keep in sync with ParserWikiPage::shouldCheckParserCache().
		// NOTE: when we allow caching of old revisions in the future,
		//       we must not allow caching of deleted revisions.

		$wikiPage = $this->wikiPageFactory->newFromTitle( $page );
		if ( !$page->exists() || !$wikiPage->getContentHandler()->isParserCacheSupported() ) {
			return self::CACHE_NONE;
		}

		$isOld = $rev && $rev->getId() !== $page->getLatest();
		if ( !$isOld ) {
			return self::CACHE_PRIMARY;
		}

		if ( !$rev->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
			// deleted/suppressed revision
			return self::CACHE_NONE;
		}

		return self::CACHE_SECONDARY;
	}

	/**
	 * Returns the rendered output for the given page if it is present in the cache.
	 *
	 * @param PageRecord $page
	 * @param ParserOptions $parserOptions
	 * @param RevisionRecord|null $revision
	 * @param int $options Bitfield using the OPT_XXX constants
	 *
	 * @return ParserOutput|null
	 */
	public function getCachedParserOutput(
		PageRecord $page,
		ParserOptions $parserOptions,
		?RevisionRecord $revision = null,
		int $options = 0
	): ?ParserOutput {
		$isOld = $revision && $revision->getId() !== $page->getLatest();
		$useCache = $this->shouldUseCache( $page, $revision );
		$classCacheKey = $this->primaryCache->makeParserOutputKey( $page, $parserOptions );

		if ( $useCache === self::CACHE_PRIMARY ) {
			if ( isset( $this->localCache[$classCacheKey] ) && !$isOld ) {
				return $this->localCache[$classCacheKey];
			}
			$output = $this->primaryCache->get( $page, $parserOptions );
		} elseif ( $useCache === self::CACHE_SECONDARY && $revision ) {
			$output = $this->secondaryCache->get( $revision, $parserOptions );
		} else {
			$output = null;
		}

		if ( $output && !$isOld ) {
			$this->localCache[$classCacheKey] = $output;
		}

		if ( $output ) {
			$this->statsDataFactory->increment( "ParserOutputAccess.Cache.$useCache.hit" );
		} else {
			$this->statsDataFactory->increment( "ParserOutputAccess.Cache.$useCache.miss" );
		}

		return $output ?: null; // convert false to null
	}

	/**
	 * Returns the rendered output for the given page.
	 * Caching and concurrency control is applied.
	 *
	 * @param PageRecord $page
	 * @param ParserOptions $parserOptions
	 * @param RevisionRecord|null $revision
	 * @param int $options Bitfield using the OPT_XXX constants
	 *
	 * @return Status containing a ParserOutput if no error occurred.
	 *         Well known errors and warnings include the following messages:
	 *         - 'view-pool-dirty-output' (warning) The output is dirty (from a stale cache entry).
	 *         - 'view-pool-contention' (warning) Dirty output was returned immediately instead of
	 *           waiting to acquire a work lock (when "fast stale" mode is enabled in PoolCounter).
	 *         - 'view-pool-timeout' (warning) Dirty output was returned after failing to acquire
	 *           a work lock (got QUEUE_FULL or TIMEOUT from PoolCounter).
	 *         - 'pool-queuefull' (error) unable to acquire work lock, and no cached content found.
	 *         - 'pool-timeout' (error) unable to acquire work lock, and no cached content found.
	 *         - 'pool-servererror' (error) PoolCounterWork failed due to a lock service error.
	 *         - 'pool-unknownerror' (error) PoolCounterWork failed for an unknown reason.
	 *         - 'nopagetext' (error) The page does not exist
	 */
	public function getParserOutput(
		PageRecord $page,
		ParserOptions $parserOptions,
		?RevisionRecord $revision = null,
		int $options = 0
	): Status {
		$error = $this->checkPreconditions( $page, $revision, $options );
		if ( $error ) {
			$this->statsDataFactory->increment( "ParserOutputAccess.Case.error" );
			return $error;
		}

		$isOld = $revision && $revision->getId() !== $page->getLatest();
		if ( $isOld ) {
			$this->statsDataFactory->increment( 'ParserOutputAccess.Case.old' );
		} else {
			$this->statsDataFactory->increment( 'ParserOutputAccess.Case.current' );
		}

		if ( !( $options & self::OPT_NO_CHECK_CACHE ) ) {
			$output = $this->getCachedParserOutput( $page, $parserOptions, $revision );
			if ( $output ) {
				return Status::newGood( $output );
			}
		}

		if ( !$revision ) {
			$revId = $page->getLatest();
			$revision = $revId ? $this->revisionLookup->getRevisionById( $revId ) : null;

			if ( !$revision ) {
				$this->statsDataFactory->increment( "ParserOutputAccess.Status.norev" );
				return Status::newFatal( 'missing-revision', $revId );
			}
		}

		$work = $this->newPoolWorkArticleView( $page, $parserOptions, $revision, $options );
		/** @var Status $status */
		$status = $work->execute();
		$output = $status->getValue();
		Assert::postcondition( $output || !$status->isOK(), 'Worker returned invalid status' );

		if ( $output && !$isOld ) {
			$classCacheKey = $this->primaryCache->makeParserOutputKey( $page, $parserOptions );
			$this->localCache[$classCacheKey] = $output;
		}

		if ( $status->isGood() ) {
			$this->statsDataFactory->increment( 'ParserOutputAccess.Status.good' );
		} elseif ( $status->isOK() ) {
			$this->statsDataFactory->increment( 'ParserOutputAccess.Status.ok' );
		} else {
			$this->statsDataFactory->increment( 'ParserOutputAccess.Status.error' );
		}

		return $status;
	}

	/**
	 * @param PageRecord $page
	 * @param RevisionRecord|null $revision
	 * @param int $options
	 *
	 * @return Status|null
	 */
	private function checkPreconditions(
		PageRecord $page,
		?RevisionRecord $revision = null,
		int $options = 0
	): ?Status {
		if ( !$page->exists() ) {
			return Status::newFatal( 'nopagetext' );
		}

		if ( !( $options & self::OPT_NO_UPDATE_CACHE ) && $revision && !$revision->getId() ) {
			throw new InvalidArgumentException(
				'The revision does not have a known ID. Use NO_CACHE.'
			);
		}

		if ( $revision && $revision->getPageId() !== $page->getId() ) {
			throw new InvalidArgumentException(
				'The revision does not belong to the given page.'
			);
		}

		if ( $revision && !( $options & self::OPT_NO_AUDIENCE_CHECK ) ) {
			// NOTE: If per-user checks are desired, the caller should perform them and
			//       then set OPT_NO_AUDIENCE_CHECK if they passed.
			if ( !$revision->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
				return Status::newFatal(
					'missing-revision-permission',
					$revision->getId(),
					$revision->getTimestamp(),
					$this->titleFormatter->getPrefixedDBkey( $page )
				);
			}
		}

		return null;
	}

	/**
	 * @param PageRecord $page
	 * @param ParserOptions $parserOptions
	 * @param RevisionRecord $revision
	 * @param int $options
	 *
	 * @return PoolCounterWork
	 */
	private function newPoolWorkArticleView(
		PageRecord $page,
		ParserOptions $parserOptions,
		RevisionRecord $revision,
		int $options
	): PoolCounterWork {
		$useCache = $this->shouldUseCache( $page, $revision );

		switch ( $useCache ) {
			case self::CACHE_PRIMARY:
				$this->statsDataFactory->increment( 'ParserOutputAccess.PoolWork.Current' );
				$parserCacheMetadata = $this->primaryCache->getMetadata( $page );
				$cacheKey = $this->primaryCache->makeParserOutputKey( $page, $parserOptions,
					$parserCacheMetadata ? $parserCacheMetadata->getUsedOptions() : null
				);

				$workKey = $cacheKey . ':revid:' . $revision->getId();

				return new PoolWorkArticleViewCurrent(
					$workKey,
					$page,
					$revision,
					$parserOptions,
					$this->revisionRenderer,
					$this->primaryCache,
					$this->lbFactory,
					$this->loggerSpi,
					$this->wikiPageFactory,
					!( $options & self::OPT_NO_UPDATE_CACHE )
				);

			case self::CACHE_SECONDARY:
				$this->statsDataFactory->increment( 'ParserOutputAccess.PoolWork.Old' );
				$workKey = $this->secondaryCache->makeParserOutputKey( $revision, $parserOptions );
				return new PoolWorkArticleViewOld(
					$workKey,
					$this->secondaryCache,
					$revision,
					$parserOptions,
					$this->revisionRenderer,
					$this->loggerSpi
				);

			default:
				$this->statsDataFactory->increment( 'ParserOutputAccess.PoolWork.Uncached' );
				$workKey = $this->secondaryCache->makeParserOutputKeyOptionalRevId( $revision, $parserOptions );
				return new PoolWorkArticleView(
					$workKey,
					$revision,
					$parserOptions,
					$this->revisionRenderer,
					$this->loggerSpi
				);
		}

		// unreachable
	}

}

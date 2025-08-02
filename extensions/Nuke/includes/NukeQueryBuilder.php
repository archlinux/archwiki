<?php

namespace MediaWiki\Extension\Nuke;

use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\NamespaceInfo;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeMatch;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class NukeQueryBuilder {

	/**
	 * Constant to run queries on the revision table.
	 */
	public const TABLE_REVISION = 'revision';
	/**
	 * Constant to run queries on the recentchanges table.
	 */
	public const TABLE_RECENTCHANGES = 'recentchanges';

	/**
	 * Default fields to include in the result set. Must be fields that can be found
	 * in both revision and recentchanges queries.
	 */
	private const DEFAULT_FIELDS = [ 'page_id', 'page_title', 'page_namespace', 'actor_name' ];

	private IReadableDatabase $readableDatabase;
	private Config $config;
	private NamespaceInfo $namespaceInfo;
	private Language $contentLanguage;

	/**
	 * The query builder for this query. Set once in the constructor, and should never be
	 * reassigned afterward.
	 *
	 * @var SelectQueryBuilder|null
	 */
	private ?SelectQueryBuilder $selectQueryBuilder = null;
	/**
	 * The table being used.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * @param IReadableDatabase $readableDatabase
	 * @param Config $config
	 * @param NamespaceInfo $namespaceInfo
	 * @param Language $contentLanguage
	 * @param string $table The table to use. Must be one of
	 *   {@link TABLE_REVISION} or {@link TABLE_RECENTCHANGES}.
	 */
	public function __construct(
		IReadableDatabase $readableDatabase,
		Config $config,
		NamespaceInfo $namespaceInfo,
		Language $contentLanguage,
		string $table
	) {
		$this->readableDatabase = $readableDatabase;
		$this->config = $config;
		$this->namespaceInfo = $namespaceInfo;
		$this->contentLanguage = $contentLanguage;

		switch ( $table ) {
			case self::TABLE_REVISION:
				$this->fromRevisionTableQuery();
				break;
			case self::TABLE_RECENTCHANGES:
				$this->fromRecentChangesTableQuery();
				break;
			default:
				throw new LogicException( "Invalid Nuke table target: $table" );
		}
		$this->table = $table;
	}

	private function fromRevisionTableQuery() {
		$dbr = $this->readableDatabase;
		$this->selectQueryBuilder = $dbr->newSelectQueryBuilder()
			->select( self::DEFAULT_FIELDS )
			->distinct()
			->from( self::TABLE_REVISION )
			->join( 'actor', null, 'actor_id=rev_actor' )
			->join( 'page', null, 'page_id=rev_page' )
			->where( [
				$dbr->expr( 'rev_parent_id', '=', 0 )
			] )
			->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_DESC )
			->setMaxExecutionTime(
				$this->config->get( MainConfigNames::MaxExecutionTimeForExpensiveQueries )
			);
	}

	private function fromRecentChangesTableQuery() {
		$dbr = $this->readableDatabase;
		$this->selectQueryBuilder = $dbr->newSelectQueryBuilder()
			->select( self::DEFAULT_FIELDS )
			->from( self::TABLE_RECENTCHANGES )
			->join( 'actor', null, 'actor_id=rc_actor' )
			->join( 'page', null, 'page_id=rc_cur_id' )
			->where( [
				$dbr->expr( 'rc_source', '=', 'mw.new' )->orExpr(
					$dbr->expr( 'rc_log_type', '=', 'upload' )
						->and( 'rc_log_action', '=', 'upload' )
				)
			] )
			->orderBy( 'rc_timestamp', SelectQueryBuilder::SORT_DESC )
			->setMaxExecutionTime(
				$this->config->get( MainConfigNames::MaxExecutionTimeForExpensiveQueries )
			);
	}

	/**
	 * Limit the number of rows returned.
	 *
	 * @param int $limit The limit to follow
	 * @return $this
	 */
	public function limit( int $limit ): self {
		$this->selectQueryBuilder->limit( $limit );
		return $this;
	}

	/**
	 * Filter based on one or multiple actor names.
	 *
	 * If `$actors` is an empty array, this is a no-op.
	 *
	 * @param string|string[] $actors The actor names to filter to
	 * @return self
	 */
	public function filterActor( $actors ): self {
		if ( !is_array( $actors ) ) {
			$actors = [ $actors ];
		} elseif ( !count( $actors ) ) {
			return $this;
		}

		$this->selectQueryBuilder->andWhere( [ 'actor_name' => $actors ] );
		return $this;
	}

	/**
	 * Filter based on namespaces.
	 *
	 * If `$namespaces` is null, this is a no-op.
	 *
	 * @param int[]|null $namespaces The namespace IDs to filter to
	 * @return $this
	 */
	public function filterNamespaces( ?array $namespaces ): self {
		if ( $namespaces == null ) {
			return $this;
		}

		$dbr = $this->readableDatabase;
		$namespaceConditions = array_map( static function ( $ns ) use ( $dbr ) {
			return $dbr->expr( 'page_namespace', '=', $ns );
		}, $namespaces );
		$this->selectQueryBuilder->andWhere( $dbr->orExpr( $namespaceConditions ) );
		return $this;
	}

	/**
	 * Filter based on a page title pattern.
	 *
	 * If `$pattern` is an empty string, this is a no-op.
	 *
	 * @param string $pattern The pattern to use
	 * @param int[]|null $namespaces The namespace IDs to filter to
	 * @return $this
	 */
	public function filterPattern( string $pattern, ?array $namespaces = null ): self {
		$pattern = trim( $pattern );

		if ( $pattern == '' ) {
			return $this;
		}

		$pattern = preg_replace( '/ +/', '`_', $pattern );
		$pattern = preg_replace( '/\\\\([%_])/', '`$1', $pattern );

		$overriddenNamespaces = $this->getOverriddenNamespaces( $namespaces );
		if ( count( $overriddenNamespaces ) ) {
			$this->filterPatternWithOverriddenNamespaces( $pattern, $namespaces, $overriddenNamespaces );
		} else {
			$this->filterSimplePattern( $pattern );
		}

		return $this;
	}

	/**
	 * Filter based on a minimum page size.
	 *
	 * If `$minPageSize` is 0 or negative, this is a no-op.
	 *
	 * @param int $minPageSize The minimum size (in bytes) that a page must be to be included
	 * @return $this
	 */
	public function filterByMinPageSize( int $minPageSize ): self {
		if ( $minPageSize <= 0 ) {
			// No filtering if minPageSize is 0 or negative, because
			// this would do nothing if we added it to the query
			// anyway.
			return $this;
		}

		$dbr = $this->readableDatabase;
		// Add a condition to filter by page length if minPageSize is greater than 0
		$this->selectQueryBuilder->andWhere(
			$dbr->expr( 'page_len', '>=', $minPageSize )
		);

		return $this;
	}

	/**
	 * Filter based on a maximum page size.
	 *
	 * If `$maxPageSize` is negative, this is a no-op.
	 * It is possible for a page to exist with 0 bytes, so having a
	 * max of 0 is allowed.
	 *
	 * @param int $maxPageSize The maximum size (in bytes) that a page must be to be included
	 * @return $this
	 */
	public function filterByMaxPageSize( int $maxPageSize ): self {
		if ( $maxPageSize < 0 ) {
			// No filtering if maxPageSize is negative as this doesn't make sense
			// The user is told this will be ignored in the UI
			return $this;
		}

		$dbr = $this->readableDatabase;
		// Add a condition to filter by page length if maxPageSize is greater than 0
		$this->selectQueryBuilder->andWhere(
			$dbr->expr( 'page_len', '<=', $maxPageSize )
		);

		return $this;
	}

	/**
	 * Get an array of all namespaces in `$namespaces` (or all namespaces, if `$namespaces` is
	 * null) where their `$wgCapitalLinksOverride` configuration does not match the wiki's
	 * `$wgCapitalLinks` option. Used to determine whether the pattern should be capitalized
	 * for some namespaces.
	 *
	 * When `$namespaces` is set, the return value will always be a subset of it.
	 *
	 * @param int[]|null $namespaces The namespaces to check for
	 * @return int[]
	 */
	protected function getOverriddenNamespaces( ?array $namespaces ): array {
		$overriddenNamespaces = [];
		$capitalLinks = $this->config->get( MainConfigNames::CapitalLinks );
		$capitalLinkOverrides = $this->config->get( MainConfigNames::CapitalLinkOverrides );
		// If there are any capital-overridden namespaces, keep track of them. "overridden"
		// here means the namespace-specific value is not equal to $wgCapitalLinks.
		foreach ( $capitalLinkOverrides as $nsId => $nsOverridden ) {
			if ( $nsOverridden !== $capitalLinks && (
					$namespaces == null || in_array( $nsId, $namespaces )
				) ) {
				$overriddenNamespaces[] = $nsId;
			}
		}

		return $overriddenNamespaces;
	}

	/**
	 * Add a WHERE condition on the query, filtering pages by a given pattern.
	 * This function performs capitalization as needed, to ensure that the pattern has a capital
	 * first character when `$wgCapitalLinks` is not disabled (since all titles are stored in the
	 * database with the first letter capitalized).
	 *
	 * @param string $pattern The pattern to use
	 * @return void
	 */
	protected function filterSimplePattern( string $pattern ) {
		$pattern = $this->namespaceInfo->isCapitalized( NS_MAIN ) ?
			$this->contentLanguage->ucfirst( $pattern ) : $pattern;

		$this->selectQueryBuilder->andWhere(
			$this->readableDatabase->expr(
				'page_title',
				IExpression::LIKE,
				new LikeValue(
					new LikeMatch( $pattern )
				)
			)
		);
	}

	/**
	 * Add a WHERE condition on the query, filtering pages by a given pattern.
	 * Depending on the namespaces selected (or all of them, if applicable), some
	 * namespaces may be case-sensitive (configured via `$wgCapitalLinkOverrides`).
	 * This function performs conversion on the pattern as needed to ensure that
	 * matching is always case-sensitive for namespaces which are also case-sensitive,
	 * or capitalizes the first character of the pattern if it isn't (since all titles
	 * are stored in the database with the first letter capitalized).
	 *
	 * @param string $pattern The pattern to use
	 * @param int[]|null $namespaces The namespaces selected by the user
	 * @param int[] $overriddenNamespaces The list of namespaces which have entries in the
	 *   `$wgCapitalLinkOverrides` configuration variable that varies from the default.
	 * @return void
	 */
	protected function filterPatternWithOverriddenNamespaces(
		string $pattern,
		?array $namespaces,
		array $overriddenNamespaces
	) {
		$dbr = $this->readableDatabase;

		// If there are overridden namespaces, they have to be converted
		// on a case-by-case basis.

		// Our scope should only be limited to the namespaces selected by the user,
		// or all namespaces (when $namespaces == null).
		$validNamespaces = $namespaces == null ?
			$this->namespaceInfo->getValidNamespaces() :
			$namespaces;
		$nonOverriddenNamespaces = [];
		foreach ( $validNamespaces as $ns ) {
			if ( !in_array( $ns, $overriddenNamespaces ) ) {
				// Put all namespaces that aren't overridden in $nonOverriddenNamespaces.
				$nonOverriddenNamespaces[] = $ns;
			}
		}

		$patternSpecific = $this->namespaceInfo->isCapitalized( $overriddenNamespaces[0] ) ?
			$this->contentLanguage->ucfirst( $pattern ) : $pattern;
		$orConditions = [
			$dbr->expr(
				'page_title', IExpression::LIKE, new LikeValue(
					new LikeMatch( $patternSpecific )
				)
			)->and(
			// IN condition
				'page_namespace', '=', $overriddenNamespaces
			)
		];
		if ( count( $nonOverriddenNamespaces ) ) {
			$patternStandard = $this->namespaceInfo->isCapitalized( $nonOverriddenNamespaces[0] ) ?
				$this->contentLanguage->ucfirst( $pattern ) : $pattern;
			$orConditions[] = $dbr->expr(
				'page_title', IExpression::LIKE, new LikeValue(
					new LikeMatch( $patternStandard )
				)
			)->and(
				// IN condition, with the non-overridden namespaces.
				// If the default is case-sensitive namespaces, $pattern's first
				// character is turned lowercase. Otherwise, it is turned uppercase.
				'page_namespace', '=', $nonOverriddenNamespaces
			);
		}
		$this->selectQueryBuilder->andWhere( $dbr->orExpr( $orConditions ) );
	}

	/**
	 * Filter based on timestamp, only allowing creations which are after `$timestamp` (inclusive).
	 *
	 * @param int $timestamp The minimum timestamp that a page creation must be to be included
	 * @return $this
	 */
	public function filterFromTimestamp( int $timestamp ): self {
		$dbr = $this->readableDatabase;
		if ( $this->table === 'revision' ) {
			$this->selectQueryBuilder->andWhere(
				$dbr->expr( 'rev_timestamp', '>=', $dbr->timestamp( $timestamp ) )
			);
		} else {
			$this->selectQueryBuilder->andWhere(
				$dbr->expr( 'rc_timestamp', '>=', $dbr->timestamp( $timestamp ) )
			);
		}
		return $this;
	}

	/**
	 * Filter based on timestamp, only allowing creations which are before `$timestamp` (exclusive).
	 *
	 * @param int $timestamp The maximum timestamp that a page creation must be to be included
	 * @return $this
	 */
	public function filterToTimestamp( int $timestamp ): self {
		$dbr = $this->readableDatabase;
		if ( $this->table === 'revision' ) {
			$this->selectQueryBuilder->andWhere(
				$dbr->expr( 'rev_timestamp', '<', $dbr->timestamp( $timestamp ) )
			);
		} else {
			$this->selectQueryBuilder->andWhere(
				$dbr->expr( 'rc_timestamp', '<', $dbr->timestamp( $timestamp ) )
			);
		}
		return $this;
	}

	/**
	 * Get a copy of the {@link SelectQueryBuilder} for this instance.
	 *
	 * @return SelectQueryBuilder
	 */
	public function build(): SelectQueryBuilder {
		return clone $this->selectQueryBuilder;
	}

}

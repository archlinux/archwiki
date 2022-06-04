<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

use ContentHandler;
use Diff;
use Language;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\TextExtractor;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MWException;
use Parser;
use ParserOptions;
use Psr\Log\LoggerInterface;
use stdClass;
use StringUtils;
use TextContent;
use Title;
use UnifiedDiffFormatter;
use User;
use WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;

/**
 * Service used to compute lazy-loaded variable.
 * @internal
 */
class LazyVariableComputer {
	public const SERVICE_NAME = 'AbuseFilterLazyVariableComputer';

	/**
	 * @var float The amount of time to subtract from profiling
	 * @todo This is a hack
	 */
	public static $profilingExtraTime = 0;

	/** @var TextExtractor */
	private $textExtractor;

	/** @var AbuseFilterHookRunner */
	private $hookRunner;

	/** @var LoggerInterface */
	private $logger;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var WANObjectCache */
	private $wanCache;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var Language */
	private $contentLanguage;

	/** @var Parser */
	private $parser;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var string */
	private $wikiID;

	/**
	 * @param TextExtractor $textExtractor
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param LoggerInterface $logger
	 * @param ILoadBalancer $loadBalancer
	 * @param WANObjectCache $wanCache
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionStore $revisionStore
	 * @param Language $contentLanguage
	 * @param Parser $parser
	 * @param UserEditTracker $userEditTracker
	 * @param UserGroupManager $userGroupManager
	 * @param PermissionManager $permissionManager
	 * @param string $wikiID
	 */
	public function __construct(
		TextExtractor $textExtractor,
		AbuseFilterHookRunner $hookRunner,
		LoggerInterface $logger,
		ILoadBalancer $loadBalancer,
		WANObjectCache $wanCache,
		RevisionLookup $revisionLookup,
		RevisionStore $revisionStore,
		Language $contentLanguage,
		Parser $parser,
		UserEditTracker $userEditTracker,
		UserGroupManager $userGroupManager,
		PermissionManager $permissionManager,
		string $wikiID
	) {
		$this->textExtractor = $textExtractor;
		$this->hookRunner = $hookRunner;
		$this->logger = $logger;
		$this->loadBalancer = $loadBalancer;
		$this->wanCache = $wanCache;
		$this->revisionLookup = $revisionLookup;
		$this->revisionStore = $revisionStore;
		$this->contentLanguage = $contentLanguage;
		$this->parser = $parser;
		$this->userEditTracker = $userEditTracker;
		$this->userGroupManager = $userGroupManager;
		$this->permissionManager = $permissionManager;
		$this->wikiID = $wikiID;
	}

	/**
	 * XXX: $getVarCB is a hack to hide the cyclic dependency with VariablesManager. See T261069 for possible
	 * solutions. This might also be merged into VariablesManager, but it would bring a ton of dependencies.
	 * @todo Should we remove $vars parameter (check hooks)?
	 *
	 * @param LazyLoadedVariable $var
	 * @param VariableHolder $vars
	 * @param callable $getVarCB
	 * @phan-param callable(string $name):AFPData $getVarCB
	 * @return AFPData
	 * @throws MWException
	 */
	public function compute( LazyLoadedVariable $var, VariableHolder $vars, callable $getVarCB ) {
		$parameters = $var->getParameters();
		$varMethod = $var->getMethod();
		$result = null;

		if ( !$this->hookRunner->onAbuseFilter_interceptVariable(
			$varMethod,
			$vars,
			$parameters,
			$result
		) ) {
			return $result instanceof AFPData
				? $result : AFPData::newFromPHPVar( $result );
		}

		switch ( $varMethod ) {
			case 'diff':
				$text1Var = $parameters['oldtext-var'];
				$text2Var = $parameters['newtext-var'];
				$text1 = $getVarCB( $text1Var )->toString();
				$text2 = $getVarCB( $text2Var )->toString();
				// T74329: if there's no text, don't return an array with the empty string
				$text1 = $text1 === '' ? [] : explode( "\n", $text1 );
				$text2 = $text2 === '' ? [] : explode( "\n", $text2 );
				$diffs = new Diff( $text1, $text2 );
				$format = new UnifiedDiffFormatter();
				$result = $format->format( $diffs );
				break;
			case 'diff-split':
				$diff = $getVarCB( $parameters['diff-var'] )->toString();
				$line_prefix = $parameters['line-prefix'];
				$diff_lines = explode( "\n", $diff );
				$result = [];
				foreach ( $diff_lines as $line ) {
					if ( substr( $line, 0, 1 ) === $line_prefix ) {
						$result[] = substr( $line, strlen( $line_prefix ) );
					}
				}
				break;
			case 'links-from-wikitext':
				// This should ONLY be used when sharing a parse operation with the edit.

				/** @var WikiPage $article */
				$article = $parameters['article'];
				if ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					// Shared with the edit, don't count it in profiling
					$startTime = microtime( true );
					$textVar = $parameters['text-var'];

					$new_text = $getVarCB( $textVar )->toString();
					$content = ContentHandler::makeContent( $new_text, $article->getTitle() );
					$editInfo = $article->prepareContentForEdit(
						$content,
						null,
						$parameters['contextUser']
					);
					$result = array_keys( $editInfo->output->getExternalLinks() );
					self::$profilingExtraTime += ( microtime( true ) - $startTime );
					break;
				}
			// Otherwise fall back to database
			case 'links-from-wikitext-or-database':
				// TODO: use Content object instead, if available!
				/** @var WikiPage $article */
				$article = $article ?? $parameters['article'];

				// this inference is ugly, but the name isn't accessible from here
				// and we only want this for debugging
				$varName = strpos( $parameters['text-var'], 'old_' ) === 0 ? 'old_links' : 'all_links';
				if ( $vars->forFilter ) {
					$this->logger->debug( "Loading $varName from DB" );
					$links = $this->getLinksFromDB( $article );
				} elseif ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					$this->logger->debug( "Loading $varName from Parser" );
					$textVar = $parameters['text-var'];

					$wikitext = $getVarCB( $textVar )->toString();
					$editInfo = $this->parseNonEditWikitext(
						$wikitext,
						$article,
						$parameters['contextUser']
					);
					$links = array_keys( $editInfo->output->getExternalLinks() );
				} else {
					// TODO: Get links from Content object. But we don't have the content object.
					// And for non-text content, $wikitext is usually not going to be a valid
					// serialization, but rather some dummy text for filtering.
					$links = [];
				}

				$result = $links;
				break;
			case 'link-diff-added':
			case 'link-diff-removed':
				$oldLinkVar = $parameters['oldlink-var'];
				$newLinkVar = $parameters['newlink-var'];

				$oldLinks = $getVarCB( $oldLinkVar )->toNative();
				$newLinks = $getVarCB( $newLinkVar )->toNative();

				if ( $varMethod === 'link-diff-added' ) {
					$result = array_diff( $newLinks, $oldLinks );
				}
				if ( $varMethod === 'link-diff-removed' ) {
					$result = array_diff( $oldLinks, $newLinks );
				}
				break;
			case 'parse-wikitext':
				// Should ONLY be used when sharing a parse operation with the edit.
				// TODO: use Content object instead, if available!
				/* @var WikiPage $article */
				$article = $parameters['article'];
				if ( $article->getContentModel() === CONTENT_MODEL_WIKITEXT ) {
					// Shared with the edit, don't count it in profiling
					$startTime = microtime( true );
					$textVar = $parameters['wikitext-var'];

					$new_text = $getVarCB( $textVar )->toString();
					$content = ContentHandler::makeContent( $new_text, $article->getTitle() );
					$editInfo = $article->prepareContentForEdit(
						$content,
						null,
						$parameters['contextUser']
					);
					if ( isset( $parameters['pst'] ) && $parameters['pst'] ) {
						$result = $editInfo->pstContent->serialize( $editInfo->format );
					} else {
						// Note: as of core change r727361, the PP limit comments (which we don't want to be here)
						// are already excluded.
						$result = $editInfo->getOutput()->getText();
					}
					self::$profilingExtraTime += ( microtime( true ) - $startTime );
				} else {
					$result = '';
				}
				break;
			case 'strip-html':
				$htmlVar = $parameters['html-var'];
				$html = $getVarCB( $htmlVar )->toString();
				$stripped = StringUtils::delimiterReplace( '<', '>', '', $html );
				// We strip extra spaces to the right because the stripping above
				// could leave a lot of whitespace.
				// @fixme Find a better way to do this.
				$result = TextContent::normalizeLineEndings( $stripped );
				break;
			case 'load-recent-authors':
				$result = $this->getLastPageAuthors( $parameters['title'] );
				break;
			case 'load-first-author':
				$revision = $this->revisionLookup->getFirstRevision( $parameters['title'] );
				if ( $revision ) {
					// TODO T233241
					$user = $revision->getUser();
					$result = $user === null ? '' : $user->getName();
				} else {
					$result = '';
				}
				break;
			case 'get-page-restrictions':
				$action = $parameters['action'];
				/** @var Title $title */
				$title = $parameters['title'];
				$result = $title->getRestrictions( $action );
				break;
			case 'user-editcount':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->userEditTracker->getUserEditCount( $userIdentity );
				break;
			case 'user-emailconfirm':
				/** @var User $user */
				$user = $parameters['user'];
				$result = $user->getEmailAuthenticationTimestamp();
				break;
			case 'user-groups':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->userGroupManager->getUserEffectiveGroups( $userIdentity );
				break;
			case 'user-rights':
				/** @var UserIdentity $userIdentity */
				$userIdentity = $parameters['user-identity'];
				$result = $this->permissionManager->getUserPermissions( $userIdentity );
				break;
			case 'user-block':
				// @todo Support partial blocks?
				/** @var User $user */
				$user = $parameters['user'];
				$result = (bool)$user->getBlock();
				break;
			case 'user-age':
				/** @var User $user */
				$user = $parameters['user'];
				$asOf = $parameters['asof'];

				if ( !$user->isRegistered() ) {
					$result = 0;
				} else {
					$registration = $user->getRegistration();
					// HACK: If there's no registration date, assume 2008-01-15, Wikipedia Day
					// in the year before the new user log was created. See T243469.
					if ( $registration === null ) {
						$registration = "20080115000000";
					}
					$result = (int)wfTimestamp( TS_UNIX, $asOf ) - (int)wfTimestamp( TS_UNIX, $registration );
				}
				break;
			case 'page-age':
				/** @var Title $title */
				$title = $parameters['title'];

				$firstRev = $this->revisionLookup->getFirstRevision( $title );
				$firstRevisionTime = $firstRev ? $firstRev->getTimestamp() : null;
				if ( !$firstRevisionTime ) {
					$result = 0;
					break;
				}

				$asOf = $parameters['asof'];
				$result = (int)wfTimestamp( TS_UNIX, $asOf ) - (int)wfTimestamp( TS_UNIX, $firstRevisionTime );
				break;
			case 'length':
				$s = $getVarCB( $parameters['length-var'] )->toString();
				$result = strlen( $s );
				break;
			case 'subtract-int':
				$v1 = $getVarCB( $parameters['val1-var'] )->toInt();
				$v2 = $getVarCB( $parameters['val2-var'] )->toInt();
				$result = $v1 - $v2;
				break;
			case 'revision-text-by-id':
				$revRec = $this->revisionLookup->getRevisionById( $parameters['revid'] );
				$result = $this->textExtractor->revisionToString( $revRec, $parameters['contextUser'] );
				break;
			case 'get-wiki-name':
				$result = $this->wikiID;
				break;
			case 'get-wiki-language':
				$result = $this->contentLanguage->getCode();
				break;
			default:
				if ( $this->hookRunner->onAbuseFilter_computeVariable(
					$varMethod,
					$vars,
					$parameters,
					$result
				) ) {
					throw new MWException( 'Unknown variable compute type ' . $varMethod );
				}
		}

		return $result instanceof AFPData ? $result : AFPData::newFromPHPVar( $result );
	}

	/**
	 * @param WikiPage $article
	 * @return array
	 */
	private function getLinksFromDB( WikiPage $article ) {
		// Stolen from ConfirmEdit, SimpleCaptcha::getLinksFromTracker
		$id = $article->getId();
		if ( !$id ) {
			return [];
		}

		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		return $dbr->selectFieldValues(
			'externallinks',
			'el_to',
			[ 'el_from' => $id ],
			__METHOD__
		);
	}

	/**
	 * @todo Move to MW core (T272050)
	 * @param Title $title
	 * @return string[] Usernames of the last 10 (unique) authors from $title
	 */
	private function getLastPageAuthors( Title $title ) {
		if ( !$title->exists() ) {
			return [];
		}

		$fname = __METHOD__;

		return $this->wanCache->getWithSetCallback(
			$this->wanCache->makeKey( 'last-10-authors', 'revision', $title->getLatestRevID() ),
			WANObjectCache::TTL_MINUTE,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $title, $fname ) {
				$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
				// T270033 Index renaming
				$revIndex = $dbr->indexExists( 'revision', 'page_timestamp', $fname )
					? 'page_timestamp'
					: 'rev_page_timestamp';

				$setOpts += Database::getCacheSetOptions( $dbr );
				// Get the last 100 edit authors with a trivial query (avoid T116557)
				$revQuery = $this->revisionStore->getQueryInfo();
				$revAuthors = $dbr->selectFieldValues(
					$revQuery['tables'],
					$revQuery['fields']['rev_user_text'],
					[
						'rev_page' => $title->getArticleID(),
						// TODO Should deleted names be counted in the 10 authors? If yes, this check should
						// be moved inside the foreach
						'rev_deleted' => 0
					],
					$fname,
					// Some pages have < 10 authors but many revisions (e.g. bot pages)
					[ 'ORDER BY' => 'rev_timestamp DESC, rev_id DESC',
						'LIMIT' => 100,
						// Force index per T116557
						'USE INDEX' => [ 'revision' => $revIndex ],
					],
					$revQuery['joins']
				);
				// Get the last 10 distinct authors within this set of edits
				$users = [];
				foreach ( $revAuthors as $author ) {
					$users[$author] = 1;
					if ( count( $users ) >= 10 ) {
						break;
					}
				}

				return array_keys( $users );
			}
		);
	}

	/**
	 * It's like WikiPage::prepareContentForEdit, but not for editing (old wikitext usually)
	 *
	 * @param string $wikitext
	 * @param WikiPage $article
	 * @param User $user Context user
	 *
	 * @return stdClass
	 */
	private function parseNonEditWikitext( $wikitext, WikiPage $article, User $user ) {
		static $cache = [];

		$cacheKey = md5( $wikitext ) . ':' . $article->getTitle()->getPrefixedText();

		if ( isset( $cache[$cacheKey] ) ) {
			return $cache[$cacheKey];
		}

		$edit = (object)[];
		$options = ParserOptions::newFromUser( $user );
		$edit->output = $this->parser->parse( $wikitext, $article->getTitle(), $options );
		$cache[$cacheKey] = $edit;

		return $edit;
	}
}

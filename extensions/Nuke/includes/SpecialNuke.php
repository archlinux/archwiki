<?php

namespace MediaWiki\Extension\Nuke;

use DateTime;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\Nuke\Form\SpecialNukeHTMLFormUIRenderer;
use MediaWiki\Extension\Nuke\Form\SpecialNukeUIRenderer;
use MediaWiki\Extension\Nuke\Hooks\NukeHookRunner;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\Jobs\DeletePageJob;
use MediaWiki\Language\Language;
use MediaWiki\Page\File\FileDeleteForm;
use MediaWiki\Page\RedirectLookup;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialNuke extends SpecialPage {

	/** @var NukeHookRunner|null */
	private $hookRunner;

	private JobQueueGroup $jobQueueGroup;
	private IConnectionProvider $dbProvider;
	private PermissionManager $permissionManager;
	private RepoGroup $repoGroup;
	private UserOptionsLookup $userOptionsLookup;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private NamespaceInfo $namespaceInfo;
	private Language $contentLanguage;
	private RedirectLookup $redirectLookup;
	/** @var CheckUserTemporaryAccountsByIPLookup|null */
	private $checkUserTemporaryAccountsByIPLookup = null;

	/**
	 * Action keyword for the "prompt" step.
	 */
	public const ACTION_PROMPT = 'prompt';
	/**
	 * Action keyword for the "list" step.
	 */
	public const ACTION_LIST = 'list';
	/**
	 * Action keyword for the "confirm" step.
	 */
	public const ACTION_CONFIRM = 'confirm';
	/**
	 * Action keyword for the "delete/results" step.
	 */
	public const ACTION_DELETE = 'delete';

	/**
	 * Separator for the hidden "page list" fields.
	 */
	public const PAGE_LIST_SEPARATOR = '|';

	/**
	 * Separator for the namespace list. This constant comes from the separator used by
	 * HTMLNamespacesMultiselectField.
	 */
	public const NAMESPACE_LIST_SEPARATOR = "\n";

	/**
	 * @inheritDoc
	 */
	public function __construct(
		JobQueueGroup $jobQueueGroup,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		RepoGroup $repoGroup,
		UserOptionsLookup $userOptionsLookup,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		NamespaceInfo $namespaceInfo,
		Language $contentLanguage,
		RedirectLookup $redirectLookup,
		$checkUserTemporaryAccountsByIPLookup = null
	) {
		parent::__construct( 'Nuke' );
		$this->jobQueueGroup = $jobQueueGroup;
		$this->dbProvider = $dbProvider;
		$this->permissionManager = $permissionManager;
		$this->repoGroup = $repoGroup;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->namespaceInfo = $namespaceInfo;
		$this->contentLanguage = $contentLanguage;
		$this->redirectLookup = $redirectLookup;
		$this->checkUserTemporaryAccountsByIPLookup = $checkUserTemporaryAccountsByIPLookup;
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @param null|string $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->checkReadOnly();
		$this->outputHeader();
		$this->addHelpLink( 'Help:Extension:Nuke' );

		$currentUser = $this->getUser();

		$req = $this->getRequest();
		$nukeContext = $this->getNukeContextFromRequest( $req, $par );

		if ( $nukeContext->validatePrompt() !== true ) {
			// Something is wrong with filters. Immediately return the prompt form again.
			$this->showPromptForm( $nukeContext );
			return;
		}

		switch ( $nukeContext->getAction() ) {
			case self::ACTION_DELETE:
			case self::ACTION_CONFIRM:
				if ( !$req->wasPosted()
					|| !$currentUser->matchEditToken( $req->getVal( 'wpEditToken' ) )
				) {
					// If the form was not posted or the edit token didn't match, something
					// must have gone wrong. Show the prompt form again.
					$this->showPromptForm( $nukeContext );
					break;
				}

				if ( !$nukeContext->hasPages() ) {
					if ( !$nukeContext->hasOriginalPages() ) {
						// No pages were requested. This is an early confirm attempt without having
						// listed the pages at all. Show the list form again.
						$this->showPromptForm( $nukeContext );
					} else {
						// Pages were not requested but a page list exists. The user did not select any
						// pages. Show the list form again.
						$this->showListForm( $nukeContext );
					}
					break;
				}

				if ( $nukeContext->getAction() === self::ACTION_DELETE ) {
					$deletedPageStatuses = $this->doDelete( $nukeContext );
					$this->showResultPage( $nukeContext, $deletedPageStatuses );
				} else {
					$this->showConfirmForm( $nukeContext );
				}
				break;
			case self::ACTION_LIST:
				$this->showListForm( $nukeContext );
				break;
			default:
				$this->showPromptForm( $nukeContext );
				break;
		}
	}

	/**
	 * Return a list of temporary accounts that are known to have edited from the context's target.
	 * Calls to this method result in a log entry being generated for the logged-in user account
	 * making the request.
	 *
	 * @param NukeContext $context
	 * @return string[] A list of temporary account usernames associated with the IP address
	 */
	protected function getTempAccounts( NukeContext $context ): array {
		if ( !$this->checkUserTemporaryAccountsByIPLookup ) {
			return [];
		}
		$status = $this->checkUserTemporaryAccountsByIPLookup->get(
			$context->getTarget(),
			$this->getAuthority(),
			true
		);
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return [];
	}

	/**
	 * Load the Nuke context from request data ({@link SpecialPage::getRequest}).
	 *
	 * @param WebRequest $req The request to use
	 * @param string|null $par The parameter to use as the target, if any
	 * @return NukeContext
	 */
	protected function getNukeContextFromRequest(
		WebRequest $req,
		?string $par = null
	): NukeContext {
		$target = trim( $req->getText( 'target', $par ?? '' ) );

		// Normalise name
		if ( $target !== '' ) {
			if ( IPUtils::isValid( $target ) ) {
				$target = IPUtils::sanitizeIP( $target );
				// IPUtils::sanitizeIP returns null only for bad input
				'@phan-var string $target';
			} else {
				$target = $this->userNameUtils->getCanonical( $target ) ?: $target;
			}
		}

		$namespaces = $this->loadNamespacesFromRequest( $req );
		// Set $namespaces to null if it's empty
		if ( count( $namespaces ) == 0 ) {
			$namespaces = null;
		}

		$action = $req->getRawVal( 'action' );
		if ( !$action ) {
			if ( $target !== '' ) {
				// Target was supplied but action was not. Imply 'list' action.
				$action = self::ACTION_LIST;
			} else {
				$action = self::ACTION_PROMPT;
			}
		}

		// This uses a string value to avoid having to generate hundreds of hidden <input>s.
		$originalPages = explode(
			self::PAGE_LIST_SEPARATOR,
			$req->getText( 'originalPageList' )
		);
		if ( count( $originalPages ) == 1 && $originalPages[0] == "" ) {
			$originalPages = [];
		}

		// Retrieve the maximum page size in kilobytes
		$maxPageSizeKB = $this->getConfig()->get( 'MaxArticleSize' );

		// Convert the size to bytes
		$maxPageSizeBytes = $maxPageSizeKB * 1024;

		$maxSizeUserConfig = $maxPageSizeBytes;

		// getInt doesn't treat "" as null, so we need to manually do this instead of parsing
		// $maxSizeUserConfig directly to getInt as the fallback
		if ( $req->getRawVal( 'maxPageSize' ) != "" ) {
			$maxSizeUserConfig = $req->getInt( 'maxPageSize', $maxSizeUserConfig );
		}

		return new NukeContext( [
			'requestContext' => $this->getContext(),
			'useTemporaryAccounts' => $this->checkUserTemporaryAccountsByIPLookup != null,
			'nukeAccessStatus' => $this->getNukeAccessStatus( $this->getUser() ),

			'action' => $action,
			'target' => $target,
			'listedTarget' => trim( $req->getText( 'listedTarget', $target ) ),
			'pattern' => $req->getText( 'pattern' ),
			'limit' => $req->getInt( 'limit', 500 ),
			'namespaces' => $namespaces,

			'dateFrom' => $req->getText( 'wpdateFrom' ),
			'dateTo' => $req->getText( 'wpdateTo' ),

			'includeTalkPages' => $req->getBool( 'includeTalkPages' ),
			'includeRedirects' => $req->getBool( 'includeRedirects' ),

			'pages' => $req->getArray( 'pages', [] ),
			'associatedPages' => $req->getArray( 'associatedPages', [] ),
			'originalPages' => $originalPages,

			// default to 0 (no limit) if the parameters are not set
			'minPageSize' => $req->getInt( 'minPageSize', 0 ),
			'maxPageSize' => $maxSizeUserConfig,
		] );
	}

	/**
	 * Get the UI renderer for a given type.
	 *
	 * @param NukeContext $context
	 * @return SpecialNukeUIRenderer
	 */
	protected function getUIRenderer(
		NukeContext $context
	): SpecialNukeUIRenderer {
		// Permit overriding the UI type with the `?nukeUI=` query parameter.
		$formType = $this->getRequest()->getText( 'nukeUI' );
		if ( !$formType ) {
			$formType = $this->getConfig()->get( NukeConfigNames::UIType ) ?? 'htmlform';
		}

		// Possible values: 'codex', 'htmlform'
		switch ( $formType ) {
			// case 'codex': to be implemented (T153988)
			case 'htmlform':
			default:
				return new SpecialNukeHTMLFormUIRenderer(
					$context,
					$this,
					$this->repoGroup,
					$this->getLinkRenderer(),
					$this->namespaceInfo,
					$this->redirectLookup
				);
		}
	}

	/**
	 * Load namespaces from the provided request and return them as an array. This also performs
	 * validation, ensuring that only valid namespaces are returned.
	 *
	 * @param WebRequest $req The request
	 * @return array An array of namespace IDs
	 */
	private function loadNamespacesFromRequest( WebRequest $req ): array {
		$validNamespaces = $this->namespaceInfo->getValidNamespaces();

		return array_map(
			'intval', array_filter(
				explode( self::NAMESPACE_LIST_SEPARATOR, $req->getText( "namespace" ) ),
				static function ( $ns ) use ( $validNamespaces ) {
					return is_numeric( $ns ) && in_array( intval( $ns ), $validNamespaces );
				}
			)
		);
	}

	/**
	 * Does the user have the appropriate permissions and have they enabled in preferences?
	 * Adapted from MediaWiki\CheckUser\Api\Rest\Handler\AbstractTemporaryAccountHandler::checkPermissions
	 *
	 * @param User $currentUser
	 *
	 * @throws PermissionsError if the user does not have the 'checkuser-temporary-account' right
	 * @throws ErrorPageError if the user has not enabled the 'checkuser-temporary-account-enabled' preference
	 */
	private function assertUserCanAccessTemporaryAccounts( User $currentUser ) {
		if (
			!$currentUser->isAllowed( 'checkuser-temporary-account-no-preference' )
		) {
			if (
				!$currentUser->isAllowed( 'checkuser-temporary-account' )
			) {
				throw new PermissionsError( 'checkuser-temporary-account' );
			}
			if (
				!$this->userOptionsLookup->getOption(
					$currentUser,
					'checkuser-temporary-account-enable'
				)
			) {
				throw new ErrorPageError(
					$this->msg( 'checkuser-ip-contributions-permission-error-title' ),
					$this->msg( 'checkuser-ip-contributions-permission-error-description' )
				);
			}
		}
	}

	/**
	 * Prompt for a username or IP address.
	 *
	 * @param NukeContext $context
	 */
	public function showPromptForm( NukeContext $context ): void {
		$this->getUIRenderer( $context )
			->showPromptForm();
	}

	/**
	 * Display the prompt form and a list of pages to delete.
	 *
	 * @param NukeContext $context
	 */
	public function showListForm( NukeContext $context ): void {
		// Check for temporary accounts, if applicable.
		$tempAccounts = [];
		if (
			$this->checkUserTemporaryAccountsByIPLookup &&
			IPUtils::isValid( $context->getTarget() )
		) {
			// if the target is an ip address and temp account lookup is available,
			// list pages created by the ip user or by temp accounts associated with the ip address
			$this->assertUserCanAccessTemporaryAccounts( $this->getUser() );
			$tempAccounts = $this->getTempAccounts( $context );
		}

		// Get list of pages to show the user.
		$hasExcludedResults = false;
		$pageGroups = $this->getNewPages( $context, $hasExcludedResults, $tempAccounts );

		// Calculate the search notices to show the user.
		$notices = $context->calculateSearchNotices();

		$this->getUIRenderer( $context )
			->showListForm( $pageGroups, $hasExcludedResults, $notices );
	}

	/**
	 * Display a page confirming all pages to be deleted.
	 *
	 * @param NukeContext $context
	 *
	 * @return void
	 */
	public function showConfirmForm( NukeContext $context ): void {
		$this->getUIRenderer( $context )
			->showConfirmForm();
	}

	/**
	 * Show the result page, showing what pages were deleted and what pages were skipped by the
	 * user.
	 *
	 * @param NukeContext $context
	 *   deletion. Can be either `"job"` to indicate that the page was queued for deletion, a
	 *   {@link Status} to indicate if the page was successfully deleted, or `false` if the user
	 *   did not select the page for deletion.
	 * @param (Status|string|boolean)[] $deletedPageStatuses The status for each page queued for
	 * @return void
	 */
	public function showResultPage( NukeContext $context, array $deletedPageStatuses ): void {
		$this->getUIRenderer( $context )
			->showResultPage( $deletedPageStatuses );
	}

	/**
	 * Gets a list of new pages by the specified user or everyone when none is specified.
	 *
	 * This returns an array of arrays of more arrays, following the following general structure:
	 *  - Each element in the outermost array is a "page group".
	 *  - Each page group consists of one or more pages.
	 *  - The first element of each page group represents the "main page", which the other
	 *    pages in that array are associated with.
	 *  - Each page is represented by an array with the following elements:
	 *    - The page title
	 *    - The actor name
	 *    - (if an associated page) "talk" or "redirect", to indicate the type of page
	 *
	 * @param NukeContext $context
	 * @param bool &$hasExcludedResults Will be set to `true` if some results had to be excluded
	 *   due to the user-defined limit.
	 * @param string[] $tempAccounts Temporary accounts to search for. This is passed directly
	 *   instead of through context to ensure permissions checks happen first.
	 *
	 * @return array{0:Title,1:string|false,2?:string,3?:Title}[][]
	 */
	protected function getNewPages(
		NukeContext $context, bool &$hasExcludedResults, array $tempAccounts = []
	): array {
		$dbr = $this->dbProvider->getReplicaDatabase();

		$nukeMaxAge = $context->getNukeMaxAge();

		$min = $context->getDateFrom();
		if ( !$min || $min->getTimestamp() < time() - $nukeMaxAge ) {
			// Requested $min is way too far in the past (or null). Set it to the earliest possible
			// value.
			$min = time() - $nukeMaxAge;
		} else {
			$min = $min->getTimestamp();
		}

		$max = $context->getDateTo();
		if ( $max ) {
			// Increment by 1 day to include all edits from that day.
			$max = ( clone $max )
				->modify( "+1 day" )
				->getTimestamp();
		}
		// $min and $max are int|null here.

		if ( $max && $max < $min ) {
			// Impossible range. Skip the query and fail gracefully.
			return [];
		}
		if ( $min > time() ) {
			// Improbable range (since revisions cannot be in the future).
			// Skip the query and fail gracefully.
			return [];
		}
		$maxPossibleDate = ( new DateTime() )
			->modify( "+1 day" )
			->getTimestamp();
		if ( $max > $maxPossibleDate ) {
			// Truncate to the current day, since there shouldn't be any future revisions.
			$max = $maxPossibleDate;
		}

		$target = $context->getTarget();
		if ( $context->hasTarget() ) {
			// Enable revision table searches only when a target has been specified.
			// Running queries on the revision table when there's no actor causes timeouts, since
			// the entirety of the `page` table needs to be scanned. (T380846)
			$nukeQueryBuilder = new NukeQueryBuilder(
				$dbr,
				$this->getConfig(),
				$this->namespaceInfo,
				$this->contentLanguage,
				NukeQueryBuilder::TABLE_REVISION
			);
		} else {
			// Switch to `recentchanges` table searching when running an all-user search. (T380846)
			$nukeQueryBuilder = new NukeQueryBuilder(
				$dbr,
				$this->getConfig(),
				$this->namespaceInfo,
				$this->contentLanguage,
				NukeQueryBuilder::TABLE_RECENTCHANGES
			);
		}

		// Follow the `$wgNukeMaxAge` config variable, or the user-specified minimum date.
		$nukeQueryBuilder->filterFromTimestamp( $min );

		// Follow the user-specified maximum date, if applicable.
		if ( $max ) {
			$nukeQueryBuilder->filterToTimestamp( $max );
		}

		// Limit the number of rows that can be returned by the query.
		$limit = $context->getLimit();
		$nukeQueryBuilder->limit( $limit );

		// Filter by actors, if applicable.
		$nukeQueryBuilder->filterActor( array_filter( [ $target, ...$tempAccounts ] ) );

		// Filter by namespace, if applicable.
		$namespaces = $context->getNamespaces();
		$nukeQueryBuilder->filterNamespaces( $namespaces );

		// Filter by pattern, if applicable
		$pattern = $context->getPattern();
		$nukeQueryBuilder->filterPattern(
			$pattern,
			$namespaces
		);

		$nukeQueryBuilder->filterByMinPageSize( $context->getMinPageSize() );
		$nukeQueryBuilder->filterByMaxPageSize( $context->getMaxPageSize() );

		$result = $nukeQueryBuilder
			->build()
			->caller( __METHOD__ )
			->fetchResultSet();

		// Organize all the pages we collect into "groups". This ensures that we properly
		// associate talk pages or redirects with their main page.
		//
		// The first element of each group must always be the main page.
		// This array is keyed by the main page ID.
		/** @var array{0:Title,1:string|false,2?:string,3?:Title}[][] $pageGroups */
		$pageGroups = [];

		// A summative list of pages, to be used for associated queries.
		/** @var Title[] $pageGroups */
		$pages = [];

		foreach ( $result as $row ) {
			// [ [ page title, actor name ], [ page title, actor name ], ... ]
			$mainPage = [
				Title::makeTitle( $row->page_namespace, $row->page_title ),
				$row->actor_name
			];
			$pageGroups[ $row->page_id ] = [ $mainPage ];
			$pages[] = $mainPage[0];
		}

		if ( !$pageGroups ) {
			// No results were found. Return early.
			return [];
		}

		$associatedQueryBuilder = new NukeAssociatedQueryBuilder(
			$dbr,
			$this->getConfig(),
			$this->namespaceInfo
		);
		if ( $context->getIncludeTalkPages() ) {
			// Include talk pages in the results.
			$talkPagesResult = $associatedQueryBuilder->getTalkPages( $pages )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $talkPagesResult as $talkPageRow ) {
				if ( array_key_exists( $talkPageRow->page_id, $pageGroups ) ) {
					// This page was already included in the first query. Merge it and
					// its associated pages into their main page, and then have its
					// entry in $pageGroups reference that new merged array.

					// Merging in these arrays manually instead of using array_merge
					// to preserve references across $pageGroups elements.
					foreach ( $pageGroups[ $talkPageRow->page_id ] as $talkAndAssociatedPages ) {
						$pageGroups[ $talkPageRow->subject_page_id ][] = $talkAndAssociatedPages;
					}
					$pageGroups[ $talkPageRow->page_id ] =
						&$pageGroups[ $talkPageRow->subject_page_id ];
				} else {
					// [ [ page title, actor name, "talk" ], ... ]
					$pageGroups[ $talkPageRow->subject_page_id ][] = [
						Title::makeTitle(
							$talkPageRow->page_namespace,
							$talkPageRow->page_title
						),
						$talkPageRow->actor_name,
						"talk"
					];
				}
			}
		}
		if ( $context->getIncludeRedirects() ) {
			// Include redirect pages in the results.
			$redirectPagesResult = $associatedQueryBuilder->getRedirectPages( $pages )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $redirectPagesResult as $redirectPageRow ) {
				if ( array_key_exists( $redirectPageRow->page_id, $pageGroups ) ) {
					// This page was already included in previous queries. Merge it and
					// its associated pages into their main page, and then have its
					// entry in $pageGroups reference that new merged array.

					// Merging in these arrays manually instead of using array_merge
					// to preserve references across $pageGroups elements.
					foreach ( $pageGroups[ $redirectPageRow->page_id ] as $rdAndAssociatedPages ) {
						$pageGroups[ $redirectPageRow->target_page_id ][] = $rdAndAssociatedPages;
					}
					$pageGroups[ $redirectPageRow->page_id ] =
						&$pageGroups[ $redirectPageRow->target_page_id ];
				} else {
					// [ [ page title, actor name, "redirect" ], ... ]
					$pageGroups[$redirectPageRow->target_page_id][] = [
						Title::makeTitle(
							$redirectPageRow->page_namespace,
							$redirectPageRow->page_title
						),
						$redirectPageRow->actor_name,
						"redirect"
					];
				}
			}
		}

		// Allows other extensions to provide pages to be mass-deleted that
		// don't use the revision table the way mediawiki-core does.
		foreach ( array_unique( $pageGroups, SORT_REGULAR ) as $pageGroup ) {
			if ( $namespaces ) {
				foreach ( $namespaces as $namespace ) {
					$this->getNukeHookRunner()->onNukeGetNewPages(
						$target,
						$pattern,
						$namespace,
						$limit,
						$pageGroup
					);
				}
			} else {
				$this->getNukeHookRunner()->onNukeGetNewPages(
					$target,
					$pattern,
					null,
					$limit,
					$pageGroup
				);
			}
		}

		// Now compile a list of page groups that we can show to the user. When a page group is
		// too big to include in the results (due to the limit), we'll exclude it from the results.
		// The admin can then later re-run the query, and (assuming that the page does not have an
		// extremely large amount of associated pages) the page will be included in the results.
		//
		// A page group will never be included in the results without all of its associated pages.
		// An associated page will also never appear in a group without its main page.
		$finalPageGroups = [];
		$includedPages = 0;
		$hasExcludedResults = false;
		foreach ( array_unique( $pageGroups, SORT_REGULAR ) as $pageGroup ) {
			if ( $includedPages + count( $pageGroup ) > $limit ) {
				$hasExcludedResults = true;
				continue;
			}
			$finalPageGroups[] = $pageGroup;
			$includedPages += count( $pageGroup );
		}

		return $finalPageGroups;
	}

	/**
	 * Does the actual deletion of the pages.
	 *
	 * @return array An associative array of statuses (or the string "job") keyed by the page title
	 * @throws PermissionsError
	 */
	protected function doDelete( NukeContext $context ): array {
		$statuses = [];
		$jobs = [];
		$user = $this->getUser();

		$baseReason = $context->getDeleteReason();
		$localRepo = $this->repoGroup->getLocalRepo();
		$associatedPages = $context->getAssociatedPages();
		foreach ( $context->getAllPages() as $page ) {
			$title = Title::newFromText( $page );

			if ( in_array( $page, $associatedPages ) ) {
				$reason = $this->msg( 'delete-talk-summary-prefix', $baseReason )
					->inContentLanguage()
					->text();
			} else {
				$reason = $baseReason;
			}

			$deletionResult = false;
			if ( !$this->getNukeHookRunner()->onNukeDeletePage( $title, $reason, $deletionResult ) ) {
				$statuses[$title->getPrefixedDBkey()] = $deletionResult ?
					Status::newGood() :
					Status::newFatal(
						$this->msg( 'nuke-not-deleted' )
					);
				continue;
			}

			$permission_errors = $this->permissionManager->getPermissionErrors( 'delete', $user, $title );

			if ( $permission_errors !== [] ) {
				throw new PermissionsError( 'delete', $permission_errors );
			}

			$file = $title->getNamespace() === NS_FILE ? $localRepo->newFile( $title ) : false;
			if ( $file ) {
				// Must be passed by reference
				$oldimage = null;
				$status = FileDeleteForm::doDelete(
					$title,
					$file,
					$oldimage,
					$reason,
					false,
					$user
				);
			} else {
				$job = new DeletePageJob( [
					'namespace' => $title->getNamespace(),
					'title' => $title->getDBKey(),
					'reason' => $reason,
					'userId' => $user->getId(),
					'wikiPageId' => $title->getId(),
					'suppress' => false,
					'tags' => '["nuke"]',
					'logsubtype' => 'delete',
				] );
				$jobs[] = $job;
				$status = 'job';
			}

			$statuses[$title->getPrefixedDBkey()] = $status;
		}

		if ( $jobs ) {
			$this->jobQueueGroup->push( $jobs );
		}

		return $statuses;
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}

		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * Group Special:Nuke with pagetools
	 *
	 * @codeCoverageIgnore
	 * @return string
	 */
	protected function getGroupName() {
		return 'pagetools';
	}

	private function getNukeHookRunner(): NukeHookRunner {
		$this->hookRunner ??= new NukeHookRunner( $this->getHookContainer() );
		return $this->hookRunner;
	}

	/**
	 * Check the status of the current user's access to Nuke.
	 *
	 * Returns a number based on the user's access to Nuke,
	 * you can use the NUKE_ACCESS_* constants to compare the result.
	 *
	 * @param User $currentUser
	 *
	 * @return int
	 */
	private function getNukeAccessStatus( User $currentUser ): int {
		if ( !$currentUser->isAllowed( 'nuke' ) ) {
			return NukeContext::NUKE_ACCESS_NO_PERMISSION;
		}

		// appliesToRight is presently a no-op, since there is no handling for `delete`,
		// and so will return `null`. `true` will be returned if the block actively
		// applies to `delete`, and both `null` and `true` should result in an error
		$block = $currentUser->getBlock();
		if ( $block && ( $block->isSitewide() ||
			( $block->appliesToRight( 'delete' ) !== false ) )
		) {
			return NukeContext::NUKE_ACCESS_BLOCKED;
		}

		return NukeContext::NUKE_ACCESS_GRANTED;
	}
}

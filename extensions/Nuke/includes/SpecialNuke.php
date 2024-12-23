<?php

namespace MediaWiki\Extension\Nuke;

use DeletePageJob;
use ErrorPageError;
use JobQueueGroup;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Extension\Nuke\Hooks\NukeHookRunner;
use MediaWiki\Html\Html;
use MediaWiki\Html\ListToggle;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\Language;
use MediaWiki\Page\File\FileDeleteForm;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\Xml\Xml;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\TextInputWidget;
use PermissionsError;
use RepoGroup;
use UserBlockedError;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeMatch;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class SpecialNuke extends SpecialPage {

	/** @var NukeHookRunner|null */
	private $hookRunner;

	private JobQueueGroup $jobQueueGroup;
	private IConnectionProvider $dbProvider;
	private PermissionManager $permissionManager;
	private RepoGroup $repoGroup;
	private UserFactory $userFactory;
	private UserOptionsLookup $userOptionsLookup;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserNameUtils $userNameUtils;
	private NamespaceInfo $namespaceInfo;
	private Language $contentLanguage;
	/** @var CheckUserTemporaryAccountsByIPLookup|null */
	private $checkUserTemporaryAccountsByIPLookup = null;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		JobQueueGroup $jobQueueGroup,
		IConnectionProvider $dbProvider,
		PermissionManager $permissionManager,
		RepoGroup $repoGroup,
		UserFactory $userFactory,
		UserOptionsLookup $userOptionsLookup,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserNameUtils $userNameUtils,
		NamespaceInfo $namespaceInfo,
		Language $contentLanguage,
		$checkUserTemporaryAccountsByIPLookup = null
	) {
		parent::__construct( 'Nuke', 'nuke' );
		$this->jobQueueGroup = $jobQueueGroup;
		$this->dbProvider = $dbProvider;
		$this->permissionManager = $permissionManager;
		$this->repoGroup = $repoGroup;
		$this->userFactory = $userFactory;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userNameUtils = $userNameUtils;
		$this->namespaceInfo = $namespaceInfo;
		$this->contentLanguage = $contentLanguage;
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
		$this->checkPermissions();
		$this->checkReadOnly();
		$this->outputHeader();
		$this->addHelpLink( 'Help:Extension:Nuke' );

		$currentUser = $this->getUser();
		$block = $currentUser->getBlock();

		// appliesToRight is presently a no-op, since there is no handling for `delete`,
		// and so will return `null`. `true` will be returned if the block actively
		// applies to `delete`, and both `null` and `true` should result in an error
		if ( $block && ( $block->isSitewide() ||
			( $block->appliesToRight( 'delete' ) !== false ) )
		) {
			throw new UserBlockedError( $block );
		}

		$req = $this->getRequest();
		$target = trim( $req->getText( 'target', $par ?? '' ) );

		// Normalise name
		if ( $target !== '' ) {
			$user = $this->userFactory->newFromName( $target );
			if ( $user ) {
				$target = $user->getName();
			}
		}

		$reason = $this->getDeleteReason( $this->getRequest(), $target );

		$limit = $req->getInt( 'limit', 500 );
		$namespace = $req->getIntOrNull( 'namespace' );

		if ( $req->wasPosted()
			&& $currentUser->matchEditToken( $req->getVal( 'wpEditToken' ) )
		) {
			if ( $req->getRawVal( 'action' ) === 'delete' ) {
				$pages = $req->getArray( 'pages' );

				if ( $pages ) {
					$this->doDelete( $pages, $reason );
					return;
				}
			} elseif ( $req->getRawVal( 'action' ) === 'submit' ) {
				// if the target is an ip addresss and temp account lookup is available,
				// list pages created by the ip user or by temp accounts associated with the ip address
				if (
					$this->checkUserTemporaryAccountsByIPLookup &&
					IPUtils::isValid( $target )
				) {
					$this->assertUserCanAccessTemporaryAccounts( $currentUser );
					$tempnames = $this->getTempAccountData( $target );
					$reason = $this->getDeleteReason( $this->getRequest(), $target, true );
					$this->listForm( $target, $reason, $limit, $namespace, $tempnames );
				} else {
					// otherwise just list pages normally
					$this->listForm( $target, $reason, $limit, $namespace );
				}
			} else {
				$this->promptForm();
			}
		} elseif ( $target === '' ) {
			$this->promptForm();
		} else {
			$this->listForm( $target, $reason, $limit, $namespace );
		}
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
	 * Given an IP address, return a list of temporary accounts that are known to have edited from the IP.
	 *
	 * Calls to this method result in a log entry being generated for the logged-in user account making the request.
	 * @param string $ip The IP address used for looking up temporary account names.
	 * The address will be normalized in the IP lookup service.
	 * @return string[] A list of temporary account usernames associated with the IP address
	 */
	private function getTempAccountData( string $ip ): array {
		// Requires CheckUserTemporaryAccountsByIPLookup service
		if ( !$this->checkUserTemporaryAccountsByIPLookup ) {
			return [];
		}
		$status = $this->checkUserTemporaryAccountsByIPLookup->get(
			$ip,
			$this->getAuthority(),
			true
		);
		if ( $status->isGood() ) {
			return $status->getValue();
		}
		return [];
	}

	/**
	 * Prompt for a username or IP address.
	 *
	 * @param string $userName
	 */
	protected function promptForm( string $userName = '' ): void {
		$out = $this->getOutput();

		if ( $this->checkUserTemporaryAccountsByIPLookup ) {
			$out->addWikiMsg( 'nuke-tools-tempaccount' );
		} else {
			$out->addWikiMsg( 'nuke-tools' );
		}

		$formDescriptor = [
			'nuke-target' => [
				'id' => 'nuke-target',
				'default' => $userName,
				'label' => $this->msg( 'nuke-userorip' )->text(),
				'type' => 'user',
				'name' => 'target',
				'autofocus' => true
			],
			'nuke-pattern' => [
				'id' => 'nuke-pattern',
				'label' => $this->msg( 'nuke-pattern' )->text(),
				'maxLength' => 40,
				'type' => 'text',
				'name' => 'pattern'
			],
			'namespace' => [
				'id' => 'nuke-namespace',
				'type' => 'namespaceselect',
				'label' => $this->msg( 'nuke-namespace' )->text(),
				'all' => 'all',
				'name' => 'namespace'
			],
			'limit' => [
				'id' => 'nuke-limit',
				'maxLength' => 7,
				'default' => 500,
				'label' => $this->msg( 'nuke-maxpages' )->text(),
				'type' => 'int',
				'name' => 'limit'
			]
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setName( 'massdelete' )
			->setFormIdentifier( 'massdelete' )
			->setWrapperLegendMsg( 'nuke' )
			->setSubmitTextMsg( 'nuke-submit-user' )
			->setSubmitName( 'nuke-submit-user' )
			->setAction( $this->getPageTitle()->getLocalURL( 'action=submit' ) )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Display list of pages to delete.
	 *
	 * @param string $username
	 * @param string $reason
	 * @param int $limit
	 * @param int|null $namespace
	 * @param string[] $tempnames
	 */
	protected function listForm( $username, $reason, $limit, $namespace = null, $tempnames = [] ): void {
		$out = $this->getOutput();

		$pages = $this->getNewPages( $username, $limit, $namespace, $tempnames );

		if ( !$pages ) {
			if ( $username === '' ) {
				$out->addWikiMsg( 'nuke-nopages-global' );
			} else {
				$out->addWikiMsg( 'nuke-nopages', $username );
			}

			$this->promptForm( $username );
			return;
		}

		$out->addModules( 'ext.nuke.confirm' );
		$out->addModuleStyles( [ 'ext.nuke.styles', 'mediawiki.interface.helpers.styles' ] );

		if ( $username === '' ) {
			$out->addWikiMsg( 'nuke-list-multiple' );
		} elseif ( $tempnames ) {
			$out->addWikiMsg( 'nuke-list-tempaccount', $username );
		} else {
			$out->addWikiMsg( 'nuke-list', $username );
		}

		$nuke = $this->getPageTitle();

		$options = Xml::listDropdownOptions(
			$this->msg( 'deletereason-dropdown' )->inContentLanguage()->text(),
			[ 'other' => $this->msg( 'deletereasonotherlist' )->inContentLanguage()->text() ]
		);

		$dropdown = new FieldLayout(
			new DropdownInputWidget( [
				'name' => 'wpDeleteReasonList',
				'inputId' => 'wpDeleteReasonList',
				'tabIndex' => 1,
				'infusable' => true,
				'value' => '',
				'options' => Xml::listDropdownOptionsOoui( $options ),
			] ),
			[
				'label' => $this->msg( 'deletecomment' )->text(),
				'align' => 'top',
			]
		);
		$reasonField = new FieldLayout(
			new TextInputWidget( [
				'name' => 'wpReason',
				'inputId' => 'wpReason',
				'tabIndex' => 2,
				'maxLength' => CommentStore::COMMENT_CHARACTER_LIMIT,
				'infusable' => true,
				'value' => $reason,
				'autofocus' => true,
			] ),
			[
				'label' => $this->msg( 'deleteotherreason' )->text(),
				'align' => 'top',
			]
		);

		$out->enableOOUI();
		$out->addHTML(
			Html::openElement( 'form', [
					'action' => $nuke->getLocalURL( 'action=delete' ),
					'method' => 'post',
					'name' => 'nukelist' ]
			) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			$dropdown .
			$reasonField .
			// Select: All, None, Invert
			( new ListToggle( $this->getOutput() ) )->getHTML() .
			'<ul>'
		);

		$wordSeparator = $this->msg( 'word-separator' )->escaped();
		$commaSeparator = $this->msg( 'comma-separator' )->escaped();
		$pipeSeparator = $this->msg( 'pipe-separator' )->escaped();

		$linkRenderer = $this->getLinkRenderer();
		$localRepo = $this->repoGroup->getLocalRepo();
		foreach ( $pages as [ $title, $userName ] ) {
			/**
			 * @var $title Title
			 */

			$image = $title->inNamespace( NS_FILE ) ? $localRepo->newFile( $title ) : false;
			$thumb = $image && $image->exists() ?
				$image->transform( [ 'width' => 120, 'height' => 120 ], 0 ) :
				false;

			$userNameText = $userName ?
				' <span class="mw-changeslist-separator"></span> ' . $this->msg( 'nuke-editby', $userName )->parse() :
				'';
			$changesLink = $linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'nuke-viewchanges' )->text(),
				[],
				[ 'action' => 'history' ]
			);

			$talkPageText = $this->namespaceInfo->isTalk( $title->getNamespace() ) ?
				'' :
				$linkRenderer->makeLink(
					$this->namespaceInfo->getTalkPage( $title ),
					$this->msg( 'sp-contributions-talk' )->text(),
					[],
					[],
				) . $wordSeparator . $pipeSeparator;

			$query = $title->isRedirect() ? [ 'redirect' => 'no' ] : [];
			$attributes = $title->isRedirect() ? [ 'class' => 'ext-nuke-italicize' ] : [];
			$out->addHTML( '<li>' .
				Html::check(
					'pages[]',
					true,
					[ 'value' => $title->getPrefixedDBkey() ]
				) . "\u{00A0}" .
				( $thumb ? $thumb->toHtml( [ 'desc-link' => true ] ) : '' ) .
				$linkRenderer->makeKnownLink( $title, null, $attributes, $query ) . $wordSeparator .
				$this->msg( 'parentheses' )->rawParams( $talkPageText . $changesLink )->escaped() . $wordSeparator .
				"<span class='ext-nuke-italicize'>" . $userNameText . "</span>" .
				"</li>\n" );
		}

		$out->addHTML(
			"</ul>\n" .
			Html::submitButton( $this->msg( 'nuke-submit-delete' )->text() ) .
			'</form>'
		);
	}

	/**
	 * Gets a list of new pages by the specified user or everyone when none is specified.
	 *
	 * @param string $username
	 * @param int $limit
	 * @param int|null $namespace
	 * @param string[] $tempnames
	 *
	 * @return array{0:Title,1:string|false}[]
	 */
	protected function getNewPages( $username, $limit, $namespace = null, $tempnames = [] ): array {
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder()
			->select( [ 'page_title', 'page_namespace' ] )
			->from( 'recentchanges' )
			->join( 'actor', null, 'actor_id=rc_actor' )
			->join( 'page', null, 'page_id=rc_cur_id' )
			->where(
				$dbr->expr( 'rc_source', '=', 'mw.new' )->orExpr(
					$dbr->expr( 'rc_log_type', '=', 'upload' )
						->and( 'rc_log_action', '=', 'upload' )
				)
			)
			->orderBy( 'rc_timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $limit );

		if ( $username === '' ) {
			$queryBuilder->field( 'actor_name', 'rc_user_text' );
		} else {
			$actornames = array_filter( [ $username, ...$tempnames ] );
			if ( $actornames ) {
				$queryBuilder->andWhere( [ 'actor_name' => $actornames ] );
			}
		}

		if ( $namespace !== null ) {
			$queryBuilder->andWhere( [ 'page_namespace' => $namespace ] );
		}

		$pattern = $this->getRequest()->getText( 'pattern' );
		if ( $pattern !== null && trim( $pattern ) !== '' ) {
			$addedWhere = false;

			$pattern = trim( $pattern );
			$pattern = preg_replace( '/ +/', '`_', $pattern );
			$pattern = preg_replace( '/\\\\([%_])/', '`$1', $pattern );

			if ( $namespace !== null ) {
				// Custom namespace requested
				// If that namespace capitalizes titles, capitalize the first character
				// to match the DB title.
				$pattern = $this->namespaceInfo->isCapitalized( $namespace ) ?
					$this->contentLanguage->ucfirst( $pattern ) : $pattern;
			} else {
				// All namespaces requested

				$overriddenNamespaces = [];
				$capitalLinks = $this->getConfig()->get( 'CapitalLinks' );
				$capitalLinkOverrides = $this->getConfig()->get( 'CapitalLinkOverrides' );
				// If there are any capital-overridden namespaces, keep track of them. "overridden"
				// here means the namespace-specific value is not equal to $wgCapitalLinks.
				foreach ( $capitalLinkOverrides as $k => $v ) {
					if ( $v !== $capitalLinks ) {
						$overriddenNamespaces[] = $k;
					}
				}

				if ( count( $overriddenNamespaces ) ) {
					// If there are overridden namespaces, they have to be converted
					// on a case-by-case basis.

					$validNamespaces = $this->namespaceInfo->getValidNamespaces();
					$nonOverriddenNamespaces = [];
					foreach ( $validNamespaces as $ns ) {
						if ( !in_array( $ns, $overriddenNamespaces ) ) {
							// Put all namespaces that aren't overridden in $nonOverriddenNamespaces
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
					$queryBuilder->andWhere( $dbr->orExpr( $orConditions ) );
					$addedWhere = true;
				} else {
					// No overridden namespaces; just convert all titles.
					$pattern = $this->namespaceInfo->isCapitalized( NS_MAIN ) ?
						$this->contentLanguage->ucfirst( $pattern ) : $pattern;
				}
			}

			if ( !$addedWhere ) {
				$queryBuilder->andWhere(
					$dbr->expr(
						'page_title',
						IExpression::LIKE,
						new LikeValue(
							new LikeMatch( $pattern )
						)
					)
				);
			}
		}

		$result = $queryBuilder->caller( __METHOD__ )->fetchResultSet();
		/** @var array{0:Title,1:string|false}[] $pages */
		$pages = [];
		foreach ( $result as $row ) {
			$pages[] = [
				Title::makeTitle( $row->page_namespace, $row->page_title ),
				$username === '' ? $row->rc_user_text : false
			];
		}

		// Allows other extensions to provide pages to be nuked that don't use
		// the recentchanges table the way mediawiki-core does
		$this->getNukeHookRunner()->onNukeGetNewPages( $username, $pattern, $namespace, $limit, $pages );

		// Re-enforcing the limit *after* the hook because other extensions
		// may add and/or remove pages. We need to make sure we don't end up
		// with more pages than $limit.
		if ( count( $pages ) > $limit ) {
			$pages = array_slice( $pages, 0, $limit );
		}

		return $pages;
	}

	/**
	 * Does the actual deletion of the pages.
	 *
	 * @param array $pages The pages to delete
	 * @param string $reason
	 * @throws PermissionsError
	 */
	protected function doDelete( array $pages, $reason ): void {
		$res = [];
		$jobs = [];
		$user = $this->getUser();

		$localRepo = $this->repoGroup->getLocalRepo();
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );

			$deletionResult = false;
			if ( !$this->getNukeHookRunner()->onNukeDeletePage( $title, $reason, $deletionResult ) ) {
				$res[] = $this->msg(
					$deletionResult ? 'nuke-deleted' : 'nuke-not-deleted',
					wfEscapeWikiText( $title->getPrefixedText() )
				)->parse();
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
					'tags' => '["Nuke"]',
					'logsubtype' => 'delete',
				] );
				$jobs[] = $job;
				$status = 'job';
			}

			if ( $status === 'job' ) {
				$res[] = $this->msg(
					'nuke-deletion-queued',
					wfEscapeWikiText( $title->getPrefixedText() )
				)->parse();
			} else {
				$res[] = $this->msg(
					$status->isOK() ? 'nuke-deleted' : 'nuke-not-deleted',
					wfEscapeWikiText( $title->getPrefixedText() )
				)->parse();
			}
		}

		if ( $jobs ) {
			$this->jobQueueGroup->push( $jobs );
		}

		$this->getOutput()->addHTML(
			"<ul>\n<li>" .
			implode( "</li>\n<li>", $res ) .
			"</li>\n</ul>\n"
		);
		$this->getOutput()->addWikiMsg( 'nuke-delete-more' );
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

	private function getDeleteReason( WebRequest $request, string $target, bool $tempaccount = false ): string {
		if ( $tempaccount ) {
			$defaultReason = $this->msg( 'nuke-defaultreason-tempaccount' );
		} else {
			$defaultReason = $target === ''
				? $this->msg( 'nuke-multiplepeople' )->inContentLanguage()->text()
				: $this->msg( 'nuke-defaultreason', $target )->inContentLanguage()->text();
		}

		$dropdownSelection = $request->getText( 'wpDeleteReasonList', 'other' );
		$reasonInput = $request->getText( 'wpReason', $defaultReason );

		if ( $dropdownSelection === 'other' ) {
			return $reasonInput;
		} elseif ( $reasonInput !== '' ) {
			// Entry from drop down menu + additional comment
			$separator = $this->msg( 'colon-separator' )->inContentLanguage()->text();
			return $dropdownSelection . $separator . $reasonInput;
		} else {
			return $dropdownSelection;
		}
	}

	private function getNukeHookRunner(): NukeHookRunner {
		$this->hookRunner ??= new NukeHookRunner( $this->getHookContainer() );
		return $this->hookRunner;
	}
}

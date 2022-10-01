<?php

namespace MediaWiki\Extension\Nuke;

use CommentStore;
use DeletePageJob;
use FileDeleteForm;
use Html;
use HTMLForm;
use ListToggle;
use MediaWiki\Extension\Nuke\Hooks\NukeHookRunner;
use MediaWiki\MediaWikiServices;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\TextInputWidget;
use PermissionsError;
use SpecialPage;
use Title;
use User;
use UserBlockedError;
use UserNamePrefixSearch;
use WebRequest;
use Xml;

class SpecialNuke extends SpecialPage {

	/** @var NukeHookRunner */
	private $hookRunner;

	public function __construct() {
		parent::__construct( 'Nuke', 'nuke' );
		$this->hookRunner = new NukeHookRunner( $this->getHookContainer() );
	}

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
			$user = User::newFromName( $target );
			if ( $user ) {
				$target = $user->getName();
			}
		}

		$reason = $this->getDeleteReason( $this->getRequest(), $target );

		$limit = $req->getInt( 'limit', 500 );
		$namespace = $req->getVal( 'namespace' );
		$namespace = ctype_digit( $namespace ) ? (int)$namespace : null;

		if ( $req->wasPosted()
			&& $currentUser->matchEditToken( $req->getVal( 'wpEditToken' ) )
		) {
			if ( $req->getVal( 'action' ) === 'delete' ) {
				$pages = $req->getArray( 'pages' );

				if ( $pages ) {
					$this->doDelete( $pages, $reason );

					return;
				}
			} elseif ( $req->getVal( 'action' ) === 'submit' ) {
				$this->listForm( $target, $reason, $limit, $namespace );
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
	 * Prompt for a username or IP address.
	 *
	 * @param string $userName
	 */
	protected function promptForm( $userName = '' ) {
		$out = $this->getOutput();

		$out->addWikiMsg( 'nuke-tools' );

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
			->addHiddenField( 'wpEditToken', $this->getUser()->getEditToken() )
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
	 */
	protected function listForm( $username, $reason, $limit, $namespace = null ) {
		$out = $this->getOutput();

		$pages = $this->getNewPages( $username, $limit, $namespace );

		if ( count( $pages ) === 0 ) {
			if ( $username === '' ) {
				$out->addWikiMsg( 'nuke-nopages-global' );
			} else {
				$out->addWikiMsg( 'nuke-nopages', $username );
			}

			$this->promptForm( $username );

			return;
		}

		$out->addModules( 'ext.nuke.confirm' );

		if ( $username === '' ) {
			$out->addWikiMsg( 'nuke-list-multiple' );
		} else {
			$out->addWikiMsg( 'nuke-list', $username );
		}

		$nuke = $this->getPageTitle();

		$options = Xml::listDropDownOptions(
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
				'options' => Xml::listDropDownOptionsOoui( $options ),
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
			Xml::openElement( 'form', [
					'action' => $nuke->getLocalURL( 'action=delete' ),
					'method' => 'post',
					'name' => 'nukelist' ]
			) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			$dropdown . $reasonField
		);

		// Select: All, None, Invert
		$listToggle = new ListToggle( $this->getOutput() );
		$selectLinks = $listToggle->getHTML();

		$out->addHTML(
			$selectLinks .
			'<ul>'
		);

		$wordSeparator = $this->msg( 'word-separator' )->escaped();
		$commaSeparator = $this->msg( 'comma-separator' )->escaped();

		$linkRenderer = $this->getLinkRenderer();
		$localRepo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		foreach ( $pages as $info ) {
			/**
			 * @var $title Title
			 */
			list( $title, $userName ) = $info;

			$image = $title->inNamespace( NS_FILE ) ? $localRepo->newFile( $title ) : false;
			$thumb = $image && $image->exists() ?
				$image->transform( [ 'width' => 120, 'height' => 120 ], 0 ) :
				false;

			$userNameText = $userName ?
				$this->msg( 'nuke-editby', $userName )->parse() . $commaSeparator :
				'';
			$changesLink = $linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'nuke-viewchanges' )->text(),
				[],
				[ 'action' => 'history' ]
			);
			$out->addHTML( '<li>' .
				Xml::check(
					'pages[]',
					true,
					[ 'value' => $title->getPrefixedDBkey() ]
				) . '&#160;' .
				( $thumb ? $thumb->toHtml( [ 'desc-link' => true ] ) : '' ) .
				$linkRenderer->makeKnownLink( $title ) . $wordSeparator .
				$this->msg( 'parentheses' )->rawParams( $userNameText . $changesLink )->escaped() .
				"</li>\n" );
		}

		$out->addHTML(
			"</ul>\n" .
			Xml::submitButton( $this->msg( 'nuke-submit-delete' )->text() ) .
			'</form>'
		);
	}

	/**
	 * Gets a list of new pages by the specified user or everyone when none is specified.
	 *
	 * @param string $username
	 * @param int $limit
	 * @param int|null $namespace
	 *
	 * @return array
	 */
	protected function getNewPages( $username, $limit, $namespace = null ) {
		$dbr = wfGetDB( DB_REPLICA );

		$what = [
			'rc_namespace',
			'rc_title',
		];

		$where = [
			$dbr->makeList( [
				'rc_new' => 1,
				$dbr->makeList( [
					'rc_log_type' => 'upload',
					'rc_log_action' => 'upload',
				], LIST_AND ),
			], LIST_OR ),
		];

		if ( $username === '' ) {
			$what['rc_user_text'] = 'actor_name';
		} else {
			$where['actor_name'] = $username;
		}

		if ( $namespace !== null ) {
			$where['rc_namespace'] = $namespace;
		}

		$pattern = $this->getRequest()->getText( 'pattern' );
		if ( $pattern !== null && trim( $pattern ) !== '' ) {
			// $pattern is a SQL pattern supporting wildcards, so buildLike
			// will not work.
			$where[] = 'rc_title LIKE ' . $dbr->addQuotes( $pattern );
		}

		$result = $dbr->select(
			[ 'recentchanges', 'actor' ],
			$what,
			$where,
			__METHOD__,
			[
				'ORDER BY' => 'rc_timestamp DESC',
				'LIMIT' => $limit
			],
			[ 'actor' => [ 'JOIN', 'actor_id=rc_actor' ] ]
		);

		$pages = [];

		foreach ( $result as $row ) {
			$pages[] = [
				Title::makeTitle( $row->rc_namespace, $row->rc_title ),
				$username === '' ? $row->rc_user_text : false
			];
		}

		// Allows other extensions to provide pages to be nuked that don't use
		// the recentchanges table the way mediawiki-core does
		$this->hookRunner->onNukeGetNewPages( $username, $pattern, $namespace, $limit, $pages );

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
	protected function doDelete( array $pages, $reason ) {
		$res = [];
		$jobs = [];
		$user = $this->getUser();

		$services = MediaWikiServices::getInstance();
		$localRepo = $services->getRepoGroup()->getLocalRepo();
		$permissionManager = $services->getPermissionManager();
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );

			$deletionResult = false;
			if ( !$this->hookRunner->onNukeDeletePage( $title, $reason, $deletionResult ) ) {
				if ( $deletionResult ) {
					$res[] = $this->msg( 'nuke-deleted', $title->getPrefixedText() )->parse();
				} else {
					$res[] = $this->msg( 'nuke-not-deleted', $title->getPrefixedText() )->parse();
				}
				continue;
			}

			$permission_errors = $permissionManager->getPermissionErrors( 'delete', $user, $title );

			if ( $permission_errors !== [] ) {
				throw new PermissionsError( 'delete', $permission_errors );
			}

			$file = $title->getNamespace() === NS_FILE ? $localRepo->newFile( $title ) : false;
			if ( $file ) {
				$oldimage = null; // Must be passed by reference
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
					'tags' => '[]',
					'logsubtype' => 'delete',
				] );
				$jobs[] = $job;
				$status = 'job';
			}

			if ( $status == 'job' ) {
				$res[] = $this->msg( 'nuke-deletion-queued', $title->getPrefixedText() )->parse();
			} elseif ( $status->isOK() ) {
				$res[] = $this->msg( 'nuke-deleted', $title->getPrefixedText() )->parse();
			} else {
				$res[] = $this->msg( 'nuke-not-deleted', $title->getPrefixedText() )->parse();
			}
		}

		if ( $jobs ) {
			MediaWikiServices::getInstance()->getJobQueueGroup()->push( $jobs );
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
		$user = User::newFromName( $search );
		if ( !$user ) {
			// No prefix suggestion for invalid user
			return [];
		}

		// Autocomplete subpage as user list - public to allow caching
		return UserNamePrefixSearch::search( 'public', $search, $limit, $offset );
	}

	/**
	 * Group Special:Nuke with pagetools
	 *
	 * @return string
	 */
	protected function getGroupName() {
		return 'pagetools';
	}

	/**
	 * @param WebRequest $request
	 * @param string $target
	 * @return string
	 */
	private function getDeleteReason( WebRequest $request, string $target ): string {
		$defaultReason = $target === ''
			? $this->msg( 'nuke-multiplepeople' )->inContentLanguage()->text()
			: $this->msg( 'nuke-defaultreason', $target )->inContentLanguage()->text();

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
}

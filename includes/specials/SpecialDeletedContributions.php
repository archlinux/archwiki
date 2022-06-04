<?php
/**
 * Implements Special:DeletedContributions
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

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionFactory;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Implements Special:DeletedContributions to display archived revisions
 * @ingroup SpecialPage
 */
class SpecialDeletedContributions extends SpecialPage {
	/** @var FormOptions */
	protected $mOpts;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var CommentStore */
	private $commentStore;

	/** @var RevisionFactory */
	private $revisionFactory;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/** @var UserFactory */
	private $userFactory;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var UserNamePrefixSearch */
	private $userNamePrefixSearch;

	/**
	 * @param PermissionManager $permissionManager
	 * @param ILoadBalancer $loadBalancer
	 * @param CommentStore $commentStore
	 * @param RevisionFactory $revisionFactory
	 * @param NamespaceInfo $namespaceInfo
	 * @param UserFactory $userFactory
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 */
	public function __construct(
		PermissionManager $permissionManager,
		ILoadBalancer $loadBalancer,
		CommentStore $commentStore,
		RevisionFactory $revisionFactory,
		NamespaceInfo $namespaceInfo,
		UserFactory $userFactory,
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch
	) {
		parent::__construct( 'DeletedContributions', 'deletedhistory' );
		$this->permissionManager = $permissionManager;
		$this->loadBalancer = $loadBalancer;
		$this->commentStore = $commentStore;
		$this->revisionFactory = $revisionFactory;
		$this->namespaceInfo = $namespaceInfo;
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
	}

	/**
	 * Special page "deleted user contributions".
	 * Shows a list of the deleted contributions of a user.
	 *
	 * @param string|null $par user name of the user for which to show the contributions
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->checkPermissions();
		$out = $this->getOutput();
		$out->addModuleStyles( 'mediawiki.interface.helpers.styles' );
		$this->addHelpLink( 'Help:User contributions' );

		$opts = new FormOptions();

		$opts->add( 'target', '' );
		$opts->add( 'namespace', '' );
		$opts->add( 'limit', 20 );

		$opts->fetchValuesFromRequest( $this->getRequest() );
		$opts->validateIntBounds( 'limit', 0, $this->getConfig()->get( 'QueryPageDefaultLimit' ) );

		if ( $par !== null ) {
			// Beautify the username
			$par = $this->userNameUtils->getCanonical( $par, UserNameUtils::RIGOR_NONE );
			$opts->setValue( 'target', (string)$par );
		}

		$ns = $opts->getValue( 'namespace' );
		if ( $ns !== null && $ns !== '' ) {
			$opts->setValue( 'namespace', intval( $ns ) );
		}

		$this->mOpts = $opts;

		$target = trim( $opts->getValue( 'target' ) );
		if ( !strlen( $target ) ) {
			$this->getForm();

			return;
		}

		$userObj = $this->userFactory->newFromName( $target, UserFactory::RIGOR_NONE );
		if ( !$userObj ) {
			$this->getForm();

			return;
		}
		$this->getSkin()->setRelevantUser( $userObj );

		$target = $userObj->getName();

		$out->addSubtitle( $this->getSubTitle( $userObj ) );
		$out->setPageTitle( $this->msg( 'deletedcontributions-title', $target ) );

		$this->getForm();

		$pager = new DeletedContribsPager(
			$this->getContext(),
			$this->commentStore,
			$this->getHookContainer(),
			$this->getLinkRenderer(),
			$this->loadBalancer,
			$this->revisionFactory,
			$target,
			$opts->getValue( 'namespace' )
		);
		if ( !$pager->getNumRows() ) {
			$out->addWikiMsg( 'nocontribs' );

			return;
		}

		# Show a message about replica DB lag, if applicable
		$lag = $pager->getDatabase()->getSessionLagStatus()['lag'];
		if ( $lag > 0 ) {
			$out->showLagWarning( $lag );
		}

		$out->addHTML(
			'<p>' . $pager->getNavigationBar() . '</p>' .
				$pager->getBody() .
				'<p>' . $pager->getNavigationBar() . '</p>' );

		# If there were contributions, and it was a valid user or IP, show
		# the appropriate "footer" message - WHOIS tools, etc.
		$message = IPUtils::isIPAddress( $target ) ?
			'sp-contributions-footer-anon' :
			'sp-contributions-footer';

		if ( !$this->msg( $message )->isDisabled() ) {
			$out->wrapWikiMsg(
				"<div class='mw-contributions-footer'>\n$1\n</div>",
				[ $message, $target ]
			);
		}
	}

	/**
	 * Generates the subheading with links
	 * @param User $userObj User object for the target
	 * @return string Appropriately-escaped HTML to be output literally
	 */
	private function getSubTitle( $userObj ) {
		$linkRenderer = $this->getLinkRenderer();
		if ( $userObj->isAnon() ) {
			$user = htmlspecialchars( $userObj->getName() );
		} else {
			$user = $linkRenderer->makeLink( $userObj->getUserPage(), $userObj->getName() );
		}
		$links = '';
		$nt = $userObj->getUserPage();
		$talk = $nt->getTalkPage();
		if ( $talk ) {
			$tools = SpecialContributions::getUserLinks(
				$this,
				$userObj,
				$this->permissionManager,
				$this->getHookRunner()
			);

			$contributionsLink = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Contributions', $nt->getDBkey() ),
				$this->msg( 'sp-deletedcontributions-contribs' )->text()
			);
			if ( isset( $tools['deletedcontribs'] ) ) {
				// Swap out the deletedcontribs link for our contribs one
				$tools = wfArrayInsertAfter(
					$tools, [ 'contribs' => $contributionsLink ], 'deletedcontribs' );
				unset( $tools['deletedcontribs'] );
			} else {
				$tools['contribs'] = $contributionsLink;
			}

			$links = $this->getLanguage()->pipeList( $tools );

			// Show a note if the user is blocked and display the last block log entry.
			$block = DatabaseBlock::newFromTarget( $userObj, $userObj );
			if ( $block !== null && $block->getType() != DatabaseBlock::TYPE_AUTO ) {
				if ( $block->getType() == DatabaseBlock::TYPE_RANGE ) {
					$nt = $this->namespaceInfo->getCanonicalName( NS_USER )
						. ':' . $block->getTargetName();
				}

				// LogEventsList::showLogExtract() wants the first parameter by ref
				$out = $this->getOutput();
				LogEventsList::showLogExtract(
					$out,
					'block',
					$nt,
					'',
					[
						'lim' => 1,
						'showIfEmpty' => false,
						'msgKey' => [
							'sp-contributions-blocked-notice',
							$userObj->getName() # Support GENDER in 'sp-contributions-blocked-notice'
						],
						'offset' => '' # don't use $this->getRequest() parameter offset
					]
				);
			}
		}

		return $this->msg( 'contribsub2' )->rawParams( $user, $links )->params( $userObj->getName() );
	}

	/**
	 * Generates the namespace selector form with hidden attributes.
	 */
	private function getForm() {
		$opts = $this->mOpts;

		$formDescriptor = [
			'target' => [
				'type' => 'user',
				'name' => 'target',
				'label-message' => 'sp-contributions-username',
				'default' => $opts->getValue( 'target' ),
				'ipallowed' => true,
			],

			'namespace' => [
				'type' => 'namespaceselect',
				'name' => 'namespace',
				'label-message' => 'namespace',
				'all' => '',
			],
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setWrapperLegendMsg( 'sp-contributions-search' )
			->setSubmitTextMsg( 'sp-contributions-submit' )
			// prevent setting subpage and 'target' parameter at the same time
			->setAction( $this->getPageTitle()->getLocalURL() )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );
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

	protected function getGroupName() {
		return 'users';
	}
}

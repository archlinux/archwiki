<?php
/**
 * Implements Special:Log
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

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentityLookup;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Timestamp\TimestampException;

/**
 * A special page that lists log entries
 *
 * @ingroup SpecialPage
 */
class SpecialLog extends SpecialPage {

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var ActorNormalization */
	private $actorNormalization;

	/** @var UserIdentityLookup */
	private $userIdentityLookup;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param ILoadBalancer $loadBalancer
	 * @param ActorNormalization $actorNormalization
	 * @param UserIdentityLookup $userIdentityLookup
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		ILoadBalancer $loadBalancer,
		ActorNormalization $actorNormalization,
		UserIdentityLookup $userIdentityLookup
	) {
		parent::__construct( 'Log' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->loadBalancer = $loadBalancer;
		$this->actorNormalization = $actorNormalization;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		$out->addModules( 'mediawiki.userSuggest' );
		$out->addModuleStyles( 'mediawiki.interface.helpers.styles' );
		$this->addHelpLink( 'Help:Log' );

		$opts = new FormOptions;
		$opts->add( 'type', '' );
		$opts->add( 'user', '' );
		$opts->add( 'page', '' );
		$opts->add( 'pattern', false );
		$opts->add( 'year', null, FormOptions::INTNULL );
		$opts->add( 'month', null, FormOptions::INTNULL );
		$opts->add( 'day', null, FormOptions::INTNULL );
		$opts->add( 'tagfilter', '' );
		$opts->add( 'offset', '' );
		$opts->add( 'dir', '' );
		$opts->add( 'offender', '' );
		$opts->add( 'subtype', '' );
		$opts->add( 'logid', '' );

		// Set values
		$opts->fetchValuesFromRequest( $this->getRequest() );
		if ( $par !== null ) {
			$this->parseParams( $opts, (string)$par );
		}

		// Set date values
		$dateString = $this->getRequest()->getVal( 'wpdate' );
		if ( !empty( $dateString ) ) {
			try {
				$dateStamp = MWTimestamp::getInstance( $dateString . ' 00:00:00' );
			} catch ( TimestampException $e ) {
				// If users provide an invalid date, silently ignore it
				// instead of letting an exception bubble up (T201411)
				$dateStamp = false;
			}
			if ( $dateStamp ) {
				$opts->setValue( 'year', (int)$dateStamp->format( 'Y' ) );
				$opts->setValue( 'month', (int)$dateStamp->format( 'm' ) );
				$opts->setValue( 'day', (int)$dateStamp->format( 'd' ) );
			}
		}

		# Don't let the user get stuck with a certain date
		if ( $opts->getValue( 'offset' ) || $opts->getValue( 'dir' ) == 'prev' ) {
			$opts->setValue( 'year', '' );
			$opts->setValue( 'month', '' );
		}

		// If the user doesn't have the right permission to view the specific
		// log type, throw a PermissionsError
		// If the log type is invalid, just show all public logs
		$logRestrictions = $this->getConfig()->get( 'LogRestrictions' );
		$type = $opts->getValue( 'type' );
		if ( !LogPage::isLogType( $type ) ) {
			$opts->setValue( 'type', '' );
		} elseif ( isset( $logRestrictions[$type] )
			&& !$this->getAuthority()->isAllowed( $logRestrictions[$type] )
		) {
			throw new PermissionsError( $logRestrictions[$type] );
		}

		# Handle type-specific inputs
		$qc = [];
		if ( $opts->getValue( 'type' ) == 'suppress' ) {
			$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
			$offenderName = $opts->getValue( 'offender' );
			$offenderId = $this->actorNormalization->findActorIdByName( $offenderName, $dbr );
			if ( $offenderId ) {
				$qc = [ 'ls_field' => 'target_author_actor', 'ls_value' => $offenderId ];
			}
		} else {
			// Allow extensions to add relations to their search types
			$this->getHookRunner()->onSpecialLogAddLogSearchRelations(
				$opts->getValue( 'type' ), $this->getRequest(), $qc );
		}

		# Some log types are only for a 'User:' title but we might have been given
		# only the username instead of the full title 'User:username'. This part try
		# to lookup for a user by that name and eventually fix user input. See T3697.
		if ( in_array( $opts->getValue( 'type' ), self::getLogTypesOnUser( $this->getHookRunner() ) ) ) {
			# ok we have a type of log which expect a user title.
			$target = Title::newFromText( $opts->getValue( 'page' ) );
			if ( $target && $target->getNamespace() === NS_MAIN ) {
				# User forgot to add 'User:', we are adding it for him
				$opts->setValue( 'page',
					Title::makeTitleSafe( NS_USER, $opts->getValue( 'page' ) )
				);
			}
		}

		$this->show( $opts, $qc );
	}

	/**
	 * List log type for which the target is a user
	 * Thus if the given target is in NS_MAIN we can alter it to be an NS_USER
	 * Title user instead.
	 *
	 * @since 1.25
	 * @since 1.36 Added $runner parameter
	 *
	 * @param HookRunner|null $runner
	 * @return array
	 */
	public static function getLogTypesOnUser( HookRunner $runner = null ) {
		static $types = null;
		if ( $types !== null ) {
			return $types;
		}
		$types = [
			'block',
			'newusers',
			'rights',
		];

		( $runner ?? Hooks::runner() )->onGetLogTypesOnUser( $types );
		return $types;
	}

	/**
	 * Return an array of subpages that this special page will accept.
	 *
	 * @return string[] subpages
	 */
	public function getSubpagesForPrefixSearch() {
		$subpages = LogPage::validTypes();
		$subpages[] = 'all';
		sort( $subpages );
		return $subpages;
	}

	/**
	 * Set options based on the subpage title parts:
	 * - One part that is a valid log type: Special:Log/logtype
	 * - Two parts: Special:Log/logtype/username
	 * - Otherwise, assume the whole subpage is a username.
	 *
	 * @param FormOptions $opts
	 * @param string $par
	 */
	private function parseParams( FormOptions $opts, $par ) {
		# Get parameters
		$par = $par ?? '';
		$parms = explode( '/', $par );
		$symsForAll = [ '*', 'all' ];
		if ( $parms[0] != '' &&
			( in_array( $par, LogPage::validTypes() ) || in_array( $par, $symsForAll ) )
		) {
			$opts->setValue( 'type', $par );
		} elseif ( count( $parms ) == 2 ) {
			$opts->setValue( 'type', $parms[0] );
			$opts->setValue( 'user', $parms[1] );
		} elseif ( $par != '' ) {
			$opts->setValue( 'user', $par );
		}
	}

	private function show( FormOptions $opts, array $extraConds ) {
		# Create a LogPager item to get the results and a LogEventsList item to format them...
		$loglist = new LogEventsList(
			$this->getContext(),
			$this->getLinkRenderer(),
			LogEventsList::USE_CHECKBOXES
		);

		$pager = new LogPager(
			$loglist,
			$opts->getValue( 'type' ),
			$opts->getValue( 'user' ),
			$opts->getValue( 'page' ),
			$opts->getValue( 'pattern' ),
			$extraConds,
			$opts->getValue( 'year' ),
			$opts->getValue( 'month' ),
			$opts->getValue( 'day' ),
			$opts->getValue( 'tagfilter' ),
			$opts->getValue( 'subtype' ),
			$opts->getValue( 'logid' ),
			$this->linkBatchFactory,
			$this->loadBalancer,
			$this->actorNormalization
		);

		$this->addHeader( $opts->getValue( 'type' ) );

		# Set relevant user
		$performer = $pager->getPerformer();
		if ( $performer ) {
			$performerUser = $this->userIdentityLookup->getUserIdentityByName( $performer );
			if ( $performerUser ) {
				$this->getSkin()->setRelevantUser( $performerUser );
			}
		}

		# Show form options
		$loglist->showOptions(
			$pager->getType(),
			$performer,
			$pager->getPage(),
			$pager->getPattern(),
			$pager->getYear(),
			$pager->getMonth(),
			$pager->getDay(),
			$pager->getFilterParams(),
			$pager->getTagFilter(),
			$pager->getAction()
		);

		# Insert list
		$logBody = $pager->getBody();
		if ( $logBody ) {
			$this->getOutput()->addHTML(
				$pager->getNavigationBar() .
					$this->getActionButtons(
						$loglist->beginLogEventsList() .
							$logBody .
							$loglist->endLogEventsList()
					) .
					$pager->getNavigationBar()
			);
		} else {
			$this->getOutput()->addWikiMsg( 'logempty' );
		}
	}

	private function getActionButtons( $formcontents ) {
		$canRevDelete = $this->getAuthority()
			->isAllowedAll( 'deletedhistory', 'deletelogentry' );
		$showTagEditUI = ChangeTags::showTagEditingUI( $this->getAuthority() );
		# If the user doesn't have the ability to delete log entries nor edit tags,
		# don't bother showing them the button(s).
		if ( !$canRevDelete && !$showTagEditUI ) {
			return $formcontents;
		}

		# Show button to hide log entries and/or edit change tags
		$s = Html::openElement(
			'form',
			[ 'action' => wfScript(), 'id' => 'mw-log-deleterevision-submit' ]
		) . "\n";
		$s .= Html::hidden( 'action', 'historysubmit' ) . "\n";
		$s .= Html::hidden( 'type', 'logging' ) . "\n";

		// If no title is set, the fallback is to use the main page, as defined
		// by MediaWiki:Mainpage
		// On wikis where the main page can be translated, MediaWiki:Mainpage
		// is sometimes set to use Special:MyLanguage to redirect to the
		// appropriate version. This is interpreted as a special page, and
		// Action::getActionName forces the action to be 'view' if the title
		// cannot be used as a WikiPage, which includes all pages in NS_SPECIAL.
		// Set a dummy title to avoid this. The title provided is unused
		// by the SpecialPageAction class and does not matter.
		// See T205908
		$s .= Html::hidden( 'title', 'Unused' ) . "\n";

		$buttons = '';
		if ( $canRevDelete ) {
			$buttons .= Html::element(
				'button',
				[
					'type' => 'submit',
					'name' => 'revisiondelete',
					'value' => '1',
					'class' => "deleterevision-log-submit mw-log-deleterevision-button mw-ui-button"
				],
				$this->msg( 'showhideselectedlogentries' )->text()
			) . "\n";
		}
		if ( $showTagEditUI ) {
			$buttons .= Html::element(
				'button',
				[
					'type' => 'submit',
					'name' => 'editchangetags',
					'value' => '1',
					'class' => "editchangetags-log-submit mw-log-editchangetags-button mw-ui-button"
				],
				$this->msg( 'log-edit-tags' )->text()
			) . "\n";
		}

		$buttons .= ( new ListToggle( $this->getOutput() ) )->getHTML();

		$s .= $buttons . $formcontents . $buttons;
		$s .= Html::closeElement( 'form' );

		return $s;
	}

	/**
	 * Set page title and show header for this log type
	 * @param string $type
	 * @since 1.19
	 */
	protected function addHeader( $type ) {
		$page = new LogPage( $type );
		$this->getOutput()->setPageTitle( $page->getName() );
		$this->getOutput()->addHTML( $page->getDescription()
			->setContext( $this->getContext() )->parseAsBlock() );
	}

	protected function getGroupName() {
		return 'changes';
	}
}

<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\SpecialPage\ContributionsSpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\ContribsPager__getQueryInfoHook;
use MediaWiki\Specials\Hook\SpecialContributions__getForm__filtersHook;
use MediaWiki\Specials\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use OOUI\ButtonWidget;
use Wikimedia\Stats\StatsFactory;

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
class SpecialContributionsHandler implements
	SpecialContributionsBeforeMainOutputHook,
	SpecialContributions__getForm__filtersHook,
	ContribsPager__getQueryInfoHook
{
	use ContributionsRangeTrait;

	/**
	 * Prometheus counter metric name for any privileged (ip view) user viewing temporary account contributions
	 */
	public const PRIVILEGED_VIEW_TA_CONTRIBUTIONS = 'privileged_view_ta_contributions';

	/**
	 * Prometheus counter metric name for privileged views of connected temporary account contributions
	 */
	public const PRIVILEGED_VIEW_TA_CONNECTED_CONTRIBUTIONS = 'privileged_view_ta_connected_contributions';

	/**
	 * Prometheus counter metric name for privileged views of temporary accounts with other connected accounts
	 * but without viewing those contributions
	 */
	public const PRIVILEGED_VIEW_TA_SINGLE_CONTRIBUTIONS = 'privileged_view_ta_single_contributions';

	public function __construct(
		private readonly TempUserConfig $tempUserConfig,
		private readonly CheckUserTemporaryAccountsByIPLookup $checkUserTemporaryAccountsByIPLookup,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly DatabaseBlockStore $databaseBlockStore,
		private readonly StatsFactory $statsFactory
	) {
	}

	/** @inheritDoc */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( !$sp->getUser()->isRegistered() ) {
			return;
		}

		if ( $this->shouldShowCountFromAssociatedIPs( $user, $sp ) ) {
			$this->addCountFromAssociatedIPs( $user, $sp );
		}
		if ( $this->shouldShowCountFromThisIP( $user, $sp ) ) {
			$this->addCountFromThisIPSubtitle( $user->getName(), $sp );
		}
	}

	private function shouldShowCountFromAssociatedIPs( User $user, ContributionsSpecialPage $sp ): bool {
		if ( !$user->isRegistered() || !$this->tempUserConfig->isTempName( $user->getName() ) ) {
			return false;
		}

		// If the user is hidden, and the authority doesn't have the `hideuser` right,
		// then pretend that the user doesn't exist
		if ( $user->isHidden() && !$sp->getAuthority()->isAllowed( 'hideuser' ) ) {
			return false;
		}

		return true;
	}

	private function shouldShowCountFromThisIP( UserIdentity $user, ContributionsSpecialPage $sp ): bool {
		if (
			!$this->tempUserConfig->isKnown() ||
			$user->isRegistered() ||
			!$this->isValidIPOrQueryableRange( $user->getName(), $sp->getConfig() )
		) {
			return false;
		}

		// The permissions are checked by CheckUserTemporaryAccountsByIPLookup, from addCountFromThisIPSubtitle
		return true;
	}

	private function addCountFromAssociatedIPs( UserIdentity $tempUser, ContributionsSpecialPage $sp ) {
		// 101 is the maximum number of accounts we care about as defined by T412212
		$exactCount = $this->checkUserTemporaryAccountsByIPLookup
			->getAggregateActiveTempAccountCount( $tempUser, 101 );
		$out = $sp->getOutput();
		$out->enableOOUI();
		$performerCanShowRelatedTemporaryAccounts = $this
			->canShowRelatedTemporaryAccounts( $sp->getName(), $tempUser->getName(), $sp->getUser() );

		// Record when a user with ip view rights visits Special:Contributions for a temporary account; See T416591.
		if ( $performerCanShowRelatedTemporaryAccounts ) {
			$this->statsFactory
				->withComponent( 'CheckUser' )
				->getCounter( self::PRIVILEGED_VIEW_TA_CONTRIBUTIONS )
				->increment();
		}

		if (
			$exactCount > 1 &&
			$performerCanShowRelatedTemporaryAccounts
		) {
			// To show the exact count, use the -min message, which shows a single value
			$countMsg = $sp->msg( 'checkuser-temporary-account-bucketcount-min' )
				->params( $exactCount );
			$warningMsg = $sp->msg( 'checkuser-contributions-temporary-account-bucketcount' )
				->params( $countMsg );

			$showingRelated = $sp->getRequest()->getBool( 'showRelatedTemporaryAccounts' );
			$linkMsgKey = $showingRelated ?
				'checkuser-contributions-temporary-accounts-hide-related' :
				'checkuser-contributions-temporary-accounts-show-related';

			$title = $sp->getPageTitle();
			$queryParams = $sp->getRequest()->getQueryValues();
			$queryParams['showRelatedTemporaryAccounts'] = $showingRelated ? 0 : 1;

			$link = Html::rawElement(
				'a',
				[ 'href' => $title->getFullUrl( $queryParams ) ],
				$sp->msg( $linkMsgKey )->parse()
			);

			// Generate list of all the related temporary accounts found
			$listOfTempAccounts = '';
			$toClipboardButton = '';
			$relatedTempAccounts = $this->checkUserTemporaryAccountsByIPLookup
				->getActiveTempAccountNames( $sp->getUser(), $tempUser, 101 )->value;
			if ( $relatedTempAccounts ) {
				$listOfTempAccounts .= Html::openElement( 'div', [ 'class' => 'ext-checkuser-related-tas' ] );

				// This checkbox controls visibility via CSS and will be hidden.
				// It exists here, outside of visual order, to support visibility toggling via css
				// because mediawiki's browser support doesn't broadly support :has.
				// See ext.checkUser.specialContributions/contributions.less for implementation details.
				$listOfTempAccounts .= Html::check(
					'ext-checkuser-related-tas-list-visibility',
					false,
					[
						'id' => 'ext-checkuser-related-tas-list-visibility',
					]
				);

				// Collapse list when over 5 accounts, per T417222
				$listClasses = 'ext-checkuser-related-tas-list';
				if ( count( $relatedTempAccounts ) > 5 ) {
					$listClasses .= ' ext-checkuser-taslist-collapsible';
				}
				$listOfTempAccounts .= Html::openElement( 'ul', [ 'class' => $listClasses ] );
				foreach ( $relatedTempAccounts as $tempAccountName ) {
					$targetTempAccount = $this->userIdentityLookup->getUserIdentityByName( $tempAccountName );
					if ( !$targetTempAccount ) {
						continue;
					}

					// "TA username (talk | block | etc.)"
					$services = MediaWikiServices::getInstance();
					$userLink = $services->getUserLinkRenderer()->userLink( $targetTempAccount, $sp->getContext() );
					$userToolLinks = Linker::userToolLinks(
						$targetTempAccount->getId(),
						$targetTempAccount->getName(),
						true
					);
					$listOfTempAccounts .= Html::rawElement(
						'li',
						[ 'class' => 'ext-checkuser-related-ta' ],
						Html::rawElement( 'bdi', [], $userLink . $userToolLinks )
					);
				}
				$listOfTempAccounts .= Html::closeElement( 'ul' );

				// Label associated with the checkbox, which allows it to be clicked in lieu of the hidden checkbox
				$visibilityToggleLabel = Html::openElement(
					'label',
					[
						'class' => 'ext-checkuser-related-tas-toggle',
						'for' => 'ext-checkuser-related-tas-list-visibility',
					]
				);
				$visibilityToggleLabel .= Html::rawElement(
					'span',
					[ 'class' => 'ext-checkuser-related-tas-list-visibility-showlabel' ],
					$sp->msg( 'checkuser-contributions-temporary-accounts-related-list-toggle-show' )->parse()
				);
				$visibilityToggleLabel .= Html::rawElement(
					'span',
					[ 'class' => 'ext-checkuser-related-tas-list-visibility-hidelabel' ],
					$sp->msg( 'checkuser-contributions-temporary-accounts-related-list-toggle-hide' )->parse()
				);
				$visibilityToggleLabel .= Html::closeElement( 'label' );
				$listOfTempAccounts .= $visibilityToggleLabel;

				$listOfTempAccounts .= Html::closeElement( 'div' );

				$toClipboardButton = new ButtonWidget( [
					'label' => $sp->msg( 'checkuser-contributions-temporary-accounts-related-list-copy-button' ),
					'icon' => 'copy',
					'classes' => [ 'ext-checkuser-related-tas-list-copy-button' ],
				] );
			}

			$warningMsg = implode( ' ', [ $warningMsg, $link, $listOfTempAccounts, $toClipboardButton ] );
			$out->addHTML( Html::noticeBox( $warningMsg ) );

			// Record if the user is viewing the connected contributions or not; See T416591.
			$viewType = $showingRelated ?
				self::PRIVILEGED_VIEW_TA_CONNECTED_CONTRIBUTIONS :
				self::PRIVILEGED_VIEW_TA_SINGLE_CONTRIBUTIONS;
			$this->statsFactory
				->withComponent( 'CheckUser' )
				->getCounter( $viewType )
				->increment();
		} else {
			[ $bucketRangeStart, $bucketRangeEnd ] = $this->checkUserTemporaryAccountsByIPLookup
				->getBucketedCount( $exactCount );

			$bucketMsgKey = 'checkuser-temporary-account-bucketcount-';
			if ( $bucketRangeStart === $bucketRangeEnd ) {
				if ( $bucketRangeStart > 1 ) {
					// For 0 or 1 we show the exact count, using the min message,
					// which shows a single value
					$bucketMsgKey .= 'max';
				} else {
					$bucketMsgKey .= 'min';
				}
			} else {
				$bucketMsgKey .= 'range';
			}

			// Uses:
			// * checkuser-temporary-account-bucketcount-min
			// * checkuser-temporary-account-bucketcount-range
			// * checkuser-temporary-account-bucketcount-max
			$bucketMsg = $sp->msg( $bucketMsgKey )
				->numParams( $bucketRangeStart, $bucketRangeEnd )
				->text();

			$subtitleMsg = $sp->msg( 'checkuser-contributions-temporary-account-bucketcount' )
				->params( $bucketMsg );

			$out->addSubtitle( $subtitleMsg );
		}
	}

	private function addCountFromThisIPSubtitle( string $ip, ContributionsSpecialPage $sp ) {
		$limit = 100;
		// We're not displaying the account names, so we don't log it
		$accountsStatus = $this->checkUserTemporaryAccountsByIPLookup->get( $ip, $sp->getAuthority(), false, $limit );
		if ( !$accountsStatus->isGood() ) {
			// Insufficient permissions
			return;
		}
		$accounts = $accountsStatus->getValue();

		$excludeHiddenAccounts = !$sp->getAuthority()->isAllowed( 'hideuser' );
		$blockedAccounts = $this->countBlockedAccounts( $accounts, $excludeHiddenAccounts );
		$numAccounts = count( $accounts );

		if ( $numAccounts < $limit ) {
			if ( $blockedAccounts > 0 ) {
				$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip-with-blocked' )
					->numParams( $numAccounts, $blockedAccounts );
			} else {
				$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip' )
					->numParams( $numAccounts );
			}
		} else {
			// Show version with blocks, even if it's going to be "0+ blocks" - to signify we're uncertain
			$msg = $sp->msg( 'checkuser-contributions-temporary-accounts-on-ip-with-blocked' )->params(
				$sp->msg( 'checkuser-temporary-account-bucketcount-max', $numAccounts ),
				$sp->msg( 'checkuser-temporary-account-bucketcount-max', $blockedAccounts ),
			);
		}
		$out = $sp->getOutput();
		$out->addSubtitle( $msg );
	}

	private function countBlockedAccounts( array $userNames, bool $excludeHidden ): int {
		if ( !$userNames ) {
			return 0;
		}

		$users = $this->userIdentityLookup->newSelectQueryBuilder()
			->whereUserNames( $userNames )
			->caller( __METHOD__ )
			->fetchUserIdentities();
		$userIds = [];
		foreach ( $users as $user ) {
			$userIds[] = $user->getId();
		}

		$conds = [ 'bt_user' => $userIds ];
		if ( $excludeHidden ) {
			$conds['bl_deleted'] = 0;
		}
		$blocks = $this->databaseBlockStore->newListFromConds( $conds );
		$blocksByUser = [];
		foreach ( $blocks as $block ) {
			$blocksByUser[$block->getTargetUserIdentity()->getId()][] = $block;
		}

		return count( $blocksByUser );
	}

	/** @inheritDoc */
	public function onSpecialContributions__getForm__filters( $sp, &$filters ) {
		$target = $sp->getRequest()->getText( 'target' );

		if ( !$this->canShowRelatedTemporaryAccounts( $sp->getName(), $target, $sp->getUser() ) ) {
			return;
		}

		$filters[] = [
			'type' => 'check',
			'label-message' => 'checkuser-contributions-filters-related-temporary-accounts',
			'name' => 'showRelatedTemporaryAccounts',
		];
	}

	/** @inheritDoc */
	public function onContribsPager__getQueryInfo( $pager, &$queryInfo ) {
		$title = $pager->getContext()->getTitle();
		if ( !$title ) {
			return;
		}
		$pageName = $this->specialPageFactory->resolveAlias( $title->getDBKey() )[0];
		if ( !$pageName ) {
			return;
		}

		$request = $pager->getContext()->getRequest();
		$target = $request->getText( 'target' );

		if ( !$this->canShowRelatedTemporaryAccounts(
			$pageName,
			$target,
			$pager->getUser()
		) ) {
			return;
		}

		if ( !isset( $queryInfo['conds']['actor_name'] ) ) {
			// This shouldn't happen, but adding in related targets when there isn't a primary
			// target set wouldn't make sense, so return early.
			return;
		}

		if ( $request->getBool( 'showRelatedTemporaryAccounts' ) ) {
			$targetUser = $this->userIdentityLookup->getUserIdentityByName(
				$queryInfo['conds']['actor_name']
			);

			if ( !$targetUser ) {
				return;
			}

			$status = $this->checkUserTemporaryAccountsByIPLookup
				->getActiveTempAccountNames( $pager->getUser(), $targetUser );
			if ( !$status->isGood() ) {
				return;
			}

			$queryInfo['conds']['actor_name'] = [ $target, ...$status->getValue() ];
		}
	}

	private function canShowRelatedTemporaryAccounts(
		string $pageName,
		string $target,
		Authority $authority
	): bool {
		if ( $pageName !== 'Contributions' ) {
			return false;
		}

		if ( !$this->tempUserConfig->isTempName( $target ) ) {
			return false;
		}

		$status = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses(
			$authority
		);
		if ( !$status->isGood() ) {
			return false;
		}

		return true;
	}
}

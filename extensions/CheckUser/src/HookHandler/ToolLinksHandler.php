<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Hook\UserToolLinksEditHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\Hook\ContributionsToolLinksHook;
use MediaWiki\Specials\Hook\SpecialContributionsBeforeMainOutputHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityUtils;
use MobileContext;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;
use Wikimedia\IPUtils;

class ToolLinksHandler implements
	SpecialContributionsBeforeMainOutputHook,
	ContributionsToolLinksHook,
	UserToolLinksEditHook
{

	public function __construct(
		private readonly CheckUserPermissionManager $cuPermissionManager,
		private readonly PermissionManager $permissionManager,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly LinkRenderer $linkRenderer,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly UserIdentityUtils $userIdentityUtils,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly TempUserConfig $tempUserConfig,
		private readonly SuggestedInvestigationsCaseLookupService $suggestedInvestigationsCaseLookupService,
		private readonly ?MobileContext $mobileContext,
	) {
	}

	/**
	 * Determine whether a user is able to reveal IP, based on their rights
	 * and preferences.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function userCanRevealIP( UserIdentity $user ) {
		return $this->permissionManager->userHasRight(
			$user,
			'checkuser-temporary-account-no-preference'
		) ||
			(
				$this->permissionManager->userHasRight(
					$user,
					'checkuser-temporary-account'
				) &&
				$this->userOptionsLookup->getOption(
					$user,
					'checkuser-temporary-account-enable'
				)
			);
	}

	/**
	 * @return bool Whether the user is in mobile view. This will always be false if MobileFrontend is not loaded.
	 */
	private function isMobile(): bool {
		return $this->mobileContext && $this->mobileContext->shouldDisplayMobileView();
	}

	/**
	 * Determine whether a user is able to see archived contributions.
	 *
	 * @param UserIdentity $user
	 * @return bool
	 */
	private function userCanSeeDeleted( UserIdentity $user ) {
		return $this->permissionManager->userHasRight( $user, 'deletedhistory' );
	}

	/** @inheritDoc */
	public function onSpecialContributionsBeforeMainOutput( $id, $user, $sp ) {
		if ( !in_array( $sp->getName(), [ 'Contributions', 'IPContributions', 'DeletedContributions' ] ) ) {
			return;
		}

		if (
			!$this->tempUserConfig->isKnown() ||
			!IPUtils::isIPAddress( $user->getName() ) ||
			!$this->userCanRevealIP( $sp->getUser() )
		) {
			return;
		}

		$isArchive = $sp->getRequest()->getBool( 'isArchive' ) ||
			$sp->getName() === 'DeletedContributions';
		if ( $isArchive && !$this->userCanSeeDeleted( $sp->getUser() ) ) {
			return;
		}

		// Pages showing archived contributions should link to each other.
		$ipContributionsUrl = SpecialPage::getTitleFor(
			'IPContributions',
			$user->getName()
		)->getLinkURL(
			[ 'isArchive' => $isArchive ]
		);
		$contributionsUrl = SpecialPage::getTitleFor(
			$isArchive ? 'DeletedContributions' : 'Contributions',
			$user->getName()
		)->getLinkURL();

		// Generate the button text for the IPContributions link. If the user should be in mobile mode (as
		// defined by MobileFrontend), then append '-mobile' to the message key to get a shortened version.
		$ipContributionsButtonMessageKey = 'checkuser-ip-contributions-special-ip-contributions-button';
		if ( $this->isMobile() ) {
			$ipContributionsButtonMessageKey .= '-mobile';
		}

		$buttons = new ButtonGroupWidget( [
			'items' => [
				new ButtonWidget( [
					'label' => $sp->msg( $ipContributionsButtonMessageKey )->text(),
					'href' => $ipContributionsUrl,
					'active' => $sp->getName() === 'IPContributions',
				] ),
				new ButtonWidget( [
					'label' => $sp->msg( 'checkuser-ip-contributions-special-contributions-button' )->text(),
					'href' => $contributionsUrl,
					'active' => in_array( $sp->getName(), [ 'Contributions', 'DeletedContributions' ] ),
				] ),
			],
		] );

		// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
		$sp->getOutput()->addSubtitle( $buttons );
	}

	/**
	 * Add links to CheckUser interfaces (CheckUser, Investigate, IPContributions, ...)
	 * on Special:Contributions/<username> for privileged users.
	 *
	 * @inheritDoc
	 */
	public function onContributionsToolLinks(
		$id,
		Title $title,
		array &$tools,
		SpecialPage $specialPage
	) {
		$targetUserIdentity = $id ? $this->userIdentityLookup->getUserIdentityByUserId( $id ) : null;
		$user = $specialPage->getUser();
		$linkRenderer = $specialPage->getLinkRenderer();

		if (
			( $specialPage->getName() === 'IPContributions' && $this->userCanRevealIP( $user ) ) ||
			$specialPage->getName() === 'Contributions' ||
			$specialPage->getName() === 'DeletedContributions'
		) {
			if ( $specialPage->getName() === 'IPContributions' ) {
				if ( $specialPage->getRequest()->getBool( 'isArchive' ) ) {
					// Use the same key to ensure the link is added in the same position
					$tools['deletedcontribs'] = $linkRenderer->makeKnownLink(
						SpecialPage::getTitleFor( 'IPContributions', $title->getText() ),
						$specialPage->msg( 'checkuser-ip-contributions-contributions-link' )->text(),
						[ 'class' => 'mw-contributions-link-check-user-ip-contributions' ],
					);
				} elseif ( $this->userCanSeeDeleted( $user ) ) {
					$tools['deletedcontribs'] = $linkRenderer->makeKnownLink(
						SpecialPage::getTitleFor( 'IPContributions', $title->getText() ),
						$specialPage->msg( 'checkuser-ip-contributions-deleted-contributions-link' )->text(),
						[ 'class' => 'mw-contributions-link-check-user-ip-contributions' ],
						[ 'isArchive' => true ]
					);
				}
			}

			$gcAccess = $this->cuPermissionManager->canAccessUserGlobalContributions(
				$user,
				$title->getText()
			);

			if ( $gcAccess->isGood() ) {
				$globalContributionsLink = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'GlobalContributions', $title->getText() ),
					$specialPage->msg( 'checkuser-global-contributions-link' )->text(),
					[ 'class' => 'mw-contributions-link-check-user-global-contributions' ],
				);
				$index = array_search( 'deletedcontribs', array_keys( $tools ) );
				if ( $index !== false ) {
					// Insert the global contributions link after the 'deletedcontribs' key
					$index += 1;
					$tools = array_merge(
						array_slice( $tools, 0, $index ),
						[ 'global-contributions' => $globalContributionsLink ],
						array_slice( $tools, $index )
					);
				} else {
					$tools['global-contributions'] = $globalContributionsLink;
				}
			}
		}

		if ( $this->permissionManager->userHasRight( $user, 'checkuser' ) ) {
			$tools['checkuser'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUser' ),
				$specialPage->msg( 'checkuser-contribs' )->text(),
				[ 'class' => 'mw-contributions-link-check-user' ],
				[ 'user' => $title->getText() ]
			);
		}
		if ( $this->permissionManager->userHasRight( $user, 'checkuser-log' ) ) {
			$tools['checkuser-log'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$specialPage->msg( 'checkuser-contribs-log' )->text(),
				[ 'class' => 'mw-contributions-link-check-user-log' ],
				[ 'cuSearch' => $title->getText() ]
			);
			if ( $targetUserIdentity && $this->userIdentityUtils->isNamed( $targetUserIdentity ) ) {
				$tools['checkuser-log-initiator'] = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'CheckUserLog' ),
					$specialPage->msg( 'checkuser-contribs-log-initiator' )->text(),
					[ 'class' => 'mw-contributions-link-check-user-initiator' ],
					[ 'cuInitiator' => $title->getText() ]
				);
			}
		}

		if (
			$targetUserIdentity &&
			$this->suggestedInvestigationsCaseLookupService->areSuggestedInvestigationsEnabled() &&
			$this->permissionManager->userHasRight( $user, 'checkuser-suggested-investigations' ) &&
			$this->suggestedInvestigationsCaseLookupService->isUserInAnyCase( $targetUserIdentity )
		) {
			// Needed to instrument the tool link added below
			$specialPage->getOutput()->addModules( 'ext.checkUser.suggestedInvestigations' );

			$tools['suggested-investigations'] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'SuggestedInvestigations' ),
				$specialPage->msg( 'checkuser-suggestedinvestigations-contributions-tool-link' )->text(),
				[ 'class' => 'mw-contributions-link-suggested-investigations' ],
				[
					'username' => $title->getText(),
					// We want to show all cases the user is in, even if the current user has no edits
					// The default of this filter is to exclude zero-edit users, so we need to
					// explicitly disable it here
					'hideCasesWithNoUserEdits' => 0,
				]
			);
		}
	}

	/** @inheritDoc */
	public function onUserToolLinksEdit( $userId, $userText, &$items ) {
		$requestTitle = RequestContext::getMain()->getTitle();
		if (
			$requestTitle !== null &&
			$requestTitle->isSpecialPage()
		) {
			$specialPageName = $this->specialPageFactory->resolveAlias( $requestTitle->getText() )[0];
			if ( $specialPageName === 'CheckUserLog' ) {
				$items[] = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CheckUserLog', $userText ),
					wfMessage( 'checkuser-log-checks-on' )->text()
				);
			} elseif ( $specialPageName === 'CheckUser' ) {
				$items[] = $this->linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'CheckUser', $userText ),
					wfMessage( 'checkuser-toollink-check' )->text(),
					[],
					[ 'reason' => RequestContext::getMain()->getRequest()->getVal( 'reason', '' ) ]
				);
			}
		}
	}
}

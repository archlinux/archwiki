<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Services\UserInfoCardBlockStatusCache;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Hook\UserLinkRendererUserLinkPostRenderHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;

class UserLinkRendererUserLinkPostRenderHandler implements UserLinkRendererUserLinkPostRenderHook {

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly UserNameUtils $userNameUtils,
		private readonly Config $config,
		private readonly UserInfoCardBlockStatusCache $blockStatusCache,
	) {
	}

	public function onUserLinkRendererUserLinkPostRender(
		UserIdentity $targetUser,
		IContextSource $context,
		string &$html,
		string &$prefix,
		string &$postfix
	) {
		if ( !$targetUser->isRegistered() ) {
			return;
		}
		if ( $this->userOptionsLookup->getBoolOption( $context->getUser(), Preferences::ENABLE_USER_INFO_CARD ) ) {
			$output = $context->getOutput();
			$output->addModuleStyles( 'ext.checkUser.styles' );
			$output->addModules( 'ext.checkUser.userInfoCard' );

			$isBlocked = $this->blockStatusCache->isIndefinitelyBlockedOrLocked( $targetUser->getName() );

			if ( $isBlocked ) {
				$iconClass = 'userBlocked';
			} elseif ( $this->userNameUtils->isTemp( $targetUser->getName() ) ) {
				$iconClass = 'userTemporary';
			} else {
				$iconClass = 'userAvatar';
			}
			// CSS-only Codex icon button
			$icon = Html::rawElement(
				'span',
				[
					'class' =>
						'cdx-button__icon ext-checkuser-userinfocard-button__icon ' .
						"ext-checkuser-userinfocard-button__icon--$iconClass",
				]
			);
			$markup = Html::rawElement(
				'a',
				[
					'href' => 'javascript:void(0)',
					'role' => 'button',
					'aria-label' => $context->msg(
						'checkuser-userinfocard-toggle-button-aria-label',
						$targetUser->getName()
					)->text(),
					'aria-haspopover' => 'dialog',
					'class' => "ext-checkuser-userinfocard-button cdx-button " .
						'cdx-button--action-default cdx-button--weight-quiet cdx-button--fake-button ' .
						'cdx-button--fake-button--enabled cdx-button--icon-only',
					'data-username' => $targetUser->getName(),
				],
				$icon
			);
			$prefix .= $markup;
		}
	}
}

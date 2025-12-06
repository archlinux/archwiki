<?php

namespace MediaWiki\CheckUser\HookHandler;

use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Hook\UserLinkRendererUserLinkPostRenderHook;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;

class UserLinkRendererUserLinkPostRenderHandler implements UserLinkRendererUserLinkPostRenderHook {

	private UserOptionsLookup $userOptionsLookup;
	private UserNameUtils $userNameUtils;
	private Config $config;

	public function __construct(
		UserOptionsLookup $userOptionsLookup,
		UserNameUtils $userNameUtils,
		Config $config
	) {
		$this->userOptionsLookup = $userOptionsLookup;
		$this->userNameUtils = $userNameUtils;
		$this->config = $config;
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

			if ( $this->config->has( 'GEUserImpactMaxEdits' ) ) {
				$output->addJsConfigVars( [
					'wgCheckUserGEUserImpactMaxEdits' => $this->config->get( 'GEUserImpactMaxEdits' ),
				] );
			}

			if ( $this->config->has( 'GEUserImpactMaxThanks' ) ) {
				$output->addJsConfigVars( [
					'wgCheckUserGEUserImpactMaxThanks' => $this->config->get( 'GEUserImpactMaxThanks' ),
				] );
			}

			$output->addJsConfigVars(
				'wgCheckUserEnableUserInfoCardInstrumentation',
				$this->config->get( 'CheckUserEnableUserInfoCardInstrumentation' )
			);

			$output->addJsConfigVars(
				'wgCheckUserUserInfoCardShowXToolsLink',
				$this->config->get( 'CheckUserUserInfoCardShowXToolsLink' )
			);

			$iconClass = $this->userNameUtils->isTemp( $targetUser->getName() ) ? 'userTemporary' : 'userAvatar';
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
						'checkuser-userinfocard-toggle-button-aria-label', $targetUser->getName()
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

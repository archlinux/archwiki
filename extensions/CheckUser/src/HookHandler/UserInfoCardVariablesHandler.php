<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\User\Options\UserOptionsLookup;

readonly class UserInfoCardVariablesHandler implements MakeGlobalVariablesScriptHook {
	public function __construct( private UserOptionsLookup $options ) {
	}

	/**
	 * Set the UserInfoCard config vars if checkuser-userinfocard-enable is true
	 * @inheritDoc
	 */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		if ( !$this->options->getBoolOption(
			$out->getUser(),
			Preferences::ENABLE_USER_INFO_CARD
		) ) {
			return;
		}

		$config = $out->getConfig();

		if ( $config->has( 'GEUserImpactMaxEdits' ) ) {
			$vars['wgCheckUserGEUserImpactMaxEdits'] =
				$config->get( 'GEUserImpactMaxEdits' );
		}

		if ( $config->has( 'GEUserImpactMaxThanks' ) ) {
			$vars['wgCheckUserGEUserImpactMaxThanks'] =
				$config->get( 'GEUserImpactMaxThanks' );
		}

		$vars['wgCheckUserEnableUserInfoCardInstrumentation'] =
			$config->get( 'CheckUserEnableUserInfoCardInstrumentation' );

		$vars['wgCheckUserUserInfoCardShowXToolsLink'] =
			$config->get( 'CheckUserUserInfoCardShowXToolsLink' );
	}
}

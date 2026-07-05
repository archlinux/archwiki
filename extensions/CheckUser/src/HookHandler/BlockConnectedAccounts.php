<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Api\ApiBlock;
use MediaWiki\Api\Hook\ApiBlockSucceededHook;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Block\BlockTargetFactory;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentityLookup;
use RuntimeException;

/**
 * Hooks that support blocking connected temporary accounts from Special:Block
 */
class BlockConnectedAccounts implements ApiBlockSucceededHook, APIGetAllowedParamsHook {
	public function __construct(
		private readonly TempUserConfig $tempUserConfig,
		private readonly BlockTargetFactory $blockTargetFactory,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly CheckUserPermissionManager $checkUserPermissionManager
	) {
	}

	/**
	 * Add the `blockConnectedTempAccounts` parameter to calls to ApiBlock if the user is allowed
	 * to perform the connected temp accounts block action.
	 *
	 * @inheritDoc
	 */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		if (
			$module instanceof ApiBlock &&
			$this->checkUserPermissionManager
				->canAccessTemporaryAccountIPAddresses( $module->getAuthority() )->isGood()
		) {
			$params['blockConnectedTempAccounts'] = false;
		}
	}

	/**
	 * If the block should also block connected temporary accounts,
	 * attempt to do so and return the statuses to the API response.
	 *
	 * @inheritDoc
	 */
	public function onApiBlockSucceeded( $module, $performer, $mainTarget, $params, &$additionalBlocksStatuses ) {
		$mainTargetName = $mainTarget->getName();
		if (
			!isset( $params['blockConnectedTempAccounts'] ) ||
			!$params['blockConnectedTempAccounts'] ||
			!$this->tempUserConfig->isTempName( $mainTargetName ) ||
			!$this->checkUserPermissionManager
				->canAccessTemporaryAccountIPAddresses( $performer )->isGood()
		) {
			return;
		}

		// Get the connected accounts and validate that the additional targets
		$checkUserTemporaryAccountsByIPLookup = MediaWikiServices::getInstance()
			->getService( 'CheckUserTemporaryAccountsByIPLookup' );
		$connectedAccounts = $checkUserTemporaryAccountsByIPLookup->getActiveTempAccountNames(
			$performer,
			$mainTarget,
			101
		)->value ?? [];
		$connectedAccounts = array_diff( $connectedAccounts, [ $mainTarget->getName() ] );

		// This call is limited to groups of 15 accounts or fewer. See T419526#11731721.
		// The front-end should prevent attempts too large but enforce it here as well.
		if ( count( $connectedAccounts ) > 15 ) {
			throw new RuntimeException( "Too many accounts found to block." );
		}

		foreach ( $connectedAccounts as $connectedAccountName ) {
			$additionalTargetUser = $this->userIdentityLookup
				->getUserIdentityByName( $connectedAccountName );
			if ( !$additionalTargetUser ) {
				continue;
			}
			$additionalTargetBlockTarget = $this
				->blockTargetFactory->newFromUser( $additionalTargetUser );
			$additionalTargetBlock = $module->insertBlock( $additionalTargetBlockTarget, $params );
			$additionalTargetBlockStatus = $additionalTargetBlock->getMessages();

			// API returns pregenerated html; behave similarly here
			$additionalBlocksStatuses[ $connectedAccountName ] = [];
			foreach ( $additionalTargetBlockStatus as $message ) {
				$additionalBlocksStatuses[ $connectedAccountName ][] =
					( new Message( $message->getKey(), $message->getParams() ) )->parse();
			}
		}
	}
}

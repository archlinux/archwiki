<?php
/**
 * DiscussionTools hooks for listening to our own hooks
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\DiscussionTools\OverflowMenuItem;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserNameUtils;

class DiscussionToolsHooks implements
	DiscussionToolsAddOverflowMenuItemsHook
{
	private Config $config;
	private UserNameUtils $userNameUtils;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		ConfigFactory $configFactory,
		UserNameUtils $userNameUtils,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->config = $configFactory->makeConfig( 'discussiontools' );
		$this->userNameUtils = $userNameUtils;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @param OverflowMenuItem[] &$overflowMenuItems
	 * @param string[] &$resourceLoaderModules
	 * @param array $threadItemData
	 * @param IContextSource $contextSource
	 * @return bool|void
	 */
	public function onDiscussionToolsAddOverflowMenuItems(
		array &$overflowMenuItems,
		array &$resourceLoaderModules,
		array $threadItemData,
		IContextSource $contextSource
	) {
		if (
			( $threadItemData['type'] ?? null ) === 'heading' &&
			!( $threadItemData['uneditableSection'] ?? false ) &&
			$contextSource->getSkin()->getSkinName() === 'minerva'
		) {
			$overflowMenuItems[] = new OverflowMenuItem(
				'edit',
				'edit',
				'skin-view-edit',
				2
			);
		}

		$user = $contextSource->getUser();
		if (
			$this->config->get( 'DiscussionToolsEnableThanks' ) ||
			(
				$this->config->get( 'DiscussionToolsBeta' ) &&
				$this->userOptionsLookup->getOption( $user, 'discussiontools-betaenable', 0 )
			)
		) {
			$showThanks = ExtensionRegistry::getInstance()->isLoaded( 'Thanks' );
			if ( $showThanks && ( $threadItemData['type'] ?? null ) === 'comment' && $user->isNamed() ) {
				$recipient = $this->userNameUtils->getCanonical( $threadItemData['author'], UserNameUtils::RIGOR_NONE );

				if (
					$recipient !== $user->getName() &&
					!$this->userNameUtils->isIP( $recipient )
				) {
					$overflowMenuItems[] = new OverflowMenuItem(
						'thank',
						'heart',
						'thanks-button-thank'
					);
				}
			}
		}
	}
}

<?php

namespace MediaWiki\Extension\DiscussionTools\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\MessageLocalizer;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	DiscussionToolsTermsOfUseMessagesHook,
	DiscussionToolsAddOverflowMenuItemsHook
{
	public function __construct( private readonly HookContainer $hookContainer ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onDiscussionToolsTermsOfUseMessages( array &$messages, MessageLocalizer $context, Config $config ) {
		return $this->hookContainer->run(
			'DiscussionToolsTermsOfUseMessages',
			[ &$messages, $context, $config ]
		);
	}

	/** @inheritDoc */
	public function onDiscussionToolsAddOverflowMenuItems(
		array &$overflowMenuItems,
		array &$resourceLoaderModules,
		array $threadItemData,
		IContextSource $contextSource
	) {
		return $this->hookContainer->run(
			'DiscussionToolsAddOverflowMenuItems',
			[
				&$overflowMenuItems,
				&$resourceLoaderModules,
				$threadItemData,
				$contextSource,
			]
		);
	}
}

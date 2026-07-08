<?php

namespace MediaWiki\Minerva\Hooks;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Minerva\SkinOptions;
use MediaWiki\Skin\Skin;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	SkinMinervaOptionsInitHook
{
	public function __construct( private readonly HookContainer $hookContainer ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinMinervaOptionsInit( Skin $skin, SkinOptions $skinOptions ) {
		return $this->hookContainer->run(
			'SkinMinervaOptionsInit',
			[ $skin, $skinOptions ]
		);
	}
}

<?php

namespace MediaWiki\Extension\Math\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

class ResourceLoaderConfigHandler implements
	ResourceLoaderGetConfigVarsHook
{

	public function __construct(
		private readonly MathConfig $mathConfig,
	) {
	}

	/** @inheritDoc */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgMathEntitySelectorUrl'] = $this->mathConfig->getMathEntitySelectorUrl();
	}
}

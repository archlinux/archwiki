<?php

namespace Vector\ResourceLoader;

use ResourceLoaderContext;
use ResourceLoaderUserModule;
use Vector\Constants;

class VectorResourceLoaderUserModule extends ResourceLoaderUserModule {
	/**
	 * @inheritDoc
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		$skin = $context->getSkin();
		$config = $this->getConfig();
		$user = $context->getUserObj();
		$pages = [];
		if ( $config->get( 'AllowUserCss' ) && !$user->isAnon() && ( $skin === Constants::SKIN_NAME_MODERN ) ) {
			$userPage = $user->getUserPage()->getPrefixedDBkey();
			$pages["$userPage/vector.js"] = [ 'type' => 'script' ];
		}
		return $pages;
	}
}

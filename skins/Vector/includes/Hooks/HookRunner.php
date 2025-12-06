<?php

namespace MediaWiki\Skins\Vector\Hooks;

use MediaWiki\HookContainer\HookContainer;

/**
 * @internal
 */
class HookRunner implements VectorSearchResourceLoaderConfigHook {
	public function __construct( private readonly HookContainer $hookContainer ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onVectorSearchResourceLoaderConfig( array &$vectorSearchConfig ): void {
		$this->hookContainer->run(
			'VectorSearchResourceLoaderConfig',
			[ &$vectorSearchConfig ]
		);
	}
}

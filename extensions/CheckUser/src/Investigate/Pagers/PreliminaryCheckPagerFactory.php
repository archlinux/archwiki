<?php

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserFactory;

class PreliminaryCheckPagerFactory implements PagerFactory {
	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly TokenQueryManager $tokenQueryManager,
		private readonly PreliminaryCheckService $preliminaryCheck,
		private readonly UserFactory $userFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function createPager( IContextSource $context ): PreliminaryCheckPager {
		return new PreliminaryCheckPager(
			$context,
			$this->linkRenderer,
			$this->namespaceInfo,
			$this->tokenQueryManager,
			$this->extensionRegistry,
			$this->preliminaryCheck,
			$this->userFactory
		);
	}
}

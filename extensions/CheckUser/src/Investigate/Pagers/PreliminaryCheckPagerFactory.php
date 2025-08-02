<?php

namespace MediaWiki\CheckUser\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserFactory;

class PreliminaryCheckPagerFactory implements PagerFactory {
	private LinkRenderer $linkRenderer;
	private NamespaceInfo $namespaceInfo;
	private ExtensionRegistry $extensionRegistry;
	private TokenQueryManager $tokenQueryManager;
	private PreliminaryCheckService $preliminaryCheck;
	private UserFactory $userFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		NamespaceInfo $namespaceInfo,
		ExtensionRegistry $extensionRegistry,
		TokenQueryManager $tokenQueryManager,
		PreliminaryCheckService $preliminaryCheck,
		UserFactory $userFactory
	) {
		$this->linkRenderer = $linkRenderer;
		$this->namespaceInfo = $namespaceInfo;
		$this->extensionRegistry = $extensionRegistry;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->preliminaryCheck = $preliminaryCheck;
		$this->userFactory = $userFactory;
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

<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CommunityConfiguration\EditorCapabilities\AbstractEditorCapability;
use MediaWiki\Extension\CommunityConfiguration\Provider\IConfigurationProvider;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\WANObjectCache;

class EditorCapability extends AbstractEditorCapability {

	public function __construct(
		IContextSource $ctx,
		Title $parentTitle,
		private readonly WANObjectCache $wanCache,
		private readonly LinkRenderer $linkRenderer,
		private readonly BlockedDomainValidator $blockedDomainValidator
	) {
		parent::__construct( $ctx, $parentTitle );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( ?IConfigurationProvider $provider, ?string $subpage = null ): void {
		if ( !$provider instanceof BlockedDomainConfigProvider ) {
			throw new LogicException( __CLASS__ . ' received unsupported provider' );
		}

		$this->getContext()->getOutput()->addSubtitle( '&lt; ' . $this->linkRenderer->makeLink(
			$this->getParentTitle()
		) );
		$editor = new BlockedDomainEditor(
			$this->getContext(), $this->getParentTitle()->getSubpage( $provider->getId() ),
			$this->wanCache, $this->linkRenderer,
			$provider, $this->blockedDomainValidator
		);
		$editor->execute( $subpage );
	}
}

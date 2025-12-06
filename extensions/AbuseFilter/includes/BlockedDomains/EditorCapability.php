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

	private WANObjectCache $wanCache;
	private LinkRenderer $linkRenderer;
	private BlockedDomainValidator $blockedDomainValidator;

	public function __construct(
		IContextSource $ctx,
		Title $parentTitle,
		WANObjectCache $wanCache,
		LinkRenderer $linkRenderer,
		BlockedDomainValidator $blockedDomainValidator
	) {
		parent::__construct( $ctx, $parentTitle );

		$this->wanCache = $wanCache;
		$this->linkRenderer = $linkRenderer;
		$this->blockedDomainValidator = $blockedDomainValidator;
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

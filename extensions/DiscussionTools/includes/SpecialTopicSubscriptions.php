<?php

namespace MediaWiki\Extension\DiscussionTools;

use ErrorPageError;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Linker\LinkRenderer;
use SpecialPage;

class SpecialTopicSubscriptions extends SpecialPage {

	private LinkRenderer $linkRenderer;
	private LinkBatchFactory $linkBatchFactory;

	public function __construct(
		LinkRenderer $linkRenderer,
		LinkBatchFactory $linkBatchFactory
	) {
		parent::__construct( 'TopicSubscriptions' );
		$this->linkRenderer = $linkRenderer;
		$this->linkBatchFactory = $linkBatchFactory;
	}

	/**
	 * @inheritDoc
	 * @throws ErrorPageError
	 */
	public function execute( $subpage ) {
		$this->requireLogin();

		parent::execute( $subpage );

		$this->getOutput()->addModules( [ 'ext.discussionTools.init' ] );

		$this->getOutput()->addHtml( $this->msg( 'discussiontools-topicsubscription-special-intro' )->parseAsBlock() );

		$this->getOutput()->enableOOUI();
		$pager = new TopicSubscriptionsPager(
			$this->getContext(),
			$this->linkRenderer,
			$this->linkBatchFactory
		);
		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'discussiontools-topicsubscription-special-title' )->text();
	}

}

<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialTopicSubscriptions extends SpecialPage {

	public function __construct(
		private readonly LinkRenderer $linkRenderer,
		private readonly LinkBatchFactory $linkBatchFactory,
		private readonly ThreadItemStore $threadItemStore,
		private readonly ThreadItemFormatter $threadItemFormatter,
	) {
		parent::__construct( 'TopicSubscriptions' );
	}

	/**
	 * @inheritDoc
	 * @throws ErrorPageError
	 */
	public function execute( $subpage ) {
		$this->requireNamedUser();

		parent::execute( $subpage );

		$this->getOutput()->addModules( [ 'ext.discussionTools.init' ] );

		$this->getOutput()->addHtml( $this->msg( 'discussiontools-topicsubscription-special-intro' )->parseAsBlock() );

		$this->getOutput()->enableOOUI();
		$pager = new TopicSubscriptionsPager(
			$this->getContext(),
			$this->linkRenderer,
			$this->linkBatchFactory,
			$this->threadItemStore,
			$this->threadItemFormatter
		);
		$this->getOutput()->addParserOutputContent(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'discussiontools-topicsubscription-special-title' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'login';
	}
}

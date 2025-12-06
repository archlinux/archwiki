<?php

namespace MediaWiki\Extension\Thanks;

use MediaWiki\Logging\LogEntry;
use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserRigorOptions;

/**
 * This class formats log entries for thanks
 */
class ThanksLogFormatter extends LogFormatter {
	public function __construct(
		LogEntry $entry,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly UserFactory $userFactory,
	) {
		parent::__construct( $entry );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		// Convert target from a pageLink to a userLink since the target is
		// actually a user, not a page.
		$recipient = $this->userFactory->newFromName(
			$this->entry->getTarget()->getText(),
			UserRigorOptions::RIGOR_NONE
		);
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$params[2] = Message::rawParam( $this->makeUserLink( $recipient ) );
		$params[3] = $recipient->getName();
		return $params;
	}

	/** @inheritDoc */
	public function getPreloadTitles() {
		// Add the recipient's user talk page to LinkBatch
		return [ $this->namespaceInfo->getTalkPage( $this->entry->getTarget() ) ];
	}
}

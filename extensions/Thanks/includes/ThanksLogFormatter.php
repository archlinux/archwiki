<?php

namespace MediaWiki\Extension\Thanks;

use LogEntry;
use LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\User\User;

/**
 * This class formats log entries for thanks
 */
class ThanksLogFormatter extends LogFormatter {
	private NamespaceInfo $namespaceInfo;

	public function __construct(
		LogEntry $entry,
		NamespaceInfo $namespaceInfo
	) {
		parent::__construct( $entry );
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		// Convert target from a pageLink to a userLink since the target is
		// actually a user, not a page.
		$recipient = User::newFromName( $this->entry->getTarget()->getText(), false );
		$params[2] = Message::rawParam( $this->makeUserLink( $recipient ) );
		$params[3] = $recipient->getName();
		return $params;
	}

	public function getPreloadTitles() {
		// Add the recipient's user talk page to LinkBatch
		return [ $this->namespaceInfo->getTalkPage( $this->entry->getTarget() ) ];
	}
}

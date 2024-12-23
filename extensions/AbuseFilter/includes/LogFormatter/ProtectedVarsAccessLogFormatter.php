<?php

namespace MediaWiki\Extension\AbuseFilter\LogFormatter;

use LogEntry;
use LogFormatter;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Linker\Linker;
use MediaWiki\Message\Message;
use MediaWiki\User\UserFactory;

class ProtectedVarsAccessLogFormatter extends LogFormatter {

	private UserFactory $userFactory;

	public function __construct(
		LogEntry $entry,
		UserFactory $userFactory
	) {
		parent::__construct( $entry );
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessageParameters() {
		$params = parent::getMessageParameters();

		// Replace temporary user page link with contributions page link.
		// Don't use LogFormatter::makeUserLink, because that adds tools links.
		if ( $this->entry->getSubtype() === ProtectedVarsAccessLogger::ACTION_VIEW_PROTECTED_VARIABLE_VALUE ) {
			$tempUserName = $this->entry->getTarget()->getText();
			$params[2] = Message::rawParam(
				Linker::userLink( 0, $this->userFactory->newUnsavedTempUser( $tempUserName ) )
			);
		}

		return $params;
	}
}

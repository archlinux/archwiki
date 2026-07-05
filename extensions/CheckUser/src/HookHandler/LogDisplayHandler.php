<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Logging\Hook\LogEventsListLineEndingHook;
use MediaWiki\Logging\LogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\RecentChanges\Hook\ChangesListInsertLogEntryHook;
use MediaWiki\Title\Title;
use MediaWiki\User\UserNameUtils;

class LogDisplayHandler implements LogEventsListLineEndingHook, ChangesListInsertLogEntryHook {

	public function __construct(
		private readonly UserNameUtils $userNameUtils,
		private readonly Config $config,
		private readonly CheckUserPermissionManager $checkUserPermissionManager,
	) {
	}

	/** @inheritDoc */
	public function onChangesListInsertLogEntry( $entry, $context, string &$html, array &$classes, array &$attribs ) {
		$this->handleLogDisplayHook( $entry, $context->getTitle(), $context->getAuthority(), $classes );
	}

	/** @inheritDoc */
	public function onLogEventsListLineEnding( $page, &$ret, $entry, &$classes, &$attribs ) {
		$this->handleLogDisplayHook( $entry, $page->getTitle(), $page->getAuthority(), $classes );
	}

	/**
	 * Handles hooks which are called just after the HTML line for a log entry is generated, so that
	 * we can add the CSS class to indicate which log entries support IP reveal on the performer.
	 *
	 * @param LogEntry $entry The log entry this log line is associated with
	 * @param Title|null $title The title of the page the user is viewing
	 * @param Authority $authority The user viewing the $title
	 * @param array &$classes CSS classes which may be added to by this method
	 */
	private function handleLogDisplayHook( LogEntry $entry, ?Title $title, Authority $authority, array &$classes ) {
		// Only add the "Show IP" button next to log entries performed by temporary accounts.
		if ( !$this->userNameUtils->isTemp( $entry->getPerformerIdentity()->getName() ) ) {
			return;
		}

		// If the title is not a special page that is supported, then we can't add the "Show IP" button.
		// We don't currently support non-special pages as the page output could be cached, which won't
		// work as the cache is not per-user.
		if (
			!( $title && $title->isSpecialPage() ) ||
			in_array( $title->getDBkey(), $this->config->get( 'CheckUserSpecialPagesWithoutIPRevealButtons' ) )
		) {
			return;
		}

		// No need for the "Show IP" button if the user cannot use the button.
		$permStatus = $this->checkUserPermissionManager->canAccessTemporaryAccountIPAddresses( $authority );
		if ( !$permStatus->isGood() ) {
			return;
		}

		// Indicate that the log line supports IP reveal and should have a "Show IP" button shown next to
		// the performer of the log entry (which should be the first temporary account user link).
		$classes[] = 'ext-checkuser-log-line-supports-ip-reveal';
	}
}

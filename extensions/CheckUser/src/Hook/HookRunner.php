<?php

namespace MediaWiki\Extension\CheckUser\Hook;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\User\UserIdentity;
use stdClass;

class HookRunner implements
	CheckUserFormatRowHook,
	CheckUserSubtitleLinksHook,
	CheckUserInsertChangesRowHook,
	CheckUserInsertLogEventRowHook,
	CheckUserInsertPrivateEventRowHook,
	CheckUserSuggestedInvestigationsBeforeCaseCreatedHook,
	CheckUserSuggestedInvestigationsGetSignalsHook,
	CheckUserSuggestedInvestigationsOnDetailViewRenderHook,
	CheckUserSuggestedInvestigationsSignalMatchHook,
	SpecialCheckUserGetLinksFromRowHook
{

	public function __construct(
		private readonly HookContainer $container,
	) {
	}

	/** @inheritDoc */
	public function onCheckUserFormatRow(
		IContextSource $context,
		stdClass $row,
		array &$rowItems
	) {
		$this->container->run(
			'CheckUserFormatRow',
			[ $context, $row, &$rowItems ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserSubtitleLinks(
		IContextSource $context,
		array &$links
	) {
		$this->container->run(
			'CheckUserSubtitleLinks',
			[ $context, &$links ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserInsertChangesRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user,
		?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertChangesRow',
			[ &$ip, &$xff, &$row, $user, $rc ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserInsertLogEventRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user,
		int $id,
		?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertLogEventRow',
			[ &$ip, &$xff, &$row, $user, $id, $rc ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserInsertPrivateEventRow(
		string &$ip,
		&$xff,
		array &$row,
		UserIdentity $user,
		?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertPrivateEventRow',
			[ &$ip, &$xff, &$row, $user, $rc ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserSuggestedInvestigationsBeforeCaseCreated( array $signals, array &$users ): void {
		$this->container->run(
			'CheckUserSuggestedInvestigationsBeforeCaseCreated',
			[ $signals, &$users ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserSuggestedInvestigationsGetSignals( array &$signals ): void {
		$this->container->run(
			'CheckUserSuggestedInvestigationsGetSignals',
			[ &$signals ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserSuggestedInvestigationsOnDetailViewRender( int $caseId, $output ): void {
		$this->container->run(
			'CheckUserSuggestedInvestigationsOnDetailViewRender',
			[ $caseId, $output ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserSuggestedInvestigationsSignalMatch(
		$userIdentity,
		string $eventType,
		array &$signalMatchResults,
		array $extraData
	): void {
		$this->container->run(
			'CheckUserSuggestedInvestigationsSignalMatch',
			[ $userIdentity, $eventType, &$signalMatchResults, $extraData ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onSpecialCheckUserGetLinksFromRow(
		AbstractCheckUserPager $specialCheckUser,
		stdClass $row,
		array &$links
	) {
		$this->container->run(
			'SpecialCheckUserGetLinksFromRow',
			[ $specialCheckUser, $row, &$links ]
		);
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias( HookRunner::class, 'MediaWiki\\CheckUser\\Hook\\HookRunner' );
// @codeCoverageIgnoreEnd

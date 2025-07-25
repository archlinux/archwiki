<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Context\IContextSource;
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
	SpecialCheckUserGetLinksFromRowHook
{

	private HookContainer $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
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
		string &$ip, &$xff, array &$row, UserIdentity $user, ?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertChangesRow',
			[ &$ip, &$xff, &$row, $user, $rc ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserInsertLogEventRow(
		string &$ip, &$xff, array &$row, UserIdentity $user, int $id, ?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertLogEventRow',
			[ &$ip, &$xff, &$row, $user, $id, $rc ]
		);
	}

	/** @inheritDoc */
	public function onCheckUserInsertPrivateEventRow(
		string &$ip, &$xff, array &$row, UserIdentity $user, ?RecentChange $rc
	) {
		$this->container->run(
			'CheckUserInsertPrivateEventRow',
			[ &$ip, &$xff, &$row, $user, $rc ]
		);
	}

	/** @inheritDoc */
	public function onSpecialCheckUserGetLinksFromRow(
		AbstractCheckUserPager $specialCheckUser, stdClass $row, array &$links
	) {
		$this->container->run(
			'SpecialCheckUserGetLinksFromRow',
			[ $specialCheckUser, $row, &$links ]
		);
	}
}

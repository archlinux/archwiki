<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\CheckUser\Hook\CheckUserInsertChangesRow;
use MediaWiki\CheckUser\Hook\CheckUserInsertLogEventRow;
use MediaWiki\CheckUser\Hook\CheckUserInsertPrivateEventRow;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\User\UserIdentity;
use RecentChange;

class CheckUserHandler implements
	CheckUserInsertChangesRow,
	CheckUserInsertPrivateEventRow,
	CheckUserInsertLogEventRow
{

	/** @var FilterUser */
	private $filterUser;

	/**
	 * @param FilterUser $filterUser
	 */
	public function __construct( FilterUser $filterUser ) {
		$this->filterUser = $filterUser;
	}

	/**
	 * Any edits by the filter user should always be marked as by the software
	 * using IP 127.0.0.1, no XFF and no UA.
	 *
	 * @inheritDoc
	 */
	public function onCheckUserInsertChangesRow(
		string &$ip, &$xff, array &$row, UserIdentity $user, ?RecentChange $rc
	) {
		if (
			$user->isRegistered() &&
			$this->filterUser->getUserIdentity()->getId() == $user->getId()
		) {
			$ip = '127.0.0.1';
			$xff = false;
			$row['cuc_agent'] = '';
		}
	}

	/**
	 * Any log actions by the filter user should always be marked as by the software
	 * using IP 127.0.0.1, no XFF and no UA.
	 *
	 * @inheritDoc
	 */
	public function onCheckUserInsertLogEventRow(
		string &$ip, &$xff, array &$row, UserIdentity $user, int $id, ?RecentChange $rc
	) {
		if (
			$user->isRegistered() &&
			$this->filterUser->getUserIdentity()->getId() == $user->getId()
		) {
			$ip = '127.0.0.1';
			$xff = false;
			$row['cule_agent'] = '';
		}
	}

	/**
	 * Any log actions by the filter user should always be marked as by the software
	 * using IP 127.0.0.1, no XFF and no UA.
	 *
	 * @inheritDoc
	 */
	public function onCheckUserInsertPrivateEventRow(
		string &$ip, &$xff, array &$row, UserIdentity $user, ?RecentChange $rc
	) {
		if (
			$user->isRegistered() &&
			$this->filterUser->getUserIdentity()->getId() == $user->getId()
		) {
			$ip = '127.0.0.1';
			$xff = false;
			$row['cupe_agent'] = '';
		}
	}
}

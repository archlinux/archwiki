<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Enforce2FA;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserRequirementsConditionEvaluatorBase;

class UserRequirementsConditionEvaluatorWith2FAAssumption extends UserRequirementsConditionEvaluatorBase {

	/**
	 * @var bool|null Assumed 2FA state for the user. If null, uses the actual 2FA state. If true/false, treats
	 * the user as having 2FA enabled/disabled respectively.
	 */
	private ?bool $assumed2FAState = null;

	/** @inheritDoc */
	public function checkCondition(
		string|int $conditionType,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest
	): ?bool {
		if ( $conditionType === APCOND_OATH_HAS2FA ) {
			return $this->assumed2FAState;
		}
		return null;
	}

	/**
	 * Set an assumed 2FA state for the user. If set, this will be used instead of the actual 2FA state when
	 * checking conditions.
	 *
	 * @param bool|null $state Assumed 2FA state, or null to use the actual 2FA state
	 */
	public function setAssumed2FAState( ?bool $state ): void {
		$this->assumed2FAState = $state;
	}
}

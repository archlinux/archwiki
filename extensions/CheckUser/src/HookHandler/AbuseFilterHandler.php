<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCanViewProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterCustomProtectedVariablesHook;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterProtectedVarsAccessLoggerHook;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;

class AbuseFilterHandler implements
	AbuseFilterCustomProtectedVariablesHook,
	AbuseFilterProtectedVarsAccessLoggerHook,
	AbuseFilterCanViewProtectedVariablesHook
	{

	private TemporaryAccountLoggerFactory $loggerFactory;
	private CheckUserPermissionManager $checkUserPermissionManager;
	private TempUserConfig $tempUserConfig;

	public function __construct(
		TemporaryAccountLoggerFactory $loggerFactory,
		CheckUserPermissionManager $checkUserPermissionManager,
		TempUserConfig $tempUserConfig
	) {
		$this->loggerFactory = $loggerFactory;
		$this->checkUserPermissionManager = $checkUserPermissionManager;
		$this->tempUserConfig = $tempUserConfig;
	}

	/**
	 * Because CheckUser wants to define additional restrictions on accessing the
	 * user_unnamed_ip variable, we should ensure that the variable is always
	 * protected to allow these restrictions to take effect.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCustomProtectedVariables( array &$variables ) {
		$variables[] = 'user_unnamed_ip';
	}

	/**
	 * Whenever AbuseFilter logs access to the user_unnamed_ip protected variable, this should instead
	 * be logged to CheckUser in order to centralize IP view logs. Abort the hook
	 * afterwards so that the event is not double-logged.
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterLogProtectedVariableValueAccess(
		UserIdentity $performer,
		string $target,
		string $action,
		bool $shouldDebounce,
		int $timestamp,
		array &$params
	) {
		if ( !isset( $params['variables'] ) ) {
			return true;
		}

		$handledVariables = [ 'user_unnamed_ip' ];
		$variablesOfInterest = array_intersect( $handledVariables, $params['variables'] );

		if ( count( $variablesOfInterest ) === 0 ) {
			// No variables of interest, continue with the default logging
			return true;
		}

		// Use the AbuseFilter specific log action as a message key along
		// with an indicator that it's being sourced from AbuseFilter so that
		// it's clearer that this is an external log.
		// Possible values:
		//  - af-view-protected-var-value
		$action = 'af-' . $action;

		// Copy the parameters but with only the subset of variables that we're interested in
		$newParams = $params;
		$newParams['variables'] = $variablesOfInterest;

		// Keep only the unhandled variables in the log entry handled by AbuseFilter
		$params['variables'] = array_diff( $params['variables'], $handledVariables );

		$logger = $this->loggerFactory->getLogger();
		$logger->logFromExternal(
			$performer,
			$target,
			$action,
			$newParams,
			$shouldDebounce,
			$timestamp
		);

		// Allow AbuseFilter to continue logging if there are remaining variables
		return true;
	}

	/**
	 * Restrict access to seeing filters or logs associated with filters which use the AbuseFilter user_unnamed_ip
	 * variable to those who have the ability to see Temporary Account IP addresses (if the wiki has temporary accounts
	 * known or enabled).
	 *
	 * @inheritDoc
	 */
	public function onAbuseFilterCanViewProtectedVariables(
		Authority $performer, array $variables, AbuseFilterPermissionStatus $status
	): void {
		if ( !in_array( 'user_unnamed_ip', $variables ) || !$this->tempUserConfig->isKnown() ) {
			return;
		}

		$checkUserPermissionStatus = $this->checkUserPermissionManager
			->canAccessTemporaryAccountIPAddresses( $performer );

		$permission = $checkUserPermissionStatus->getPermission();
		if ( $permission ) {
			$status->setPermission( $permission );
		}

		$block = $checkUserPermissionStatus->getBlock();
		if ( $block ) {
			$status->setBlock( $block );
		}

		$status->merge( $checkUserPermissionStatus );
	}
}

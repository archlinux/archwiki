<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Permissions\Authority;

interface AbuseFilterCanViewProtectedVariablesHook {
	/**
	 * Called when determining if the user can view the specified protected variables.
	 *
	 * Implement this hook to define additional restrictions viewing any number of protected
	 * variable(s). This is also called when viewing the value of these variables.
	 *
	 * @since 1.44
	 * @param Authority $performer The user viewing the protected variable values.
	 * @param string[] $variables The protected variables that are being viewed.
	 * @param AbuseFilterPermissionStatus $status Modify this status to make it fatal if user does
	 *   not meet the additional restrictions. You can call {@link AbuseFilterPermissionStatus::setBlock}
	 *   and {@link AbuseFilterPermissionStatus::setPermission} where relevant.
	 */
	public function onAbuseFilterCanViewProtectedVariables(
		Authority $performer, array $variables, AbuseFilterPermissionStatus $status
	): void;
}

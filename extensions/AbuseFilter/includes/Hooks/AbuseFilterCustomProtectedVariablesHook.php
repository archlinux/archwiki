<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks;

interface AbuseFilterCustomProtectedVariablesHook {
	/**
	 * Allows other extensions to define variables as protected variables. This has the effect of
	 * adding the variable to the $wgAbuseFilterProtectedVariables configuration value. A protected
	 * variable can only be used in a protected filter, which is not publicly accessible.
	 *
	 * Using this hook has the advantage of enforcing that the variable is always protected, even if
	 * it is removed from $wgAbuseFilterProtectedVariables.
	 *
	 * @since 1.44
	 * @param string[] &$variables The variables which should be protected variables.
	 */
	public function onAbuseFilterCustomProtectedVariables( array &$variables );
}

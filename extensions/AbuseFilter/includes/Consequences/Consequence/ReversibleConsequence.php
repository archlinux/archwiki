<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Permissions\Authority;

/**
 * Interface for consequences which can be reverted
 */
interface ReversibleConsequence {

	/**
	 * @param Authority $performer
	 * @param string $reason
	 * @return bool Whether the revert was successful
	 */
	public function revert( Authority $performer, string $reason ): bool;

}

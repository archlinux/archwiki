<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\User\UserIdentity;

/**
 * Interface for consequences which can be reverted
 */
interface ReversibleConsequence {

	/**
	 * @param array $info
	 * @param UserIdentity $performer
	 * @param string $reason
	 * @return bool Whether the revert was successful
	 * @todo define or narrow $info
	 */
	public function revert( $info, UserIdentity $performer, string $reason ): bool;

}

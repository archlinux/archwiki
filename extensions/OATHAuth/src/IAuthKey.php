<?php

namespace MediaWiki\Extension\OATHAuth;

use JsonSerializable;
use stdClass;

interface IAuthKey extends JsonSerializable {

	/**
	 * @param array|stdClass $data
	 * @param OATHUser $user
	 * @return bool
	 */
	public function verify( $data, OATHUser $user );

}

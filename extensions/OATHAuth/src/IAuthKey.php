<?php

namespace MediaWiki\Extension\OATHAuth;

use stdClass;
use JsonSerializable;

interface IAuthKey extends JsonSerializable {

	/**
	 * @param array|stdClass $data
	 * @param OATHUser $user
	 * @return mixed
	 */
	public function verify( $data, OATHUser $user );

}

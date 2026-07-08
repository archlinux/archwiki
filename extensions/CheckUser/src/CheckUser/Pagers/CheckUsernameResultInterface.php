<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\CheckUser\Pagers;

/**
 * @since 1.46
 */
interface CheckUsernameResultInterface {
	/**
	 * Returns a [ userId => userName ] map for registered (non-IP) users from the pager results
	 *
	 * @return array<int, string>
	 */
	public function getResultUsernameMap(): array;
}

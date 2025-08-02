<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\Context\IContextSource;

interface CheckUserSubtitleLinksHook {
	/**
	 * Use this hook to modify the subtitle links on Special:Investigate.
	 *
	 * @since 1.36
	 *
	 * @param IContextSource $context
	 * @param string[] &$links
	 */
	public function onCheckUserSubtitleLinks(
		IContextSource $context,
		array &$links
	);
}

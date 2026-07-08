<?php

namespace MediaWiki\Extension\CheckUser\Hook;

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

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CheckUserSubtitleLinksHook::class,
	'MediaWiki\\CheckUser\\Hook\\CheckUserSubtitleLinksHook'
);
// @codeCoverageIgnoreEnd

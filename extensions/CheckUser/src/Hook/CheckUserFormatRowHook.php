<?php

namespace MediaWiki\Extension\CheckUser\Hook;

use MediaWiki\Context\IContextSource;
use stdClass;

interface CheckUserFormatRowHook {
	/**
	 * Use this hook to modify a row in the Timeline pager for Special:Investigate.
	 *
	 * @since 1.35
	 *
	 * @param IContextSource $context
	 * @param stdClass $row
	 * @param string[][] &$rowItems
	 */
	public function onCheckUserFormatRow(
		IContextSource $context,
		stdClass $row,
		array &$rowItems
	);
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CheckUserFormatRowHook::class,
	'MediaWiki\\CheckUser\\Hook\\CheckUserFormatRowHook'
);
// @codeCoverageIgnoreEnd

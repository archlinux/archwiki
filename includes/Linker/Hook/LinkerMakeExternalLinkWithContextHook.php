<?php

namespace MediaWiki\Linker\Hook;

use Wikimedia\Parsoid\Core\LinkTarget;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "LinkerMakeExternalLinkWithContext" to register handlers
 * implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface LinkerMakeExternalLinkWithContextHook {
	/**
	 * This hook is called at the end of Linker::makeExternalLink() just
	 * before the return.  It is a more modern version of
	 * LinkerMakeExternalLink, which provides a context title and
	 * reflects modern practice in not allowing direct mutation of the
	 * link HTML.
	 *
	 * Parsoid allows rewriting or blocking (setting to null) the $url,
	 * and adding class, title, and rel attributes.  Setting $url to
	 * null will block the link: no href attribute will be present on
	 * the <a> tag.
	 *
	 * @since 1.46
	 *
	 * @param ?string &$url Link URL
	 * @param string &$text Link text
	 * @param string[] &$attribs Attributes to be applied
	 * @param string $linkType External link type
	 * @param LinkTarget $contextTitle
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onLinkerMakeExternalLinkWithContext(
		?string &$url, string &$text, array &$attribs, string $linkType,
		LinkTarget $contextTitle
	);
}

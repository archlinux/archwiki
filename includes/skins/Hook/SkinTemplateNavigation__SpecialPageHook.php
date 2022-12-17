<?php

namespace MediaWiki\Hook;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
use SkinTemplate;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "SkinTemplateNavigation::SpecialPage" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 * @deprecated since 1.39 Use SkinTemplateNavigation__Universal instead
 */
interface SkinTemplateNavigation__SpecialPageHook {
	/**
	 * This hook is called on special pages after the special
	 * tab is added but before variants have been added.
	 *
	 * @since 1.35
	 *
	 * @param SkinTemplate $sktemplate
	 * @param array &$links Structured navigation links. This is used to alter the navigation for
	 *   skins which use buildNavigationUrls such as Vector.
	 * @return void This hook must not abort, it must return no value
	 *
	 * @deprecated since 1.39 Use SkinTemplateNavigation__Universal::onSkinTemplateNavigation__Universal instead
	 */
	public function onSkinTemplateNavigation__SpecialPage( $sktemplate, &$links ): void;
}

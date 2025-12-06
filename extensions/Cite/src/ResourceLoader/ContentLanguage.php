<?php

namespace Cite\ResourceLoader;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

/**
 * Callback to deliver language data for the content language, if different than the interface language.
 *
 * @copyright 2024 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */
class ContentLanguage {

	public static function getJsData( RL\Context $context ): array {
		$contentLang = MediaWikiServices::getInstance()->getContentLanguage();
		$contentLangCode = $contentLang->getCode();
		$interfaceLangCode = $context->getLanguage();

		if ( $contentLangCode === $interfaceLangCode ) {
			return [];
		}

		return [ $contentLangCode => $contentLang->getJsData() ];
	}

}

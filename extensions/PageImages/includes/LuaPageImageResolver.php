<?php

namespace PageImages;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\TitleAttributeResolver;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;

class LuaPageImageResolver extends TitleAttributeResolver {

	public function __construct(
		private readonly PageImages $pageImages,
	) {
	}

	/**
	 * @param LinkTarget $target
	 * @return string|null
	 */
	public function resolve( LinkTarget $target ) {
		$title = Title::newFromLinkTarget( $target );
		if ( !$title->canExist() ) {
			return null;
		}

		$this->incrementExpensiveFunctionCount();
		if ( $title->equals( $this->getEngine()->getTitle() ) ) {
			$parserOutput = $this->getEngine()->getParser()->getOutput();
			$parserOutput->setOutputFlag( ParserOutputFlags::VARY_REVISION );
		}

		$file = $this->pageImages->getImage( $title );
		if ( !$file ) {
			return null;
		}
		return $file->getTitle()->getPrefixedText();
	}
}

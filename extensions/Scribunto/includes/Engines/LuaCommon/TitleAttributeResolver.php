<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Parser\ParserOutputFlags;
use MediaWiki\Title\Title;

/**
 * Base class for extensions to define custom attributes on mw.title objects.
 *
 * @stable to extend
 * @since 1.45
 */
abstract class TitleAttributeResolver {

	private ?LuaEngine $engine;

	/**
	 * @param LinkTarget $target
	 * @stable to override
	 */
	abstract public function resolve( LinkTarget $target );

	/**
	 * @internal
	 * @param LuaEngine $engine
	 */
	public function setEngine( LuaEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * @return LuaEngine
	 */
	protected function getEngine(): LuaEngine {
		return $this->engine;
	}

	protected function addTemplateLink( Title $title ): void {
		$parserOutput = $this->getEngine()->getParser()->getOutput();
		if ( $title->equals( $this->getEngine()->getTitle() ) ) {
			$parserOutput->setOutputFlag( ParserOutputFlags::VARY_REVISION );
		} else {
			// Record in templatelinks, so edits cause the page to be refreshed
			$parserOutput->addTemplate( $title, $title->getArticleID(), $title->getLatestRevID() );
		}
	}

	protected function incrementExpensiveFunctionCount(): void {
		$this->getEngine()->incrementExpensiveFunctionCount();
	}
}

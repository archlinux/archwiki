<?php
namespace MediaWiki\Content\Transform;

use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOptions;

/**
 * @internal
 * An object to hold preload transform params.
 */
class PreloadTransformParamsValue implements PreloadTransformParams {
	public function __construct(
		private readonly PageReference $page,
		private readonly ParserOptions $parserOptions,
		private readonly array $params = [],
	) {
	}

	public function getPage(): PageReference {
		return $this->page;
	}

	public function getParams(): array {
		return $this->params;
	}

	public function getParserOptions(): ParserOptions {
		return $this->parserOptions;
	}
}

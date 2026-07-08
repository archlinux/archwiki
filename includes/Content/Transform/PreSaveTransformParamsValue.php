<?php
namespace MediaWiki\Content\Transform;

use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\User\UserIdentity;

/**
 * @internal
 * An object to hold pre-save transform params.
 */
class PreSaveTransformParamsValue implements PreSaveTransformParams {
	public function __construct(
		private readonly PageReference $page,
		private readonly UserIdentity $user,
		private readonly ParserOptions $parserOptions,
	) {
	}

	public function getPage(): PageReference {
		return $this->page;
	}

	public function getUser(): UserIdentity {
		return $this->user;
	}

	public function getParserOptions(): ParserOptions {
		return $this->parserOptions;
	}
}

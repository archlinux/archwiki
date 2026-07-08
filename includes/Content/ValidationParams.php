<?php
namespace MediaWiki\Content;

use MediaWiki\Page\PageIdentity;

/**
 * @since 1.38
 * An object to hold validation params.
 */
class ValidationParams {
	public function __construct(
		private readonly PageIdentity $pageIdentity,
		private readonly int $flags,
		private readonly int $parentRevId = -1,
	) {
	}

	public function getPageIdentity(): PageIdentity {
		return $this->pageIdentity;
	}

	public function getFlags(): int {
		return $this->flags;
	}

	/**
	 * @deprecated since 1.38. Born soft-deprecated as we will move usage of it
	 * to MultiContentSaveHook in ProofreadPage (only one place of usage).
	 */
	public function getParentRevisionId(): int {
		return $this->parentRevId;
	}
}

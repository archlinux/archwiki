<?php
namespace MediaWiki\Skins\Vector\Components;

/**
 * VectorComponentPinnableContainer component
 * To be used with PinnableContainer/Pinned or PinnableContainer/Unpinned templates.
 */
class VectorComponentPinnableContainer implements VectorComponent {
	public function __construct(
		private readonly string $id,
		private readonly bool $isPinned = true,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		return [
			'id' => $this->id,
			'is-pinned' => $this->isPinned,
		];
	}
}

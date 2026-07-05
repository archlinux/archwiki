<?php
namespace MediaWiki\Skins\Vector\Components;

/**
 * VectorComponentPinnableElement component
 */
class VectorComponentPinnableElement implements VectorComponent {
	public function __construct(
		private readonly string $id,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		return [
			'id' => $this->id,
		];
	}
}

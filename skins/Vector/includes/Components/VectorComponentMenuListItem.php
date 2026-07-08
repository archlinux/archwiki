<?php
namespace MediaWiki\Skins\Vector\Components;

/**
 * VectorComponentMenuListItem component
 */
class VectorComponentMenuListItem implements VectorComponent {
	public function __construct(
		private readonly VectorComponentLink $link,
		private readonly string $class = '',
		private readonly string $id = '',
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		return $this->link->getTemplateData() + [
			'item-class' => $this->class,
			'item-id' => $this->id,
		];
	}
}

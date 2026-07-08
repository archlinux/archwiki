<?php

namespace MediaWiki\Extension\DiscussionTools;

use JsonSerializable;
use MediaWiki\Language\MessageLocalizer;
use Wikimedia\Message\MessageSpecifier;

/**
 * Item to go into the DiscussionTools overflow menu, as an OO.ui.MenuOptionWidget object.
 *
 * You must call parseLabel() after constructing this object and before serializing it.
 */
class OverflowMenuItem implements JsonSerializable {

	/**
	 * @var string A rendered string to use as the label for the item.
	 */
	private string $label;

	/**
	 * @param string $id A unique identifier for the menu item, e.g. 'edit' or 'reportincident'
	 * @param string $icon An OOUI icon name.
	 * @param MessageSpecifier|string $labelMsg Message or message key to use as the label for the item.
	 *   If the message does not need params, pass a string, which will avoid parsing the message repeatedly.
	 * @param int $weight Sorting weight. Higher values will push the item further up the menu.
	 * @param array $data Data to include with the menu item. Will be accessible via getData() on the
	 *   OOUI MenuOptionWidget in client-side code.
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $icon,
		private readonly MessageSpecifier|string $labelMsg,
		private readonly int $weight = 0,
		private readonly array $data = [],
	) {
	}

	public function parseLabel( MessageLocalizer $contextSource, array &$msgCache ): void {
		$labelMsg = $this->labelMsg;
		if ( is_string( $labelMsg ) ) {
			if ( !isset( $msgCache[$labelMsg] ) ) {
				$msgCache[$labelMsg] = $contextSource->msg( $labelMsg )->text();
			}
			$this->label = $msgCache[$labelMsg];
		} else {
			$this->label = $contextSource->msg( $labelMsg )->text();
		}
	}

	public function jsonSerialize(): array {
		$data = $this->data;
		// Add 'id' into the 'data' array, for easier access with OOUI's getData() method
		$data['id'] = $this->id;
		return [
			'id' => $this->id,
			'data' => $data,
			'icon' => $this->icon,
			'label' => $this->label,
		];
	}

	public function getId(): string {
		return $this->id;
	}

	public function getWeight(): int {
		return $this->weight;
	}
}

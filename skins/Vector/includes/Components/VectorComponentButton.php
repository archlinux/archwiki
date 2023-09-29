<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Skins\Vector\Hooks;

/**
 * VectorSearchBox component
 */
class VectorComponentButton implements VectorComponent {
	/** @var string */
	private $label;
	/** @var string|null */
	private $id;
	/** @var string|null */
	private $href;
	/** @var string|null */
	private $icon;
	/** @var string|null */
	private $event;

	/**
	 * @param string $label
	 * @param string|null $id
	 * @param string|null $href
	 * @param string|null $icon
	 * @param string|null $event
	 */
	public function __construct(
		string $label,
		$id = null,
		$href = null,
		$icon = null,
		$event = null
	) {
		$this->id = $id;
		$this->href = $href;
		$this->label = $label;
		$this->icon = $icon;
		$this->event = $event;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		return [
			'id' => $this->id,
			'href' => $this->href,
			'html-vector-button-icon' => Hooks::makeIcon( $this->icon ),
			'label' => $this->label,
			'is-quiet' => true,
			'class' => 'mw-ui-primary mw-ui-progressive',
			'event' => $this->event,
		];
	}
}

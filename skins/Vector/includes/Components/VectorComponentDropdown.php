<?php
namespace MediaWiki\Skins\Vector\Components;

/**
 * VectorComponentDropdown component
 */
class VectorComponentDropdown implements VectorComponent {
	public function __construct(
		private readonly string $id,
		private readonly string $label,
		private readonly string $class = '',
		private readonly ?string $icon = null,
		private readonly string $tooltip = '',
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		// FIXME: Stop hardcoding button and icon styles, this assumes all dropdowns with icons are icon buttons
		// Not the case for the language dropdown, page tools, etc
		$icon = $this->icon;
		$buttonClass = 'cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--weight-quiet';
		$iconButtonClass = $icon ? ' cdx-button--icon-only ' : '';

		return [
			'id' => $this->id,
			'label' => $this->label,
			'label-class' => $buttonClass . $iconButtonClass,
			'icon' => $this->icon,
			'html-vector-menu-label-attributes' => '',
			'html-vector-menu-checkbox-attributes' => '',
			'class' => $this->class,
			'html-tooltip' => $this->tooltip,
			'checkbox-class' => '',
		];
	}
}

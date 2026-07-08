<?php
namespace MediaWiki\Skins\Vector\Components;

/**
 * VectorSearchBox component
 */
class VectorComponentButton implements VectorComponent {
	public function __construct(
		private readonly string $label,
		private readonly ?string $icon = null,
		private readonly ?string $id = null,
		private readonly ?string $class = null,
		private readonly ?array $attributes = [],
		private ?string $weight = 'normal',
		private ?string $action = 'default',
		private readonly ?bool $iconOnly = false,
		private readonly ?string $href = null,
	) {
		// Weight can only be normal, primary, or quiet
		if ( $this->weight !== 'primary' && $this->weight !== 'quiet' ) {
			$this->weight = 'normal';
		}
		// Action can only be default, progressive or destructive
		if ( $this->action !== 'progressive' && $this->action !== 'destructive' ) {
			$this->action = 'default';
		}
	}

	/**
	 * Constructs button classes based on the props
	 */
	private function getClasses(): string {
		$classes = 'cdx-button';
		if ( $this->href ) {
			$classes .= ' cdx-button--fake-button cdx-button--fake-button--enabled';
		}
		switch ( $this->weight ) {
			case 'primary':
				$classes .= ' cdx-button--weight-primary';
				break;
			case 'quiet':
				$classes .= ' cdx-button--weight-quiet';
				break;
		}
		switch ( $this->action ) {
			case 'progressive':
				$classes .= ' cdx-button--action-progressive';
				break;
			case 'destructive':
				$classes .= ' cdx-button--action-destructive';
				break;
		}
		if ( $this->iconOnly ) {
			$classes .= ' cdx-button--icon-only';
		}
		if ( $this->class ) {
			$classes .= ' ' . $this->class;
		}
		return $classes;
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$arrayAttributes = [];
		foreach ( $this->attributes as $key => $value ) {
			if ( $value === null ) {
				continue;
			}
			$arrayAttributes[] = [ 'key' => $key, 'value' => $value ];
		}
		return [
			'label' => $this->label,
			'icon' => $this->icon,
			'id' => $this->id,
			'class' => $this->getClasses(),
			'href' => $this->href,
			'array-attributes' => $arrayAttributes
		];
	}
}

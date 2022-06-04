<?php

namespace MediaWiki\Settings\Source;

/**
 * Settings loaded from an array.
 *
 * @since 1.38
 */
class ArraySource implements SettingsSource {
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function load(): array {
		return $this->settings;
	}

	public function __toString(): string {
		return '<array>';
	}
}

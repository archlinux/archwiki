<?php

namespace Wikimedia\Parsoid\NodeData;

/**
 * data-mw-i18n information, used for internationalization. This data is used to represent the
 * information necessary for later localization of messages (in spans) or element attributes values.
 */
class DataI18n implements \JsonSerializable {

	/** @var array */
	private $i18nInfo = [];

	/**
	 * Get the I18nInfo associated to a span (which will be used to fill in the span content) or
	 * null, if non-existent.
	 * @return I18nInfo|null
	 */
	public function getSpanInfo(): ?I18nInfo {
		return $this->i18nInfo['/'] ?? null;
	}

	/**
	 * Get the I18nInfo that will be used to localize an element attribute value with the name
	 * $name or null, if non-existent.
	 * @param string $name
	 * @return I18nInfo|null
	 */
	public function getAttributeInfo( string $name ): ?I18nInfo {
		return $this->i18nInfo[$name] ?? null;
	}

	/**
	 * Defines the internationalization parameters of a string contained in a span.
	 * @param I18nInfo $info
	 * @return void
	 */
	public function setSpanInfo( I18nInfo $info ) {
		$this->i18nInfo['/'] = $info;
	}

	/**
	 * Defines the internationalization parameters of the $name attribute's value.
	 * @param string $name
	 * @param I18nInfo $info
	 * @return void
	 */
	public function setAttributeInfo( string $name, I18nInfo $info ) {
		$this->i18nInfo[$name] = $info;
	}

	public function jsonSerialize() {
		return $this->i18nInfo;
	}

	/**
	 * @param array $json
	 * @return DataI18n
	 */
	public static function fromJson( array $json ): DataI18n {
		$i18n = new DataI18n();
		foreach ( $json as $k => $v ) {
			$i18n->i18nInfo[$k] = new I18nInfo( $v['lang'], $v['key'], $v['params'] );
		}
		return $i18n;
	}
}

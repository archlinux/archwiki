<?php

namespace Wikimedia\RemexHtml\Serializer;

use Wikimedia\RemexHtml\HTMLData;

/**
 * A formatter which produces a serialization extremely similar to the
 * Html5Depurate service, which uses the validator.nu library for tree
 * construction.
 *
 * For use in comparative testing.
 *
 * https://www.mediawiki.org/wiki/Html5Depurate
 */
class DepurateFormatter extends HtmlFormatter {
	/** @inheritDoc */
	public function __construct( $options = [] ) {
		parent::__construct( $options );
		$this->textEscapes["\xc2\xa0"] = '&#160;';
	}

	/** @inheritDoc */
	public function element( SerializerNode $parent, SerializerNode $node, $contents ) {
		$name = $node->name;
		$s = "<$name";
		foreach ( $node->attrs->getValues() as $attrName => $attrValue ) {
			$encValue = strtr( $attrValue, $this->attributeEscapes );
			$s .= " $attrName=\"$encValue\"";
		}
		if ( $node->namespace === HTMLData::NS_HTML ) {
			if ( isset( HTMLData::TAGS['prefixLF'][$name] ) ) {
				$s .= ">\n$contents</$name>";
			} elseif ( !isset( HTMLData::TAGS['void'][$name] ) ) {
				$s .= ">$contents</$name>";
			} else {
				$s .= " />";
			}
		} else {
			$s .= ">$contents</$name>";
		}
		return $s;
	}
}

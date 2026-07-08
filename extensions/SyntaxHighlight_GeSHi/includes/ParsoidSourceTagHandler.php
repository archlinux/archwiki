<?php

namespace MediaWiki\SyntaxHighlight;

use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class ParsoidSourceTagHandler extends ExtensionTagHandler {
	public function __construct( private readonly SyntaxHighlight $syntaxHighlight ) {
	}

	/** @inheritDoc */
	public function sourceToDom( ParsoidExtensionAPI $extApi, string $text, array $extArgs ): ?DocumentFragment {
		$extApi->addTrackingCategory( 'syntaxhighlight-source-category' );
		return $this->syntaxHighlight->handleParsoidTag( $extApi, $text, $extArgs );
	}
}

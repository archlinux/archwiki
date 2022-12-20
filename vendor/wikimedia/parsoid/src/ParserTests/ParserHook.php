<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use Error;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * See tests/parser/ParserTestParserHook.php in core.
 */
class ParserHook extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DocumentFragment {
		$extName = $extApi->extTag->getName();
		if ( $extApi->extTag->isSelfClosed() ) {
			$content = null;
		}
		switch ( $extName ) {
			case 'tag':
			case 'tåg':
				return $extApi->htmlToDom(
					"<pre>\n" .
						var_export( $content, true ) . "\n" .
						var_export( $extApi->extArgsToArray( $args ), true ) . "\n" .
					"</pre>"
				);

			case 'statictag':
				// FIXME: Choose a better DOM representation that doesn't mess with
				// newline constraints.
				return $extApi->htmlToDom( '<span />' );

			case 'asidetag':
				// T278565
				return $extApi->htmlToDom( '<aside>Some aside content</aside>' );

			case 'pwraptest':
				return $extApi->htmlToDom( '<!--CMT--><style>p{}</style>' );

			default:
				throw new Error( "Unexpected tag name: $extName in ParserHook" );
		}
	}

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'ParserHook',
			'tags' => [
				[ 'name' => 'tag', 'handler' => self::class ],
				[ 'name' => 'tåg', 'handler' => self::class ],
				[ 'name' => 'statictag', 'handler' => self::class ],
				[ 'name' => 'asidetag', 'handler' => self::class ],
				[ 'name' => 'pwraptest', 'handler' => self::class ],
			],
			'domProcessors' => [
				ParserHookProcessor::class
			]
		];
	}
}

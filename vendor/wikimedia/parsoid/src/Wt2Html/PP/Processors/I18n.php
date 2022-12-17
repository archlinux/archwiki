<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class I18n implements Wt2HtmlDOMProcessor {

	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, Node $root, array $options = [], bool $atTopLevel = false
	): void {
		'@phan-var Element|DocumentFragment $root';  // @var Element|DocumentFragment $root
		$spans = DOMCompat::querySelectorAll( $root, 'span[typeof~="mw:I18n"]' );
		foreach ( $spans as $span ) {
			DOMUtils::removeTypeOf( $span, 'mw:I18n' );
			$i18n = DOMDataUtils::getDataNodeI18n( $span );
			$msg = "Error: {$i18n->key}";
			// $msg = wfMessage( $i18n['key'], ...( $i18n['params'] ?? [] ) )->text();
			$span->appendChild(
				$span->ownerDocument->createTextNode( $msg )
			);
		}
	}

}

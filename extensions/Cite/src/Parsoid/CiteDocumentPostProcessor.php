<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use MediaWiki\Config\Config;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * Wikitext → HTML DOM PostProcessor
 * @license GPL-2.0-or-later
 */
class CiteDocumentPostProcessor extends DOMProcessor {
	private Config $mainConfig;

	public function __construct( Config $mainConfig ) {
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi, Node $node, array $options
	): void {
		$refsData = new ReferencesData();
		$references = new References( $this->mainConfig );
		$references->processRefs( $extApi, $refsData, $node );
		$this->insertMissingReferencesIntoDOM( $extApi, $refsData, $node );
		( new ErrorUtils( $extApi ) )->addEmbeddedErrors( $refsData, $node );
	}

	/**
	 * Process `<ref>`s left behind after the DOM is fully processed.
	 * We process them as if there was an implicit `<references />` tag at
	 * the end of the DOM.
	 */
	public function insertMissingReferencesIntoDOM(
		ParsoidExtensionAPI $extApi, ReferencesData $referencesData, Node $node
	): void {
		$references = new References( $this->mainConfig );

		$doc = $node->ownerDocument;
		foreach ( $referencesData->getRefGroups() as $groupName => $refsGroup ) {
			$domFragment = $doc->createDocumentFragment();
			$refFragment = $references->createEmptyReferenceListFragment(
				$extApi,
				$domFragment,
				[
					// Force string cast here since in the foreach above, $groupName
					// is an array key. In that context, number-like strings are
					// silently converted to a numeric value!
					// Ex: In <ref group="2" />, the "2" becomes 2 in the foreach
					'group' => (string)$groupName,
					'responsive' => null,
				],
				static function ( $dp ) use ( $extApi ) {
					// The new references come out of "nowhere", so to make selser work
					// properly, add a zero-sized DSR pointing to the end of the document.
					$content = $extApi->getPageConfig()->getRevisionContent()->getContent( 'main' );
					$contentLength = strlen( $content );
					$dp->dsr = new DomSourceRange( $contentLength, $contentLength, 0, 0 );
				},
				true
			);

			// Add a \n before the <ol> so that when serialized to wikitext,
			// each <references /> tag appears on its own line.
			$node->appendChild( $doc->createTextNode( "\n" ) );
			$node->appendChild( $refFragment );

			$references->insertReferencesIntoDOM( $extApi, $refFragment, $referencesData, true );
		}
	}

	/**
	 * HTML → Wikitext DOM PreProcessor
	 *
	 * Nothing to do right now.
	 *
	 * But, for example, as part of some future functionality, this could be used to
	 * reconstitute page-level information from local annotations left behind by editing clients.
	 */
	public function htmlPreprocess( ParsoidExtensionAPI $extApi, Element $root ): void {
	}
}

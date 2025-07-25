<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use Wikimedia\Parsoid\Core\Sanitizer;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * Helper class used by `<references>` implementation.
 * @license GPL-2.0-or-later
 */
class RefGroup {

	public string $name;
	/** @var RefGroupItem[] */
	public array $refs = [];
	/** @var array<string,RefGroupItem> Lookup map only for named refs */
	public array $indexByName = [];
	/** @var int Counter to track order of ref appearance in article */
	private int $nextIndex = 1;
	/** @var array<string,int> Counter to provide subreference indexes */
	private array $subRefCountByName = [];

	public function __construct( string $group = '' ) {
		$this->name = $group;
	}

	public function lookupRefByName( string $name ): ?RefGroupItem {
		return $this->indexByName[$name] ?? null;
	}

	/**
	 * Generate leading linkbacks
	 */
	private static function createLinkback(
		ParsoidExtensionAPI $extApi, string $target, ?string $group,
		string $text, Document $ownerDoc
	): Element {
		$a = $ownerDoc->createElement( 'a' );
		$span = $ownerDoc->createElement( 'span' );
		$a->setAttribute( 'href', $extApi->getPageUri() . '#' . Sanitizer::escapeIdForLink( $target ) );
		$span->setAttribute( 'class', 'mw-linkback-text' );
		if ( $group ) {
			$a->setAttribute( 'data-mw-group', $group );
		}
		$span->appendChild( $ownerDoc->createTextNode( $text . ' ' ) );
		$a->appendChild( $span );
		return $a;
	}

	public function renderLine(
		ParsoidExtensionAPI $extApi, Element $refsList, RefGroupItem $ref
	): void {
		$ownerDoc = $refsList->ownerDocument;

		// Generate the li and set ref content first, so the HTML gets parsed.
		// We then append the rest of the ref nodes before the first node
		$li = $ownerDoc->createElement( 'li' );
		$refDir = $ref->dir;
		$noteId = ParsoidAnchorFormatter::getNoteIdentifier( $ref );
		$refContentId = $ref->contentId;
		$refGroup = $ref->group;
		DOMUtils::addAttributes( $li, [
				'about' => '#' . $noteId,
				'id' => $noteId,
				'class' => ( $refDir === 'rtl' || $refDir === 'ltr' ) ? 'mw-cite-dir-' . $refDir : null
			]
		);
		$reftextSpan = $ownerDoc->createElement( 'span' );
		DOMUtils::addAttributes(
			$reftextSpan,
			[
				'id' => ParsoidAnchorFormatter::getNoteTextIdentifier( $ref ),
				// Add both mw-reference-text & reference-text for b/c.
				// We will remove duplicate classes in the future.
				'class' => 'mw-reference-text reference-text',
			]
		);
		if ( $refContentId ) {
			// `sup` is the wrapper created by RefTagHandler::sourceToDom()'s call to
			// `extApi->extTagToDOM()`.  Only its contents are relevant.
			$sup = $extApi->getContentDOM( $refContentId )->firstChild;
			DOMUtils::migrateChildren( $sup, $reftextSpan );
			'@phan-var Element $sup';  /** @var Element $sup */
			DOMCompat::remove( $sup );
			$extApi->clearContentDOM( $refContentId );
		} elseif ( $ref->externalFragment ) {
			DOMUtils::migrateChildren( $ref->externalFragment, $reftextSpan );
		}
		$li->appendChild( $reftextSpan );

		$errorUtils = new ErrorUtils( $extApi );
		// It seems counter-productive to go through hoops to not display all the errors considering that rendering
		// only the first one is considered deprecated in the legacy code. However, displaying the same error
		// multiple times for the same reference is also useless. Hence, we avoid displaying the same error
		// multiple times.
		$reported = [];
		foreach ( $ref->nodes as $node ) {
			foreach ( DOMDataUtils::getDataMw( $node )->errors ?? [] as $error ) {
				if ( in_array( $error, $reported ) ) {
					continue;
				}
				$reported[] = $error;
				$errorFragment = $errorUtils->renderParsoidError( $error );
				$li->appendChild( $errorFragment );
			}
		}

		// mw:referencedBy is added to the <span> for the named refs case
		// and to the <a> tag to the unnamed refs case. This difference
		// is used by CSS to style backlinks in MediaWiki:Common.css
		// of various wikis.
		$linkbackSpan = $ownerDoc->createElement( 'span' );
		if ( $ref->visibleNodes === 1 ) {
			// Can be an unnamed reference or a named one that's just never reused
			$lb = ParsoidAnchorFormatter::getBackLinkIdentifier( $ref );
			$linkback = self::createLinkback( $extApi, $lb, $refGroup, "↑", $ownerDoc );
			DOMUtils::addRel( $linkback, 'mw:referencedBy' );
			$linkbackSpan->appendChild( $linkback );
		} else {
			DOMUtils::addRel( $linkbackSpan, 'mw:referencedBy' );
			for ( $i = 1; $i <= $ref->visibleNodes; $i++ ) {
				$lb = ParsoidAnchorFormatter::getBackLinkIdentifier( $ref, $i );
				$linkbackSpan->appendChild(
					self::createLinkback( $extApi, $lb, $refGroup, (string)$i, $ownerDoc )
				);
			}
		}
		DOMCompat::getClassList( $linkbackSpan )->add( 'mw-cite-backlink' );
		$li->insertBefore( $linkbackSpan, $reftextSpan );

		// Space before content node
		$li->insertBefore( $ownerDoc->createTextNode( ' ' ), $reftextSpan );

		// Add it to the ref list
		$refsList->appendChild( $li );

		// Backward-compatibility: add newline (T372889)
		$refsList->appendChild( $ownerDoc->createTextNode( "\n" ) );
	}

	/** @internal only for {@see ReferencesData} */
	public function getNextIndex(): int {
		return $this->nextIndex++;
	}

	/** @internal only for {@see ReferencesData} */
	public function getNextSubrefSequence( string $parentName ): int {
		$this->subRefCountByName[$parentName] ??= 0;
		return ++$this->subRefCountByName[$parentName];
	}
}

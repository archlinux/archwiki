<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\DOM\Comment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\DOM\Node;
use Wikimedia\Parsoid\DOM\Text;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wikitext\Consts;

class CleanUp {
	/**
	 * @param Element $node
	 * @param Env $env
	 * @return bool|Element
	 */
	public static function stripMarkerMetas( Element $node, Env $env ) {
		if (
			// Sometimes a non-tpl meta node might get the mw:Transclusion typeof
			// element attached to it. So, check if the node has data-mw,
			// in which case we also have to keep it.
			!DOMDataUtils::validDataMw( $node ) && (
				(
					DOMUtils::hasTypeOf( $node, 'mw:Placeholder/StrippedTag' ) &&
					// NOTE: In ComputeDSR, we don't zero out the width of these
					// markers because they're staying in the DOM and serializeDOMNode
					// only handles a few cases of zero width nodes.
					!DOMUtils::isNestedInListItem( $node )
				) ||
				DOMUtils::hasTypeOf( $node, 'mw:Transclusion' )
			)
		) {
			$nextNode = $node->nextSibling;
			$node->parentNode->removeChild( $node );
			// stop the traversal, since this node is no longer in the DOM.
			return $nextNode;
		} else {
			return true;
		}
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	private static function isEmptyNode( Node $node ): bool {
		$n = $node->firstChild;
		while ( $n ) {
			// Comments, sol-transparent links, nowiki spans without content
			// are all stripped  by the core parser.
			// Text nodes with whitespace don't count either.
			if ( $n instanceof Comment ||
				WTUtils::isSolTransparentLink( $n ) ||
				( $n instanceof Text && preg_match( '/^[ \t]*$/D',  $n->nodeValue ) ) ||
				( DOMUtils::hasTypeOf( $n, 'mw:Nowiki' ) && self::isEmptyNode( $n ) )
			) {
				$n = $n->nextSibling;
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * @param Node $node
	 * @param Env $env
	 * @param array $options
	 * @param bool $atTopLevel
	 * @param ?stdClass $tplInfo
	 * @return bool|Node
	 */
	public static function handleEmptyElements(
		Node $node, Env $env, array $options, bool $atTopLevel = false,
		?stdClass $tplInfo = null
	) {
		if ( !( $node instanceof Element ) ||
			!isset( Consts::$Output['FlaggedEmptyElts'][DOMCompat::nodeName( $node )] ) ||
			!self::isEmptyNode( $node )
		) {
			return true;
		}
		foreach ( DOMUtils::attributes( $node ) as $name => $value ) {
			if ( ( $name !== DOMDataUtils::DATA_OBJECT_ATTR_NAME ) &&
				( !$tplInfo || $name !== 'about' || !Utils::isParsoidObjectId( $value ) )
			) {
				return true;
			}
		}

		/**
		 * The node is known to be empty and a deletion candidate
		 * - If node is part of template content, it can be deleted
		 *   (since we know it has no attributes, it won't be the
		 *   first node that has about, typeof, and other attrs)
		 * - If not, we add the mw-empty-elt class so that wikis
		 *   can decide what to do with them.
		 */
		if ( $tplInfo ) {
			$nextNode = $node->nextSibling;
			$node->parentNode->removeChild( $node );
			return $nextNode;
		} else {
			DOMCompat::getClassList( $node )->add( 'mw-empty-elt' );
			return true;
		}
	}

	/**
	 * FIXME: Worry about "about" siblings
	 *
	 * @param Env $env
	 * @param Element $node
	 * @return bool
	 */
	private static function inNativeContent( Env $env, Element $node ): bool {
		while ( !DOMUtils::atTheTop( $node ) ) {
			if ( WTUtils::getNativeExt( $env, $node ) !== null ) {
				return true;
			}
			$node = $node->parentNode;
		}
		return false;
	}

	/**
	 * Whitespace in this function refers to [ \t] only
	 * @param Element $node
	 * @param ?DomSourceRange $dsr
	 */
	private static function trimWhiteSpace( Element $node, ?DomSourceRange $dsr ): void {
		// Trim leading ws (on the first line)
		$trimmedLen = 0;
		$updateDSR = true;
		$skipped = false;
		for ( $c = $node->firstChild; $c; $c = $next ) {
			$next = $c->nextSibling;
			if ( $c instanceof Text && preg_match( '/^[ \t]*$/D', $c->nodeValue ) ) {
				$node->removeChild( $c );
				$trimmedLen += strlen( $c->nodeValue );
				$updateDSR = !$skipped;
			} elseif ( !WTUtils::isRenderingTransparentNode( $c ) ) {
				break;
			} else {
				// We are now skipping over a rendering transparent node
				// and will trim additional whitespace => we cannot reliably
				// maintain info about trimmed whitespace.
				$skipped = true;
			}
		}

		if ( $c instanceof Text &&
			preg_match( '/^([ \t]+)([\s\S]*)$/D', $c->nodeValue, $matches )
		) {
			$updateDSR = !$skipped;
			$c->nodeValue = $matches[2];
			$trimmedLen += strlen( $matches[1] );
		}

		if ( $dsr ) {
			$dsr->leadingWS = $updateDSR ? $trimmedLen : -1;
		}

		// Trim trailing ws (on the last line)
		$trimmedLen = 0;
		$updateDSR = true;
		$skipped = false;
		for ( $c = $node->lastChild; $c; $c = $prev ) {
			$prev = $c->previousSibling;
			if ( $c instanceof Text && preg_match( '/^[ \t]*$/D', $c->nodeValue ) ) {
				$trimmedLen += strlen( $c->nodeValue );
				$node->removeChild( $c );
				$updateDSR = !$skipped;
			} elseif ( !WTUtils::isRenderingTransparentNode( $c ) ) {
				break;
			} else {
				// We are now skipping over a rendering transparent node
				// and will trim additional whitespace => we cannot reliably
				// maintain info about trimmed whitespace.
				$skipped = true;
			}
		}

		if ( $c instanceof Text &&
			preg_match( '/^([\s\S]*\S)([ \t]+)$/D', $c->nodeValue, $matches )
		) {
			$updateDSR = !$skipped;
			$c->nodeValue = $matches[1];
			$trimmedLen += strlen( $matches[2] );
		}

		if ( $dsr ) {
			$dsr->trailingWS = $updateDSR ? $trimmedLen : -1;
		}
	}

	/**
	 * Perform some final cleanup and save data-parsoid attributes on each node.
	 *
	 * @param array $usedIdIndex
	 * @param Node $node
	 * @param Env $env
	 * @param bool $atTopLevel
	 * @param ?stdClass $tplInfo
	 * @return bool|Node The next node or true to continue with $node->nextSibling
	 */
	public static function cleanupAndSaveDataParsoid(
		array $usedIdIndex, Node $node, Env $env,
		bool $atTopLevel = false, ?stdClass $tplInfo = null
	) {
		if ( !( $node instanceof Element ) ) {
			return true;
		}

		$dp = DOMDataUtils::getDataParsoid( $node );
		// Delete from data parsoid, wikitext originating autoInsertedEnd info
		if ( !empty( $dp->autoInsertedEnd ) && !WTUtils::hasLiteralHTMLMarker( $dp ) &&
			isset( Consts::$WTTagsWithNoClosingTags[DOMCompat::nodeName( $node )] )
		) {
			unset( $dp->autoInsertedEnd );
		}

		$isFirstEncapsulationWrapperNode = ( $tplInfo->first ?? null ) === $node ||
			// Traversal isn't done with tplInfo for section tags, but we should
			// still clean them up as if they are the head of encapsulation.
			WTUtils::isParsoidSectionTag( $node );

		// Remove dp.src from elements that have valid data-mw and dsr.
		// This should reduce data-parsoid bloat.
		//
		// Presence of data-mw is a proxy for us knowing how to serialize
		// this content from HTML. Token handlers should strip src for
		// content where data-mw isn't necessary and html2wt knows how to
		// handle the HTML markup.
		$validDSR = DOMDataUtils::validDataMw( $node ) && Utils::isValidDSR( $dp->dsr ?? null );
		$isPageProp = DOMCompat::nodeName( $node ) === 'meta' &&
			str_starts_with( $node->getAttribute( 'property' ) ?? '', 'mw:PageProp/' );
		if ( $validDSR && !$isPageProp ) {
			unset( $dp->src );
		} elseif ( $isFirstEncapsulationWrapperNode && ( !$atTopLevel || empty( $dp->tsr ) ) ) {
			// Transcluded nodes will not have dp.tsr set
			// and don't need dp.src either.
			unset( $dp->src );
		}

		// Remove tsr
		if ( property_exists( $dp, 'tsr' ) ) {
			unset( $dp->tsr );
		}

		// Remove temporary information
		// @phan-suppress-next-line PhanTypeObjectUnsetDeclaredProperty
		unset( $dp->tmp );
		unset( $dp->extLinkContentOffsets ); // not stored in tmp currently

		// Various places, like ContentUtils::shiftDSR, can set this to `null`
		if ( property_exists( $dp, 'dsr' ) && $dp->dsr === null ) {
			unset( $dp->dsr );
		}

		// Make dsr zero-range for fostered content
		// to prevent selser from duplicating this content
		// outside the table from where this came.
		//
		// But, do not zero it out if the node has template encapsulation
		// information.  That will be disastrous (see T54638, T54488).
		if ( !empty( $dp->fostered ) && !empty( $dp->dsr ) && !$isFirstEncapsulationWrapperNode ) {
			$dp->dsr->start = $dp->dsr->end;
		}

		if ( $atTopLevel ) {
			// Strip nowiki spans from encapsulated content but leave behind
			// wrappers on root nodes since they have valid about ids and we
			// don't want to break the about-chain by stripping the wrapper
			// and associated ids (we cannot add an about id on the nowiki-ed
			// content since that would be a text node).
			if ( $tplInfo && !WTUtils::hasParsoidAboutId( $node ) &&
				 DOMUtils::hasTypeOf( $node, 'mw:Nowiki' )
			) {
				DOMUtils::migrateChildren( $node, $node->parentNode, $node->nextSibling );
				$next = $node->nextSibling;
				$node->parentNode->removeChild( $node );
				return $next;
			}

			// Trim whitespace from some wikitext markup
			// not involving explicit HTML tags (T157481)
			if ( !WTUtils::hasLiteralHTMLMarker( $dp ) &&
				isset( Consts::$WikitextTagsWithTrimmableWS[DOMCompat::nodeName( $node )] )
			) {
				self::trimWhiteSpace( $node, $dp->dsr ?? null );
			}

			$discardDataParsoid = $env->discardDataParsoid;

			// Strip data-parsoid from templated content, where unnecessary.
			if ( $tplInfo &&
				// Always keep info for the first node
				!$isFirstEncapsulationWrapperNode &&
				// We can't remove data-parsoid from inside <references> text,
				// as that's the only HTML representation we have left for it.
				!self::inNativeContent( $env, $node ) &&
				// FIXME: We can't remove dp from nodes with stx information
				// because the serializer uses stx information in some cases to
				// emit the right newline separators.
				//
				// For example, "a\n\nb" and "<p>a</p><p>b/p>" both generate
				// identical html but serialize to different wikitext.
				//
				// This is only needed for the last top-level node .
				( empty( $dp->stx ) || ( $tplInfo->last ?? null ) !== $node )
			) {
				$discardDataParsoid = true;
			}

			DOMDataUtils::storeDataAttribs( $node, [
					'discardDataParsoid' => $discardDataParsoid,
					// Even though we're passing in the `env`, this is the only place
					// we want the storage to happen, so don't refactor this in there.
					'storeInPageBundle' => $env->pageBundle,
					'idIndex' => $usedIdIndex,
					'env' => $env
				]
			);
		} // We only need the env in this case.
		return true;
	}
}

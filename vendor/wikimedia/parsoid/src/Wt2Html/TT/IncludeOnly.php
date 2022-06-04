<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * Simple noinclude / onlyinclude implementation.
 * Strips all tokens in noinclude sections.
 */
class IncludeOnly extends TokenCollector {
	/**
	 * @param TokenTransformManager $manager
	 * @param array $options options
	 */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
	}

	/**
	 * @return string
	 */
	protected function type(): string {
		return 'tag';
	}

	/**
	 * @return string
	 */
	protected function name(): string {
		return 'includeonly';
	}

	/**
	 * @return bool
	 */
	protected function toEnd(): bool {
		return true;    // Match the end-of-input if </noinclude> is missing.
	}

	/**
	 * @return bool
	 */
	protected function ackEnd(): bool {
		return false;
	}

	/**
	 * @param array $collection
	 * @return TokenHandlerResult
	 */
	protected function transformation( array $collection ): TokenHandlerResult {
		$start = array_shift( $collection );

		// Handle self-closing tag case specially!
		if ( $start instanceof SelfclosingTagTk ) {
			$tsr = $start->dataAttribs->tsr ?? new SourceRange( null, null );
			$token = TokenCollector::buildMetaToken(
				$this->manager,
				'mw:Includes/IncludeOnly',
				false,
				$tsr,
				null
			);
			if ( $start->dataAttribs->src ) {
				$datamw = PHPUtils::jsonEncode( [ 'src' => $start->dataAttribs->src ] );
				$token->addAttribute( 'data-mw', $datamw );
			}
			return ( $this->options['isInclude'] ) ?
				new TokenHandlerResult( [] ) : new TokenHandlerResult( [ $token ] );
		}

		$tokens = [];
		$end = array_pop( $collection );
		$eof = $end instanceof EOFTk;

		if ( $this->options['isInclude'] ) {
			// Just pass through the full collection including delimiters
			$tokens = $collection;
		} elseif ( !$this->options['inTemplate'] ) {
			// Content is stripped
			// Add meta tags for open and close for roundtripping.
			//
			// We can make do entirely with a single meta-tag since
			// there is no real content.  However, we add a dummy end meta-tag
			// so that all <*include*> meta tags show up in open/close pairs
			// and can be handled similarly by downstream handlers.
			$name = 'mw:Includes/IncludeOnly';
			$tokens[] = TokenCollector::buildStrippedMetaToken( $this->manager, $name,
				$start, $eof ? null : $end );

			if ( $start->dataAttribs->src ) {
				$dataMw = PHPUtils::jsonEncode( [ 'src' => $start->dataAttribs->src ] );
				$tokens[0]->addAttribute( 'data-mw', $dataMw );
			}

			if ( $end && !$eof ) {
				// This token is just a placeholder for RT purposes. Since the
				// stripped token (above) got the entire tsr value, we are artificially
				// setting the tsr on this node to zero-width to ensure that
				// DSR computation comes out correct.
				$endPos = isset( $end->dataAttribs->tsr ) ? $end->dataAttribs->tsr->end : null;
				$tokens[] = TokenCollector::buildMetaToken( $this->manager, $name,
					true, new SourceRange( $endPos, $endPos ), '' );
			}
		}

		// Preserve EOF
		if ( $eof ) {
			$tokens[] = $end;
		}

		return new TokenHandlerResult( $tokens );
	}
}

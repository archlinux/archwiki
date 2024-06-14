<?php

namespace MediaWiki\OutputTransform\Stages;

use MediaWiki\Linker\Linker;
use MediaWiki\OutputTransform\ContentTextTransformStage;
use MediaWiki\Parser\ParserOutput;
use ParserOptions;

/**
 * Expand relative links to absolute URLs
 * @internal
 */
class ExpandToAbsoluteUrls extends ContentTextTransformStage {

	public function shouldRun( ParserOutput $po, ?ParserOptions $popts, array $options = [] ): bool {
		return $options['absoluteURLs'] ?? false;
	}

	protected function transformText( string $text, ParserOutput $po, ?ParserOptions $popts, array &$options ): string {
		return Linker::expandLocalLinks( $text );
	}

}

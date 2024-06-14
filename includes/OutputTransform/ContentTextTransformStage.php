<?php

namespace MediaWiki\OutputTransform;

use MediaWiki\Parser\ParserOutput;
use ParserOptions;

/**
 * OutputTransformStages that only modify the content. It is expected that all inheriting classes call this class'
 * transform() method (either directly by inheritance or by calling them in the overloaded method).
 * @internal
 */
abstract class ContentTextTransformStage implements OutputTransformStage {

	public function transform( ParserOutput $po, ?ParserOptions $popts, array &$options ): ParserOutput {
		$text = $po->getContentHolderText();
		$text = $this->transformText( $text, $po, $popts, $options );
		$po->setContentHolderText( $text );
		return $po;
	}

	abstract protected function transformText(
		string $text, ParserOutput $po, ?ParserOptions $popts, array &$options
	): string;
}

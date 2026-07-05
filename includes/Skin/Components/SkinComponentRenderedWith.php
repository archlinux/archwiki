<?php

namespace MediaWiki\Skin\Components;

use MediaWiki\Language\MessageLocalizer;

class SkinComponentRenderedWith implements SkinComponent {

	/**
	 * @param MessageLocalizer $localizer
	 * @param bool $useParsoid whether Parsoid was used to render this page
	 */
	public function __construct(
		private MessageLocalizer $localizer,
		private bool $useParsoid = false,
	) {
	}

	/**
	 * Get data for the 'Rendered with' footer message
	 *
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$msg = $this->useParsoid ?
			 $this->localizer->msg( 'renderedwith-parsoid' ) :
			 $this->localizer->msg( 'renderedwith-legacy' );

		return [
			'is-parsoid' => $this->useParsoid,
			'text' => $msg->isDisabled() ? '' : $msg->parse(),
		];
	}
}

/** @deprecated class alias since 1.46 */
class_alias( SkinComponentRenderedWith::class, 'MediaWiki\\Skin\\SkinComponentRenderedWith' );

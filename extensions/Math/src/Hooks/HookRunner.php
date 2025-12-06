<?php

namespace MediaWiki\Extension\Math\Hooks;

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Revision\RevisionRecord;
use stdClass;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements
	MathFormulaPostRenderRevisionHook,
	MathRenderingResultRetrievedHook
{
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onMathFormulaPostRenderRevision(
		?RevisionRecord $revisionRecord,
		MathRenderer $renderer,
		string &$renderedMath
	) {
		return $this->hookContainer->run(
			'MathFormulaPostRenderRevision',
			[ $revisionRecord, $renderer, &$renderedMath ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onMathRenderingResultRetrieved( MathRenderer &$renderer, stdClass &$jsonResult ) {
		return $this->hookContainer->run(
			'MathRenderingResultRetrieved',
			[ &$renderer, &$jsonResult ]
		);
	}
}

<?php

namespace MediaWiki\Extension\Math\Hooks;

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Revision\RevisionRecord;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "MathFormulaPostRenderRevision" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface MathFormulaPostRenderRevisionHook {
	/**
	 * @param RevisionRecord|null $revisionRecord source of edit
	 * @param MathRenderer $renderer renderer used for render of math expression
	 * @param string &$renderedMath rendered html from math expression. Possible to modify result if needed.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onMathFormulaPostRenderRevision(
		?RevisionRecord $revisionRecord,
		MathRenderer $renderer,
		string &$renderedMath
	);
}

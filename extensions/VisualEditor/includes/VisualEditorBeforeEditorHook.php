<?php
/**
 * @file
 * @ingroup Extensions
 * @copyright 2011-2021 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;

interface VisualEditorBeforeEditorHook {

	/**
	 * This hook is executed in before deciding if the editor is available on a certain page
	 *
	 * If the hook returns false, the editor is not available.
	 *
	 * @param OutputPage $output
	 * @param Skin $skin
	 * @return bool
	 */
	public function onVisualEditorBeforeEditor(
		OutputPage $output,
		Skin $skin
	): bool;

}

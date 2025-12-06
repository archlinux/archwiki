<?php

namespace MediaWiki\Extension\VisualEditor;

/**
 * VisualEditorHookRunner
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2021 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Skin\Skin;
use MediaWiki\User\UserIdentity;

class VisualEditorHookRunner implements
	VisualEditorApiVisualEditorEditPreSaveHook,
	VisualEditorApiVisualEditorEditPostSaveHook,
	VisualEditorBeforeEditorHook
{

	public function __construct( private readonly HookContainer $hookContainer ) {
	}

	/** @inheritDoc */
	public function onVisualEditorApiVisualEditorEditPreSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array &$params,
		array $pluginData,
		array &$apiResponse
	) {
		return $this->hookContainer->run( 'VisualEditorApiVisualEditorEditPreSave', [
			$page,
			$user,
			$wikitext,
			&$params,
			$pluginData,
			&$apiResponse
		], [ 'abortable' => true ] );
	}

	/** @inheritDoc */
	public function onVisualEditorApiVisualEditorEditPostSave(
		ProperPageIdentity $page,
		UserIdentity $user,
		string $wikitext,
		array $params,
		array $pluginData,
		array $saveResult,
		array &$apiResponse
	): void {
		$this->hookContainer->run( 'VisualEditorApiVisualEditorEditPostSave', [
			$page,
			$user,
			$wikitext,
			$params,
			$pluginData,
			$saveResult,
			&$apiResponse
		], [ 'abortable' => false ] );
	}

	/** @inheritDoc */
	public function onVisualEditorBeforeEditor(
		OutputPage $output,
		Skin $skin
	): bool {
		return $this->hookContainer->run( 'VisualEditorBeforeEditor', [
			$output,
			$skin
		], [ 'abortable' => true ] );
	}
}

<?php

use MediaWiki\HookContainer\HookContainer;

/**
 * Handle running Nuke's hooks
 * @author DannyS712
 */
class NukeHookRunner implements NukeDeletePageHook, NukeGetNewPagesHook {

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param HookContainer $hookContainer
	 */
	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * Hook runner for the `NukeDeletePage` hook
	 *
	 * Allows other extensions to handle the deletion of titles
	 * Return true to let Nuke handle the deletion or false if it was already handled in the hook.
	 *
	 * @param Title $title title to delete
	 * @param string $reason reason for deletion
	 * @param bool &$deletionResult Whether the deletion was successful or not
	 * @return bool|void
	 */
	public function onNukeDeletePage( Title $title, string $reason, bool &$deletionResult ) {
		return $this->hookContainer->run(
			'NukeDeletePage',
			[ $title, $reason, &$deletionResult ]
		);
	}

	/**
	 * Hook runner for the `NukeGetNewPages` hook
	 *
	 * After searching for pages to delete. Can be used to add and remove pages.
	 *
	 * @param string $username username filter applied
	 * @param ?string $pattern pattern filter applied
	 * @param ?int $namespace namespace filter applied
	 * @param int $limit limit filter applied
	 * @param array &$pages page titles already retrieved
	 * @return bool|void
	 */
	public function onNukeGetNewPages(
		string $username,
		?string $pattern,
		?int $namespace,
		int $limit,
		array &$pages
	) {
		return $this->hookContainer->run(
			'NukeGetNewPages',
			[ $username, $pattern, $namespace, $limit, &$pages ]
		);
	}

}

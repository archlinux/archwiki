<?php

interface NukeDeletePageHook {

	/**
	 * Allows other extensions to handle the deletion of titles
	 * Return true to let Nuke handle the deletion or false if it was already handled in the hook.
	 *
	 * @param Title $title title to delete
	 * @param string $reason reason for deletion
	 * @param bool &$deletionResult Whether the deletion was successful or not
	 * @return bool|void
	 */
	public function onNukeDeletePage( Title $title, string $reason, bool &$deletionResult );
}

<?php

use MediaWiki\HookContainer\HookContainer;

class RenameuserHookRunner implements
	RenameUserAbortHook,
	RenameUserPreRenameHook,
	RenameUserCompleteHook,
	RenameUserSQLHook,
	RenameUserWarningHook
{

	/** @var HookContainer */
	private $container;

	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	public function onRenameUserAbort( int $uid, string $old, string $new ) {
		return $this->container->run(
			'RenameUserAbort',
			[ $uid, $old, $new ]
		);
	}

	public function onRenameUserComplete( int $uid, string $old, string $new ) : void {
		$this->container->run(
			'RenameUserComplete',
			[ $uid, $old, $new ],
			[ 'abortable' => false ]
		);
	}

	public function onRenameUserPreRename( int $uid, string $old, string $new ) : void {
		$this->container->run(
			'RenameUserPreRename',
			[ $uid, $old, $new ],
			[ 'abortable' => false ]
		);
	}

	public function onRenameUserSQL( RenameuserSQL $renameUserSql ) : void {
		$this->container->run(
			'RenameUserSQL',
			[ $renameUserSql ],
			[ 'abortable' => false ]
		);
	}

	public function onRenameUserWarning( string $oldUsername, string $newUsername, array &$warnings ) : void {
		$this->container->run(
			'RenameUserWarning',
			[ $oldUsername, $newUsername, &$warnings ],
			[ 'abortable' => false ]
		);
	}
}

<?php

namespace MediaWiki\CheckUser;

/**
 * An interface that provides several constants that are used
 * by code that reads from and/or writes to the CheckUser tables.
 */
interface CheckUserQueryInterface {

	/** @var string The name of the table where log events only shown in CheckUser are stored. */
	public const PRIVATE_LOG_EVENT_TABLE = 'cu_private_event';

	/** @var string The name of the table where log events are stored. */
	public const LOG_EVENT_TABLE = 'cu_log_event';

	/** @var string The name of the table where non-log actions are stored. */
	public const CHANGES_TABLE = 'cu_changes';

	/** @var string[] All tables that contain result rows. */
	public const RESULT_TABLES = [
		self::CHANGES_TABLE,
		self::LOG_EVENT_TABLE,
		self::PRIVATE_LOG_EVENT_TABLE,
	];

	/** @var string[] A map of result table name to table column/index prefix */
	public const RESULT_TABLE_TO_PREFIX = [
		self::CHANGES_TABLE => 'cuc_',
		self::LOG_EVENT_TABLE => 'cule_',
		self::PRIVATE_LOG_EVENT_TABLE => 'cupe_',
	];

	/** @var string The virtual database domain for the central index tables */
	public const VIRTUAL_GLOBAL_DB_DOMAIN = 'virtual-checkuser-global';
}

<?php

namespace MediaWiki\Extension\Renameuser;

use Job;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionManager;
use SpecialLog;
use Title;
use User;

/**
 * Class which performs the actual renaming of users
 */
class RenameuserSQL {
	/**
	 * The old username
	 *
	 * @var string
	 */
	public $old;

	/**
	 * The new username
	 *
	 * @var string
	 */
	public $new;

	/**
	 * The user ID
	 *
	 * @var int
	 */
	public $uid;

	/**
	 * The tables => fields to be updated
	 *
	 * @var array
	 */
	public $tables;

	/**
	 * tables => fields to be updated in a deferred job
	 *
	 * @var array[]
	 */
	public $tablesJob;

	/**
	 * Flag that can be set to false, in case another process has already started
	 * the updates and the old username may have already been renamed in the user table.
	 *
	 * @var bool
	 */
	public $checkIfUserExists;

	/**
	 * User object of the user performing the rename, for logging purposes
	 *
	 * @var User
	 */
	private $renamer;

	/**
	 * Reason to be used in the log entry
	 *
	 * @var string
	 */
	private $reason = '';

	/**
	 * A prefix to use in all debug log messages
	 *
	 * @var string
	 */
	private $debugPrefix = '';

	/**
	 * Users with more than this number of edits will have their rename operation
	 * deferred via the job queue.
	 */
	private const CONTRIB_JOB = 500;

	// B/C constants for tablesJob field
	public const NAME_COL = 0;
	public const UID_COL  = 1;
	public const TIME_COL = 2;

	/** @var RenameuserHookRunner */
	private $hookRunner;

	/**
	 * Constructor
	 *
	 * @param string $old The old username
	 * @param string $new The new username
	 * @param int $uid
	 * @param User $renamer
	 * @param array $options Optional extra options.
	 *    'reason' - string, reason for the rename
	 *    'debugPrefix' - string, prefixed to debug messages
	 *    'checkIfUserExists' - bool, whether to update the user table
	 */
	public function __construct( $old, $new, $uid, User $renamer, $options = [] ) {
		$this->hookRunner = new RenameuserHookRunner( MediaWikiServices::getInstance()->getHookContainer() );

		$this->old = $old;
		$this->new = $new;
		$this->uid = $uid;
		$this->renamer = $renamer;
		$this->checkIfUserExists = true;

		if ( isset( $options['checkIfUserExists'] ) ) {
			$this->checkIfUserExists = $options['checkIfUserExists'];
		}

		if ( isset( $options['debugPrefix'] ) ) {
			$this->debugPrefix = $options['debugPrefix'];
		}

		if ( isset( $options['reason'] ) ) {
			$this->reason = $options['reason'];
		}

		$this->tables = []; // Immediate updates
		$this->tablesJob = []; // Slow updates

		$this->hookRunner->onRenameUserSQL( $this );
	}

	protected function debug( $msg ) {
		if ( $this->debugPrefix ) {
			$msg = "{$this->debugPrefix}: $msg";
		}
		wfDebugLog( 'Renameuser', $msg );
	}

	/**
	 * Do the rename operation
	 * @return bool
	 */
	public function rename() {
		global $wgUpdateRowsPerJob;

		// Grab the user's edit count first, used in log entry
		$contribs = User::newFromId( $this->uid )->getEditCount();

		$dbw = wfGetDB( DB_PRIMARY );
		$atomicId = $dbw->startAtomic( __METHOD__, $dbw::ATOMIC_CANCELABLE );

		$this->hookRunner->onRenameUserPreRename( $this->uid, $this->old, $this->new );

		// Make sure the user exists if needed
		if ( $this->checkIfUserExists && !self::lockUserAndGetId( $this->old ) ) {
			$this->debug( "User {$this->old} does not exist, bailing out" );
			$dbw->cancelAtomic( __METHOD__, $atomicId );

			return false;
		}

		// Rename and touch the user before re-attributing edits to avoid users still being
		// logged in and making new edits (under the old name) while being renamed.
		$this->debug( "Starting rename of {$this->old} to {$this->new}" );
		$dbw->update( 'user',
			[ 'user_name' => $this->new, 'user_touched' => $dbw->timestamp() ],
			[ 'user_name' => $this->old, 'user_id' => $this->uid ],
			__METHOD__
		);
		$dbw->update( 'actor',
			[ 'actor_name' => $this->new ],
			[ 'actor_name' => $this->old, 'actor_user' => $this->uid ],
			__METHOD__
		);

		// Reset token to break login with central auth systems.
		// Again, avoids user being logged in with old name.
		$user = User::newFromId( $this->uid );

		$user->load( User::READ_LATEST );
		SessionManager::singleton()->invalidateSessionsForUser( $user );

		// Purge user cache
		$user->invalidateCache();

		// Update ipblock list if this user has a block in there.
		$dbw->update( 'ipblocks',
			[ 'ipb_address' => $this->new ],
			[ 'ipb_user' => $this->uid, 'ipb_address' => $this->old ],
			__METHOD__
		);
		// Update this users block/rights log. Ideally, the logs would be historical,
		// but it is really annoying when users have "clean" block logs by virtue of
		// being renamed, which makes admin tasks more of a pain...
		$oldTitle = Title::makeTitle( NS_USER, $this->old );
		$newTitle = Title::makeTitle( NS_USER, $this->new );
		$this->debug( "Updating logging table for {$this->old} to {$this->new}" );

		// Exclude user renames per T200731
		$logTypesOnUser = array_diff( SpecialLog::getLogTypesOnUser(), [ 'renameuser' ] );

		$dbw->update( 'logging',
			[ 'log_title' => $newTitle->getDBkey() ],
			[
				'log_type' => $logTypesOnUser,
				'log_namespace' => NS_USER,
				'log_title' => $oldTitle->getDBkey()
			],
			__METHOD__
		);

		$this->debug( "Updating recentchanges table for {$this->old} to {$this->new}" );
		$dbw->update( 'recentchanges',
			[ 'rc_title' => $newTitle->getDBkey() ],
			[
				'rc_type' => RC_LOG,
				'rc_log_type' => $logTypesOnUser,
				'rc_namespace' => NS_USER,
				'rc_title' => $oldTitle->getDBkey()
			],
			__METHOD__
		);

		// Do immediate re-attribution table updates...
		foreach ( $this->tables as $table => $fieldSet ) {
			list( $nameCol, $userCol ) = $fieldSet;
			$dbw->update( $table,
				[ $nameCol => $this->new ],
				[ $nameCol => $this->old, $userCol => $this->uid ],
				__METHOD__
			);
		}

		/** @var RenameUserJob[] $jobs */
		$jobs = []; // jobs for all tables
		// Construct jobqueue updates...
		// FIXME: if a bureaucrat renames a user in error, he/she
		// must be careful to wait until the rename finishes before
		// renaming back. This is due to the fact the job "queue"
		// is not really FIFO, so we might end up with a bunch of edits
		// randomly mixed between the two new names. Some sort of rename
		// lock might be in order...
		foreach ( $this->tablesJob as $table => $params ) {
			$userTextC = $params[self::NAME_COL]; // some *_user_text column
			$userIDC = $params[self::UID_COL]; // some *_user column
			$timestampC = $params[self::TIME_COL]; // some *_timestamp column

			$res = $dbw->select( $table,
				[ $timestampC ],
				[ $userTextC => $this->old, $userIDC => $this->uid ],
				__METHOD__,
				[ 'ORDER BY' => "$timestampC ASC" ]
			);

			$jobParams = [];
			$jobParams['table'] = $table;
			$jobParams['column'] = $userTextC;
			$jobParams['uidColumn'] = $userIDC;
			$jobParams['timestampColumn'] = $timestampC;
			$jobParams['oldname'] = $this->old;
			$jobParams['newname'] = $this->new;
			$jobParams['userID'] = $this->uid;
			// Timestamp column data for index optimizations
			$jobParams['minTimestamp'] = '0';
			$jobParams['maxTimestamp'] = '0';
			$jobParams['count'] = 0;
			// Unique column for replica lag avoidance
			if ( isset( $params['uniqueKey'] ) ) {
				$jobParams['uniqueKey'] = $params['uniqueKey'];
			}

			// Insert jobs into queue!
			foreach ( $res as $row ) {
				# Since the ORDER BY is ASC, set the min timestamp with first row
				if ( $jobParams['count'] === 0 ) {
					$jobParams['minTimestamp'] = $row->$timestampC;
				}
				# Keep updating the last timestamp, so it should be correct
				# when the last item is added.
				$jobParams['maxTimestamp'] = $row->$timestampC;
				# Update row counter
				$jobParams['count']++;
				# Once a job has $wgUpdateRowsPerJob rows, add it to the queue
				if ( $jobParams['count'] >= $wgUpdateRowsPerJob ) {
					$jobs[] = Job::factory( 'renameUser', $oldTitle, $jobParams );
					$jobParams['minTimestamp'] = '0';
					$jobParams['maxTimestamp'] = '0';
					$jobParams['count'] = 0;
				}
			}
			# If there are any job rows left, add it to the queue as one job
			if ( $jobParams['count'] > 0 ) {
				$jobs[] = Job::factory( 'renameUser', $oldTitle, $jobParams );
			}
		}

		// Log it!
		$logEntry = new ManualLogEntry( 'renameuser', 'renameuser' );
		$logEntry->setPerformer( $this->renamer );
		$logEntry->setTarget( $oldTitle );
		$logEntry->setComment( $this->reason );
		$logEntry->setParameters( [
			'4::olduser' => $this->old,
			'5::newuser' => $this->new,
			'6::edits' => $contribs
		] );
		$logid = $logEntry->insert();
		// Include the log_id in the jobs as a DB commit marker
		foreach ( $jobs as $job ) {
			$job->params['logId'] = $logid;
		}

		// Insert any jobs as needed. If this fails, then an exception will be thrown and the
		// DB transaction will be rolled back. If it succeeds but the DB commit fails, then the
		// jobs will see that the transaction was not committed and will cancel themselves.
		$count = count( $jobs );
		if ( $count > 0 ) {
			MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $jobs );
			$this->debug( "Queued $count jobs for {$this->old} to {$this->new}" );
		}

		// Commit the transaction
		$dbw->endAtomic( __METHOD__ );

		$fname = __METHOD__;
		$dbw->onTransactionCommitOrIdle(
			function () use ( $dbw, $logEntry, $logid, $fname ) {
				$dbw->startAtomic( $fname );
				// Clear caches and inform authentication plugins
				$user = User::newFromId( $this->uid );
				$user->load( User::READ_LATEST );
				// Trigger the UserSaveSettings hook
				$user->saveSettings();
				$this->hookRunner->onRenameUserComplete( $this->uid, $this->old, $this->new );
				// Publish to RC
				$logEntry->publish( $logid );
				$dbw->endAtomic( $fname );
			},
			$fname
		);

		$this->debug( "Finished rename for {$this->old} to {$this->new}" );

		return true;
	}

	/**
	 * @param string $name Current wiki local user name
	 * @return int Returns 0 if no row was found
	 */
	private static function lockUserAndGetId( $name ) {
		return (int)wfGetDB( DB_PRIMARY )->selectField(
			'user',
			'user_id',
			[ 'user_name' => $name ],
			__METHOD__,
			[ 'FOR UPDATE' ]
		);
	}
}

class_alias( RenameuserSQL::class, 'RenameuserSQL' );

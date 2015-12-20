<?php

/**
 * Class which performs the actual renaming of users
 */
class RenameuserSQL {
	/**
	 * The old username
	 *
	 * @var string
	 * @access private
	 */
	public $old;

	/**
	 * The new username
	 *
	 * @var string
	 * @access private
	 */
	public $new;

	/**
	 * The user ID
	 *
	 * @var integer
	 * @access private
	 */
	public $uid;

	/**
	 * The the tables => fields to be updated
	 *
	 * @var array
	 * @access private
	 */
	public $tables;

	/**
	 * Flag that can be set to false, in case another process has already started
	 * the updates and the old username may have already been renamed in the user table.
	 *
	 * @var bool
	 * @access private
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
	const CONTRIB_JOB = 500;

	/**
	 * Constructor
	 *
	 * @param $old string The old username
	 * @param $new string The new username
	 * @param $uid
	 * @param User $renamer
	 * @param $options Array of options
	 *    'checkIfUserExists' - bool, whether to update the user table
	 */
	public function __construct( $old, $new, $uid, User $renamer, $options = array() ) {
		$this->old = $old;
		$this->new = $new;
		$this->uid = $uid;
		$this->renamer = $renamer;
		$this->checkIfUserExists = true;

		if ( isset ( $options['checkIfUserExists'] ) ) {
			$this->checkIfUserExists = $options['checkIfUserExists'];
		}

		if ( isset( $options['debugPrefix'] ) ) {
			$this->debugPrefix = $options['debugPrefix'];
		}

		if ( isset( $options['reason'] ) ) {
			$this->reason = $options['reason'];
		}

		$this->tables = array(); // Immediate updates
		$this->tables['image'] = array( 'img_user_text', 'img_user' );
		$this->tables['oldimage'] = array( 'oi_user_text', 'oi_user' );
		$this->tables['filearchive'] = array( 'fa_user_text', 'fa_user' );
		$this->tablesJob = array(); // Slow updates
		// If this user has a large number of edits, use the jobqueue
		if ( User::newFromId( $uid )->getEditCount() > self::CONTRIB_JOB ) {
			$this->tablesJob['revision'] = array( 'rev_user_text', 'rev_user', 'rev_timestamp' );
			$this->tablesJob['archive'] = array( 'ar_user_text', 'ar_user', 'ar_timestamp' );
			$this->tablesJob['logging'] = array( 'log_user_text', 'log_user', 'log_timestamp' );
		} else {
			$this->tables['revision'] = array( 'rev_user_text', 'rev_user' );
			$this->tables['archive'] = array( 'ar_user_text', 'ar_user' );
			$this->tables['logging'] = array( 'log_user_text', 'log_user' );
		}
		// Recent changes is pretty hot, deadlocks occur if done all at once
		if ( wfQueriesMustScale() ) {
			$this->tablesJob['recentchanges'] = array( 'rc_user_text', 'rc_user', 'rc_timestamp' );
		} else {
			$this->tables['recentchanges'] = array( 'rc_user_text', 'rc_user' );
		}

		Hooks::run( 'RenameUserSQL', array( $this ) );
	}

	protected function debug( $msg ) {
		if ( $this->debugPrefix ) {
			$msg = "{$this->debugPrefix}: $msg";
		}
		wfDebugLog( 'Renameuser', $msg );
	}

	/**
	 * Do the rename operation
	 */
	public function rename() {
		global $wgAuth, $wgUpdateRowsPerJob;

		// Grab the user's edit count first, used in log entry
		$contribs = User::newFromId( $this->uid )->getEditCount();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		Hooks::run( 'RenameUserPreRename', array( $this->uid, $this->old, $this->new ) );

		// Rename and touch the user before re-attributing edits,
		// this avoids users still being logged in and making new edits while
		// being renamed, which leaves edits at the old name.
		$this->debug( "Starting rename of {$this->old} to {$this->new}" );
		$dbw->update( 'user',
			array( 'user_name' => $this->new, 'user_touched' => $dbw->timestamp() ),
			array( 'user_name' => $this->old, 'user_id' => $this->uid ),
			__METHOD__
		);

		if ( !$dbw->affectedRows() && $this->checkIfUserExists ) {
			$dbw->rollback();
			$this->debug( "User {$this->old} does not exist, bailing out" );

			return false;
		}

		// Reset token to break login with central auth systems.
		// Again, avoids user being logged in with old name.
		$user = User::newFromId( $this->uid );
		$authUser = $wgAuth->getUserInstance( $user );
		$authUser->resetAuthToken();

		// Delete from memcached.
		$user->invalidateCache();

		// Update ipblock list if this user has a block in there.
		$dbw->update( 'ipblocks',
			array( 'ipb_address' => $this->new ),
			array( 'ipb_user' => $this->uid, 'ipb_address' => $this->old ),
			__METHOD__ );
		// Update this users block/rights log. Ideally, the logs would be historical,
		// but it is really annoying when users have "clean" block logs by virtue of
		// being renamed, which makes admin tasks more of a pain...
		$oldTitle = Title::makeTitle( NS_USER, $this->old );
		$newTitle = Title::makeTitle( NS_USER, $this->new );
		$this->debug( "Updating logging table for {$this->old} to {$this->new}" );
		if ( is_callable( 'SpecialLog::getLogTypesOnUser' ) ) { // 1.25+
			$logTypesOnUser = SpecialLog::getLogTypesOnUser();
		} else {
			// Fallback to hardcoded list
			$logTypesOnUser = array( 'block', 'rights' );
		}
		$dbw->update( 'logging',
			array( 'log_title' => $newTitle->getDBkey() ),
			array( 'log_type' => $logTypesOnUser,
				'log_namespace' => NS_USER,
				'log_title' => $oldTitle->getDBkey() ),
			__METHOD__ );
		// Do immediate updates!
		foreach ( $this->tables as $table => $fieldSet ) {
			list( $nameCol, $userCol ) = $fieldSet;
			$dbw->update( $table,
				array( $nameCol => $this->new ),
				array( $nameCol => $this->old, $userCol => $this->uid ),
				__METHOD__
			);
		}

		// Increase time limit (like CheckUser); this can take a while...
		if ( $this->tablesJob ) {
			wfSuppressWarnings();
			set_time_limit( 120 );
			wfRestoreWarnings();
		}

		$jobs = array(); // jobs for all tables
		// Construct jobqueue updates...
		// FIXME: if a bureaucrat renames a user in error, he/she
		// must be careful to wait until the rename finishes before
		// renaming back. This is due to the fact the the job "queue"
		// is not really FIFO, so we might end up with a bunch of edits
		// randomly mixed between the two new names. Some sort of rename
		// lock might be in order...
		foreach ( $this->tablesJob as $table => $params ) {
			$userTextC = $params[0]; // some *_user_text column
			$userIDC = $params[1]; // some *_user column
			$timestampC = $params[2]; // some *_timestamp column

			$res = $dbw->select( $table,
				array( $timestampC ),
				array( $userTextC => $this->old, $userIDC => $this->uid ),
				__METHOD__,
				array( 'ORDER BY' => "$timestampC ASC" )
			);

			$jobParams = array();
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

			// Insert jobs into queue!
			while ( true ) {
				$row = $dbw->fetchObject( $res );
				if ( !$row ) {
					# If there are any job rows left, add it to the queue as one job
					if ( $jobParams['count'] > 0 ) {
						$jobs[] = Job::factory( 'renameUser', $oldTitle, $jobParams );
					}
					break;
				}
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
			$dbw->freeResult( $res );
		}

		$count = count( $jobs );
		if ( $count > 0 ) {
			JobQueueGroup::singleton()->push( $jobs, JobQueue::QOS_ATOMIC ); // don't commit yet
			$this->debug( "Queued $count jobs for {$this->old} to {$this->new}" );
		}

		// Commit the transaction
		$dbw->commit();

		// Delete from memcached again to make sure
		$user->invalidateCache();

		// Clear caches and inform authentication plugins
		$user = User::newFromId( $this->uid );
		$wgAuth->updateExternalDB( $user );
		Hooks::run( 'RenameUserComplete', array( $this->uid, $this->old, $this->new ) );

		// Log it!
		$logEntry = new ManualLogEntry( 'renameuser', 'renameuser' );
		$logEntry->setPerformer( $this->renamer );
		$logEntry->setTarget( $oldTitle );
		$logEntry->setComment( $this->reason );
		$logEntry->setParameters( array(
			'4::olduser' => $this->old,
			'5::newuser' => $this->new,
			'6::edits' => $contribs
		) );
		$logid = $logEntry->insert();
		$logEntry->publish( $logid );

		$this->debug( "Finished rename for {$this->old} to {$this->new}" );

		return true;
	}
}

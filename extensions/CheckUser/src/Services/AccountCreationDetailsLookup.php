<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Provides IP and User Agent information associated with the local creation
 * of a user, whether by that user or by another performer
 *
 * Currently only used by CentralAuth for creating missing local accounts
 * using consistent IP/User Agent info
 */
class AccountCreationDetailsLookup {

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::NewUserLog,
	];

	private LoggerInterface $logger;
	private ServiceOptions $options;

	public function __construct(
		LoggerInterface $logger,
		ServiceOptions $options
	) {
		$this->logger = $logger;
		$this->options = $options;
	}

	/**
	 * Given a username as it appears in some other local wiki database or in the globaluser
	 * table, and a db connection for the local wiki, return a row with the IP address
	 * and user agent logged at the time the user was created on the local wiki, if
	 * available, or an empty result set otherwise
	 *
	 * @param string $username the name of the user as stored in a local or central database
	 * @param IReadableDatabase $dbr
	 * @return IResultWrapper
	 */
	public function getIPAndUserAgentFromDB( string $username, IReadableDatabase $dbr ) {
		// events will be logged in the private event table unless $wgNewUserLog is true,
		// and config can be changed at any time, so we must check both there and the public
		// log event table.
		$result = $dbr->newSelectQueryBuilder()
			->select( [ 'cupe_ip_hex', 'cupe_agent' ] )
			->from( 'cu_private_event' )
			->join( 'actor', null, [ 'cupe_actor = actor_id' ] )
			->where( $dbr->expr( 'cupe_log_action', '=', [ 'create-account', 'autocreate-account' ] ) )
			->andWhere( $dbr->expr( 'actor_name', '=', $username ) )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchResultSet();
		if ( $result->numRows() ) {
			return $result;
		}
		return $dbr->newSelectQueryBuilder()
			->select( [ 'cule_ip_hex', 'cule_agent' ] )
			->from( 'cu_log_event' )
			->join( 'actor', null, [ 'cule_actor = actor_id' ] )
			->join( 'logging', null, [ 'cule_log_id = log_id' ] )
			->where( $dbr->expr( 'log_action', '=', 'create' ) )
			->andWhere( $dbr->expr( 'actor_name', '=', $username ) )
			->limit( 2 )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * Given a username as it appears in some other local wiki database or in the globaluser
	 * users table, a db connection for the local wiki, and the id of the log entry for
	 * the creation of this user on the local wiki by another user, return the IP address
	 * and user agent of the performer if available, or an empty result set otherwise
	 *
	 * @param string $username the name of the user as stored in a local or central database
	 * @param IReadableDatabase $dbr
	 * @param int $logId the log_id value from the entry in the logging table for which we
	 *   want the performer's ip and user agent
	 * @return IResultWrapper
	 */
	public function getIPAndUserAgentForCreationByOtherUser( string $username, IReadableDatabase $dbr, int $logId ) {
		// we want the ip and user agent for the performer ($username) that did the account
		// creation recorded in the logging table with id log_id
		// we cannot do this for events written only into the cu_private_event table, so we
		// only look at cu_log_event.
		return $dbr->newSelectQueryBuilder()
			->select( [ 'cule_ip_hex', 'cule_agent' ] )
			->from( 'cu_log_event' )
			->join( 'actor', null, [ 'cule_actor = actor_id' ] )
			->join( 'logging', null, [ 'cule_log_id = log_id' ] )
			->where( $dbr->expr( 'cule_log_id', '=', $logId ) )
			->andWhere( $dbr->expr( 'actor_name', '=', $username ) )
			->andWhere( $dbr->expr( 'log_action', '=', [ 'create2', 'byemail' ] ) )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * Given a db connection open for the local wiki, the name of a global user,
	 * and the timestamp of the account registration, look for log entries of the user creation,
	 * searching back up to an hour before the account registration, and return the performer
	 * user name and the id of the earliest log entry if available, or null otherwise
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $username user name as it appears in the globaluser table
	 * @param string $registration registration timestamp as it appears in the globaluser table
	 * @return array{0:string, 1:int}|null
	 */
	public function findPerformerAndLogId( $dbr, $username, $registration ) {
		// new user log is not enabled, so there would be no entries for us
		if ( !$this->options->get( MainConfigNames::NewUserLog ) ) {
			return null;
		}

		// find all performers within a minute of the registration that created the specific
		// user one way or another
		$startdate = ConvertibleTimestamp::convert(
			TS_MW,
			(int)ConvertibleTimestamp::convert( TS_UNIX, $registration ) - 60
		);
		$enddate = ConvertibleTimestamp::convert(
			TS_MW,
			(int)ConvertibleTimestamp::convert( TS_UNIX, $registration ) + 60
		);

		// tryNew should never return null, because by this point (with the
		// user already in existence somewhere locally as well as globally),
		// the name should have passed more stringent tests than TitleValue has
		$userPageTitle = TitleValue::tryNew( 2, $username )->getDBkey();
		// we'll take the oldest entry if there's more than one.
		$row = $dbr->newSelectQueryBuilder()
			->select( [ 'log_id', 'actor_name' ] )
			->from( 'logging' )
			->join( 'actor', null, [ 'log_actor = actor_id' ] )
			->where( [ 'log_type' => 'newusers' ] )
			->andWhere( $dbr->expr( 'log_timestamp', '>=', $startdate ) )
			->andWhere( $dbr->expr( 'log_timestamp', '<=', $enddate ) )
			->andWhere( $dbr->expr( 'log_namespace', '=', 2 ) )
			->andWhere( $dbr->expr( 'log_title', '=', $userPageTitle ) )
			->orderBy( 'log_timestamp', SelectQueryBuilder::SORT_ASC )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			return null;
		}
		return [ $row->actor_name ?? '', intval( $row->log_id ) ];
	}

	/**
	 * Returns the ip and user agent associated with the account creation for the given user,
	 * or null if none can be found
	 *
	 * @param string $username the name of the user as stored in a local or central database
	 * @param IReadableDatabase $dbr
	 * @param int|null $logId
	 * @return array{ip: string, agent: string}|null
	 */
	public function getAccountCreationIPAndUserAgent(
		string $username, IReadableDatabase $dbr, ?int $logId = null ) {
		if ( $logId ) {
			$result = $this->getIPAndUserAgentForCreationByOtherUser( $username, $dbr, $logId );
		} else {
			$result = $this->getIPAndUserAgentFromDB( $username, $dbr );
		}

		if ( $result->numRows() == 0 ) {
			# probably older than the checkuser keep timeframe
			return null;
		} elseif ( $result->numRows() > 1 ) {
			# not sure what this could mean, dunno if worth logging
			$this->logger->warning( "More than one account creation entry for user $username on a specific wiki" );
		}
		foreach ( $result as $row ) {
			if ( isset( $row->cupe_ip_hex ) ) {
				return [ 'ip' => IPUtils::formatHex( $row->cupe_ip_hex ), 'agent' => $row->cupe_agent ];
			} else {
				return [ 'ip' => IPUtils::formatHex( $row->cule_ip_hex ), 'agent' => $row->cule_agent ];
			}
		}
	}

}

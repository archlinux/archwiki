<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;

class CheckUserLookupUtils {

	public const CONSTRUCTOR_OPTIONS = [ 'CheckUserCIDRLimit' ];

	private ServiceOptions $options;
	private IReadableDatabase $dbr;
	private RevisionLookup $revisionLookup;
	private ArchivedRevisionLookup $archivedRevisionLookup;
	private LoggerInterface $logger;

	public function __construct(
		ServiceOptions $options,
		IConnectionProvider $dbProvider,
		RevisionLookup $revisionLookup,
		ArchivedRevisionLookup $archivedRevisionLookup,
		LoggerInterface $logger
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->dbr = $dbProvider->getReplicaDatabase();
		$this->revisionLookup = $revisionLookup;
		$this->archivedRevisionLookup = $archivedRevisionLookup;
		$this->logger = $logger;
	}

	/**
	 * Returns whether the given target IP address or IP address range is valid. This applies the range limits
	 * imposed by $wgCheckUserCIDRLimit.
	 *
	 * @param string $target an IP address or CIDR range
	 * @return bool
	 */
	public function isValidIPOrRange( string $target ): bool {
		$CIDRLimit = $this->options->get( 'CheckUserCIDRLimit' );
		if ( IPUtils::isValidRange( $target ) ) {
			[ $ip, $range ] = explode( '/', $target, 2 );
			return (
				( IPUtils::isIPv4( $ip ) && $range >= $CIDRLimit['IPv4'] ) ||
				( IPUtils::isIPv6( $ip ) && $range >= $CIDRLimit['IPv6'] )
			);
		}

		return IPUtils::isValid( $target );
	}

	/**
	 * Utility for getting the CIDR limits, e.g. for error messages. Avoids the need to pass the config to
	 * the caller.
	 *
	 * @return int[]
	 */
	public function getRangeLimit() {
		return $this->options->get( 'CheckUserCIDRLimit' );
	}

	/**
	 * Get the WHERE conditions as an IExpression object which can be used to filter results for a provided
	 * IP address / range and optionally for the XFF IP.
	 *
	 * @param string $target an IP address or CIDR range
	 * @param bool $xfor True if searching on XFF IPs by IP address / range
	 * @param string $table The table which will be used in the query these WHERE conditions
	 * are used (array of valid options in self::RESULT_TABLES).
	 * @return IExpression|null IExpression for valid conditions, null if invalid
	 */
	public function getIPTargetExpr( string $target, bool $xfor, string $table ): ?IExpression {
		$columnName = $this->getIpHexColumn( $xfor, $table );
		return $this->getIPTargetExprForColumn( $target, $columnName );
	}

	/**
	 * Get the WHERE conditions as an IExpression object which can be used to filter results for a provided
	 * IP address / range, given a database column name.
	 *
	 * @param string $target an IP address or CIDR range
	 * @param string $columnName The column which will be used in the query these WHERE conditions
	 * are used (must be a database column holding an IP address in hex format).
	 * @return IExpression|null IExpression for valid conditions, null if invalid
	 */
	public function getIPTargetExprForColumn( string $target, string $columnName ): ?IExpression {
		if ( !$this->isValidIPOrRange( $target ) ) {
			// Return null if the target is not a valid IP address or range
			return null;
		}

		if ( IPUtils::isValidRange( $target ) ) {
			// If the target is a range, then the conditions should include all rows where the IP hex
			// is between the start and end (inclusive).
			[ $start, $end ] = IPUtils::parseRange( $target );
			return $this->dbr->expr( $columnName, '>=', $start )->and( $columnName, '<=', $end );
		} else {
			// If the target is a single IP, then the ip hex column should be equal to the hex of the target IP.
			return $this->dbr->expr( $columnName, '=', IPUtils::toHex( $target ) );
		}
	}

	/**
	 * Gets the column name for the IP hex column based
	 * on the value for $xfor and a given $table.
	 *
	 * @param bool $xfor Whether the IPs being searched through are XFF IPs.
	 * @param string $table The table selecting results from (array of valid
	 * options in CheckUserQueryInterface::RESULT_TABLES).
	 * @return string
	 */
	private function getIpHexColumn( bool $xfor, string $table ): string {
		$type = $xfor ? 'xff' : 'ip';
		return CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . $type . '_hex';
	}

	/**
	 * Gets the name for the index for a given table.
	 *
	 * @param bool|null $xfor Whether the IPs being searched through are XFF IPs. Null if the target is a username.
	 * @param string $table The table this index should apply to (list of valid options
	 *   in CheckUserQueryInterface::RESULT_TABLES).
	 * @return string
	 */
	public function getIndexName( ?bool $xfor, string $table ): string {
		// So that a code search can find existing usages:
		// cuc_actor_ip_time, cule_actor_ip_time, cupe_actor_ip_time, cuc_xff_hex_time, cuc_ip_hex_time,
		// cule_xff_hex_time, cule_ip_hex_time, cupe_xff_hex_time, cupe_ip_hex_time
		if ( $xfor === null ) {
			return CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . 'actor_ip_time';
		} else {
			$type = $xfor ? 'xff' : 'ip';
			return CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . $type . '_hex_time';
		}
	}

	/**
	 * Get a ManualLogEntry instance for the given row from either cu_log_event or
	 * cu_private_event table. The column names are expected to not include the table prefix and the row should include
	 * the following columns for this method to work:
	 * - log_type
	 * - log_action
	 * - log_params
	 * - log_deleted
	 * - title (or page/page_id)
	 * - namespace (not needed if title is not defined but page/page_id is)
	 * - timestamp
	 *
	 * Do not call this method for rows from cu_changes.
	 *
	 * @param stdClass $row The database row
	 * @param UserIdentity $user The user who is the performer for this row.
	 * @return ManualLogEntry Which can be used via the LogFormatter to generate action text.
	 */
	public function getManualLogEntryFromRow( stdClass $row, UserIdentity $user ): ManualLogEntry {
		$logEntry = new ManualLogEntry( $row->log_type, $row->log_action );
		if ( $row->log_params !== null ) {
			// Suppress E_NOTICE from PHP's unserialize if the log parameters are legacy parameters.
			// This is similar to DatabaseLogEntry::getParameters.
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$parsedLogParams = @ManualLogEntry::extractParams( $row->log_params );
			if ( $parsedLogParams === false ) {
				// Use the LogPage::extractParams method to extract the log parameters as they are probably
				// legacy parameters.
				$parsedLogParams = LogPage::extractParams( $row->log_params );
				$logEntry->setLegacy( true );
			}
			$logEntry->setParameters( $parsedLogParams );
		}
		$logEntry->setPerformer( $user );
		if ( isset( $row->title ) && $row->title ) {
			$logEntry->setTarget( Title::makeTitle( $row->namespace, $row->title ) );
		} elseif (
			// page_id is the column name for Special:CheckUser. page is the column name for the CheckUser API.
			( isset( $row->page ) && $row->page ) ||
			( isset( $row->page_id ) && $row->page_id )
		) {
			$logEntry->setTarget( Title::newFromID( $row->page ?? $row->page_id ) );
		}
		$logEntry->setTimestamp( $row->timestamp );
		$logEntry->setDeleted( $row->log_deleted );
		return $logEntry;
	}

	/**
	 * Get a RevisionRecord instance for the given row from cu_changes table. This should not be called for rows from
	 * the cu_log_event or cu_private_event tables.
	 *
	 * @param stdClass $row
	 * @return ?RevisionRecord null if no revision is found, otherwise the RevisionRecord.
	 */
	public function getRevisionRecordFromRow( stdClass $row ): ?RevisionRecord {
		$revRecord = $this->revisionLookup->getRevisionById( $row->this_oldid );
		if ( !$revRecord ) {
			$revRecord = $this->archivedRevisionLookup->getArchivedRevisionRecord( null, $row->this_oldid );
		}
		if ( $revRecord === null ) {
			// This shouldn't happen, CheckUser points to a revision that isn't in revision nor archive table?
			$this->logger->warning(
				"Couldn't fetch revision with this_oldid as {old_id}",
				[ 'old_id' => $row->this_oldid, 'exception' => new RuntimeException ]
			);
		}
		return $revRecord;
	}
}

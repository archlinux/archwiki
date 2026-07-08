<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\User\TempUser\TempUserConfig;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;

/**
 * Factory used for constructing query expressions for filtering records in
 * the abuse_filter_log table.
 *
 * @since 1.46
 */
class AbuseLogConditionFactory {

	public const SERVICE_NAME = ServiceNames::AbuseLogConditionFactory;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly TempUserConfig $tempUserConfig,
	) {
	}

	/**
	 * Returns an expression for filtering out log entries not associated with a
	 * given IP or IP range.
	 *
	 * The condition selects the following records:
	 *
	 * - Log entries associated with legacy IP actors whose IP is $address.
	 *   Please note that, if $address is an IP range, legacy IP actors whose
	 *   IPs are included within said range would NOT be included (that is,
	 *   range lookups of anonymous legacy IP users are not supported).
	 *
	 * - Log entries associated with temporary accounts using the IP provided in
	 *   $address or, if $address is an IP range, using an IP included within
	 *   said range.
	 *
	 * @param string $address IP address or range to filter by
	 */
	public function getUserFilterByIPAddress( string $address ): ?IExpression {
		// Lookup for log entries associated with temp accounts where the
		// IP matches the value provided (lookups for a single IP) or is
		// included in the range provided (lookups for an IP range).

		[ $rangeStart, $rangeEnd ] = IPUtils::parseRange( $address );

		if ( $rangeStart === false ) {
			// When the range is invalid, both $rangeStart and $rangeEnd
			// fill be false: Return early in that case
			return null;
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$tempAccountsMatchExpression = $this->tempUserConfig->getMatchCondition(
			$dbr,
			'afl_user_text',
			IExpression::LIKE
		);

		if ( $rangeStart === $rangeEnd ) {
			// If rangeStart is equal to rangeEnd, the target is actually an IP
			// hex: Return temporary accounts or anonymous users matching the IP
			$readableIPAddress = IPUtils::formatHex( $rangeStart );

			return $dbr->orExpr( [
				$dbr->expr( 'afl_ip_hex', '=', $rangeStart )
					->andExpr( $tempAccountsMatchExpression ),
				$dbr->expr( 'afl_user_text', '=', $readableIPAddress )
					->and( 'afl_user', '=', 0 )
			] );
		}

		// parseRange() return false for both values if the input is invalid,
		// both values set to the same IP it $address is a single IP, or
		// different values if the input was indeed a valid IP range.
		//
		// For the latter case, query for temporary accounts that used any IP in
		// that range, as range lookups of anonymous edits aren't supported.

		return $dbr
			->expr( 'afl_ip_hex', '>=', $rangeStart )
			->and( 'afl_ip_hex', '<=', $rangeEnd )
			->andExpr( $tempAccountsMatchExpression );
	}

	/**
	 * Returns a query condition for filtering out log entries not associated
	 * with the provided user.
	 *
	 * Note that both the ID and username are used: For local users, the caller
	 * needs to know the local user's ID; for external users, the caller should
	 * provide the user ID as zero.
	 *
	 * @param UserIdentity $userIdentity User to filter by
	 * @return array List of conditions
	 */
	public function getUserFilterByUserIdentity(
		UserIdentity $userIdentity
	): array {
		return [
			'afl_user' => $userIdentity->getId(),
			'afl_user_text' => $userIdentity->getName(),
		];
	}
}

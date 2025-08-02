<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use InvalidArgumentException;
use LogicException;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\ContributionsRangeTrait;
use MediaWiki\User\CentralId\CentralIdLookup;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;

class CheckUserGlobalContributionsLookup implements CheckUserQueryInterface {

	use ContributionsRangeTrait;

	private IConnectionProvider $dbProvider;
	private ExtensionRegistry $extensionRegistry;
	private CentralIdLookup $centralIdLookup;
	private CheckUserLookupUtils $checkUserLookupUtils;
	private Config $config;

	public function __construct(
		IConnectionProvider $dbProvider,
		ExtensionRegistry $extensionRegistry,
		CentralIdLookup $centralIdLookup,
		CheckUserLookupUtils $checkUserLookupUtils,
		Config $config
	) {
		$this->dbProvider = $dbProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->centralIdLookup = $centralIdLookup;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$this->config = $config;
	}

	/**
	 * Ensure CentralAuth is loaded
	 *
	 * @throws LogicException if CentralAuth is not loaded, as it's a dependency
	 */
	private function checkCentralAuthEnabled() {
		if ( !$this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			throw new LogicException(
				"CentralAuth authentication is needed but not available"
			);
		}
	}

	/**
	 * @param string $target
	 * @param Authority $authority
	 * @return string[]
	 */
	public function getActiveWikis( string $target, Authority $authority ) {
		$this->checkCentralAuthEnabled();

		$activeWikis = [];
		$cuciDb = $this->dbProvider->getReplicaDatabase( self::VIRTUAL_GLOBAL_DB_DOMAIN );

		if ( $this->isValidIPOrQueryableRange( $target, $this->config ) ) {
			$targetIPConditions = $this->checkUserLookupUtils->getIPTargetExprForColumn(
				$target,
				'cite_ip_hex'
			);
			if ( $targetIPConditions === null ) {
				// Invalid IPs are treated as usernames so we should only ever reach
				// this condition if the IP range is out of limits
				throw new LogicException(
					"Attempted IP range lookup with a range outside of the limit: $target\n
					Check if your RangeContributionsCIDRLimit and CheckUserCIDRLimit configs are compatible."
				);
			}
			$activeWikis = $cuciDb->newSelectQueryBuilder()
				->select( 'ciwm_wiki' )
				->from( 'cuci_temp_edit' )
				->distinct()
				->where( $targetIPConditions )
				->join( 'cuci_wiki_map', null, 'cite_ciwm_id = ciwm_id' )
				->orderBy( 'cite_timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		} else {
			$centralId = $this->centralIdLookup->centralIdFromName( $target, $authority );
			if ( !$centralId ) {
				throw new InvalidArgumentException( "No central id found for $target" );
			}
			$activeWikis = $cuciDb->newSelectQueryBuilder()
				->select( 'ciwm_wiki' )
				->from( 'cuci_user' )
				->distinct()
				->where( [ 'ciu_central_id' => $centralId ] )
				->join( 'cuci_wiki_map', null, 'ciu_ciwm_id = ciwm_id' )
				->orderBy( 'ciu_timestamp', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchFieldValues();
		}

		return $activeWikis;
	}
}

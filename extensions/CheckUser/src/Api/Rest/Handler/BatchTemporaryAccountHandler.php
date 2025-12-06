<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\Jobs\LogTemporaryAccountAccessJob;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserExpiredIdsLookupService;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Config\Config;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Rest\Response;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\ReadOnlyMode;

class BatchTemporaryAccountHandler extends AbstractTemporaryAccountHandler {

	use TemporaryAccountNameTrait;
	use TemporaryAccountRevisionTrait;
	use TemporaryAccountLogTrait;

	private RevisionStore $revisionStore;
	private CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup;
	private TemporaryAccountLoggerFactory $loggerFactory;

	public function __construct(
		Config $config,
		JobQueueGroup $jobQueueGroup,
		PermissionManager $permissionManager,
		UserNameUtils $userNameUtils,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore,
		BlockManager $blockManager,
		RevisionStore $revisionStore,
		CheckUserPermissionManager $checkUserPermissionsManager,
		CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup,
		TemporaryAccountLoggerFactory $loggerFactory,
		ReadOnlyMode $readOnlyMode,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly CheckUserExpiredIdsLookupService $expiredIdsLookupService
	) {
		parent::__construct(
			$config,
			$jobQueueGroup,
			$permissionManager,
			$userNameUtils,
			$dbProvider,
			$actorStore,
			$blockManager,
			$checkUserPermissionsManager,
			$readOnlyMode
		);
		$this->revisionStore = $revisionStore;
		$this->autoRevealLookup = $autoRevealLookup;
		$this->loggerFactory = $loggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function run( $identifier = null ): Response {
		return parent::run( $identifier );
	}

	/**
	 * @inheritDoc
	 */
	public function makeLog( $identifier ) {
		$users = $this->getValidatedBody()['users'] ?? [];

		if ( $this->autoRevealLookup->isAutoRevealOn( $this->getAuthority() ) ) {
			$logger = $this->loggerFactory->getLogger();
			$performerName = $this->getAuthority()->getUser()->getName();

			foreach ( $users as $username => $params ) {
				$logger->logViewIPsWithAutoReveal(
					$performerName,
					$username
				);
			}
			return;
		}

		foreach ( $users as $username => $params ) {
			$this->jobQueueGroup->push(
				LogTemporaryAccountAccessJob::newSpec(
					$this->getAuthority()->getUser(),
					$username,
					$this->getLogType()
				)
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getResults( $identifier ): array {
		$results = [];

		$dbr = $this->getConnectionProvider()->getReplicaDatabase();
		$body = $this->getValidatedBody();

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$body = $this->filterAbuseFilterLogIds( $body );
		}

		foreach ( $body['users'] ?? [] as $username => $params ) {
			$identifier = [
				'actorId' => $this->getTemporaryAccountActorId( $username ),
				'revIds' => $params['revIds'] ?? [],
				'logIds' => $params['logIds'] ?? [],
				'lastUsedIp' => $params['lastUsedIp'] ?? false,
			];
			if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
				$identifier['abuseLogIds'] = $params['abuseLogIds'] ?? [];
			}
			$results[$username] = $this->getData( $identifier, $dbr );
		}

		if ( $this->autoRevealLookup->isAutoRevealAvailable() ) {
			$results['autoReveal'] = $this->autoRevealLookup->isAutoRevealOn(
				$this->getAuthority()
			);
		}

		return $results;
	}

	/**
	 * Given an array containing several lists of identifiers, returns an
	 * associative array whose keys are types of identifiers holding information
	 * for each type of identifier, as follows:
	 *
	 * [
	 *   'revIps' => (IP data), // (object) [ 1 => '1.2.3.4', 2 => NULL, ... ]
	 *   'logIps' => (IP data), // (object) [ 1 => '1.2.3.4', 2 => NULL, ... ]
	 *   'lastUsedIp' => '1.2.3.4'
	 * ]
	 *
	 * 'lastUsedIp' contains the last used IP for the actor ID provided in the
	 * input data.
	 *
	 * "(IP data)" above is either NULL if no IDs have been requested for a
	 * given type, or an object where each key is an identifier having the IP
	 * associated with that identifier as its value. For identifiers where the
	 * IP has expired, the IP is returned as NULL; for identifiers whose data is
	 * not available, the entry for the identifier itself is removed altogether
	 * from the "(IP data)" object.
	 *
	 * The input data in $identifier is an associative array with keys named
	 * 'actorId', 'revIds', 'logIds' & 'lastUsedIp' where:
	 *
	 * - lastUsedIp is a boolean determining whether the 'lastUsedIp' component
	 *   in the response should be proved (which will be null if lastUsedIp in
	 *   $identifier is false).
	 * - actorId contains an Actor ID, used to populate the lastUsedIp component
	 *   as described above if available.
	 * - revIds contains an array of revision IDs, which should be associated
	 *   with the actorId described above.
	 * - logIds contains an array of log IDs, which should be associated with
	 *   the actorId described above.
	 * - If the Abuse Filter extension is installed, abuseLogIds contains an
	 *   array of AbuseLog IDs.
	 *
	 * @param array<string,mixed> $identifier Lists of IDs.
	 * @param IReadableDatabase $dbr Connection used to fetch data from.
	 */
	protected function getData( $identifier, IReadableDatabase $dbr ): array {
		[ 'actorId' => $actorId, 'revIds' => $revIds, 'logIds' => $logIds, 'lastUsedIp' => $lastUsedIp ] = $identifier;

		$data = [
			'revIps' => null,
			'logIps' => null,
			'lastUsedIp' => $lastUsedIp
				? ( $this->getActorIps( $actorId, 1, $dbr )[0] ?? null )
				: null,
		];

		if ( count( $revIds ) > 0 ) {
			$revIPs = $this->getRevisionsIps( $actorId, $revIds, $dbr );
			$data[ 'revIps' ] = $this->formatIPMap(
				$revIPs,
				$revIds,
				$this->expiredIdsLookupService->listExpiredRevisionIdsInSet(
					$this->listMissingIDs( $revIPs, $revIds )
				)
			);
		}

		if ( count( $logIds ) > 0 ) {
			$logIPs = $this->getLogIps( $actorId, $logIds, $dbr );
			$data[ 'logIps' ] = $this->formatIPMap(
				$logIPs,
				$logIds,
				$this->expiredIdsLookupService->listExpiredLogIdsInSet(
					$this->listMissingIDs( $logIPs, $logIds )
				)
			);
		}

		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$data['abuseLogIps'] = null;

			if ( count( $identifier['abuseLogIds'] ) > 0 ) {
				$afLogIdentifiers = $identifier['abuseLogIds'];
				$afIPs = $this->getAbuseFilterLogIPs( $afLogIdentifiers );
				$data[ 'abuseLogIps' ] = $this->formatIPMap(
					$afIPs,
					$afLogIdentifiers,
					$this->expiredIdsLookupService->listExpiredAbuseLogIdsInSet(
						$this->listMissingIDs( $afIPs, $afLogIdentifiers )
					)
				);
			}
		}

		return $data;
	}

	/**
	 * Returns the IPs associated with a given set of abuse_filter_log IDs.
	 *
	 * This method assumes that the AbuseFilter extension is installed.
	 */
	private function getAbuseFilterLogIPs( array $abuseFilterLogIds ): array {
		if ( count( $abuseFilterLogIds ) === 0 ) {
			return [];
		}

		$abuseFilterPrivateLogDetailsLookup = AbuseFilterServices::getLogDetailsLookup();
		$abuseLogIps = $abuseFilterPrivateLogDetailsLookup->getIPsForAbuseFilterLogs(
			$this->getAuthority(), $abuseFilterLogIds
		);

		// Remove any IPs which are false (meaning the user could not see the abuse_filter_log row) and then
		// return the list.
		return array_filter( $abuseLogIps, static fn ( $value ) => $value !== false );
	}

	/**
	 * Filters out afl_id values from the request body that were not performed by the username that they
	 * were associated with in the request body.
	 *
	 * This is done to ensure that the IP reveal logs correctly indicate all the users that were revealed.
	 * Otherwise a user could specify an afl_id for a different temporary account and avoid creating a log.
	 *
	 * @param array $body The data returned by {@link self::getValidatedBody}
	 * @return array The data to use as the value of {@link self::getValidatedBody} from now on
	 */
	private function filterAbuseFilterLogIds( array $body ): array {
		$abuseLogIds = [];
		foreach ( $body['users'] ?? [] as $params ) {
			$abuseLogIds = array_merge( $params['abuseLogIds'] ?? [], $abuseLogIds );
		}

		if ( count( $abuseLogIds ) !== 0 ) {
			$abuseFilterPrivateLogDetailsLookup = AbuseFilterServices::getLogDetailsLookup();
			$groupedAbuseFilterLogIds = $abuseFilterPrivateLogDetailsLookup->groupAbuseFilterLogIdsByPerformer(
				$abuseLogIds
			);

			foreach ( $body['users'] as $username => &$params ) {
				$params['abuseLogIds'] = $groupedAbuseFilterLogIds[$username] ?? [];
			}
		}

		return $body;
	}

	/**
	 * Given a map of IDs to IPs:
	 *
	 * - Removes entries for IDs that have expired.
	 * - Adds NULLs for IDs that were requested, not expired and either not
	 *   present in the map or having an empty value (i.e. an empty string).
	 *
	 * @param array<string|int,string|int> $ipMap Map of IDs to IPs.
	 * @param array<string|int> $requestedIds List of requested IDs.
	 * @param array<string|int> $expiredIds List of expired IDs.
	 *
	 * @return array<string|int,string|int|null> The updated map.
	 */
	private function formatIPMap(
		array $ipMap,
		array $requestedIds,
		array $expiredIds
	): array {
		foreach ( $expiredIds as $expiredId ) {
			unset( $ipMap[ (string)$expiredId ] );
		}

		$nonExpiredIds = array_diff( $requestedIds, $expiredIds );
		foreach ( $nonExpiredIds as $nonExpiredId ) {
			// Testing also for an empty string due to getIPsForAbuseFilterLogs()
			// returning that when the IP has been deleted.
			if ( !isset( $ipMap[ $nonExpiredId ] ) || $ipMap[ $nonExpiredId ] === '' ) {
				$ipMap[ $nonExpiredId ] = null;
			}
		}

		return $ipMap;
	}

	/**
	 * Given a mapping of IDs to arbitrary data and a list of IDs, constructs a
	 * new list with the IDs from the second list after removing the IDs present
	 * in the mapping pointing to non-empty values.
	 *
	 * Ths method is used to remove the IDs from the list of all IDs that are
	 * already present in the data array.
	 *
	 * @param array<string|int, string|int> $data
	 * @param array<string|int> $allIds
	 *
	 * @return array<string|int>
	 */
	private function listMissingIDs( array $data, array $allIds ): array {
		return array_diff(
			$allIds,
			array_keys( array_filter( $data ) )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getBodyParamSettings(): array {
		$optionalUserProperties = [];
		if ( $this->extensionRegistry->isLoaded( 'Abuse Filter' ) ) {
			$optionalUserProperties['abuseLogIds'] = ArrayDef::makeListSchema( 'string' );
		}

		return [
			'users' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				ArrayDef::PARAM_SCHEMA => ArrayDef::makeMapSchema( ArrayDef::makeObjectSchema(
					[
						'revIds' => ArrayDef::makeListSchema( 'string' ),
						'logIds' => ArrayDef::makeListSchema( 'string' ),
						'lastUsedIp' => 'boolean',
					],
					$optionalUserProperties
				) ),
			],
		] + parent::getBodyParamSettings();
	}

	/**
	 * @inheritDoc
	 */
	protected function getLogType(): string {
		return TemporaryAccountLogger::ACTION_VIEW_IPS;
	}

	/**
	 * @inheritDoc
	 */
	protected function getActorStore(): ActorStore {
		return $this->actorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function getAutoRevealLookup(): CheckUserTemporaryAccountAutoRevealLookup {
		return $this->autoRevealLookup;
	}

	/**
	 * @inheritDoc
	 */
	protected function getBlockManager(): BlockManager {
		return $this->blockManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getConnectionProvider(): IConnectionProvider {
		return $this->dbProvider;
	}

	/**
	 * @inheritDoc
	 */
	protected function getPermissionManager(): PermissionManager {
		return $this->permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	protected function getRevisionStore(): RevisionStore {
		return $this->revisionStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getUserNameUtils(): UserNameUtils {
		return $this->userNameUtils;
	}
}

<?php

namespace MediaWiki\CheckUser\Api\CheckUser;

use InvalidArgumentException;
use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\Config;
use MediaWiki\User\UserNameUtils;
use MessageLocalizer;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

abstract class ApiQueryCheckUserAbstractResponse implements CheckUserQueryInterface {

	protected ApiQueryCheckUser $module;
	/** @var string The target of the check */
	protected string $target;
	/** @var string The reason provided for the check. This wrapped in the checkuser-reason-api message. */
	protected string $reason;
	/** @var int The maximum number of results to return */
	protected int $limit;
	/**
	 * @var bool|null Null if the target is a username, true if the target is an IP,
	 *   false if the target is an XFF IP.
	 */
	protected ?bool $xff;
	/** @var string The cut-off timestamp in a format acceptable to the database */
	protected string $timeCutoff;

	protected IReadableDatabase $dbr;
	protected Config $config;
	protected CheckUserLogService $checkUserLogService;
	protected CheckUserLookupUtils $checkUserLookupUtils;

	/**
	 * @param ApiQueryCheckUser $module
	 * @param IConnectionProvider $dbProvider
	 * @param Config $config
	 * @param MessageLocalizer $messageLocalizer
	 * @param CheckUserLogService $checkUserLogService
	 * @param UserNameUtils $userNameUtils
	 * @param CheckUserLookupUtils $checkUserLookupUtils
	 *
	 * @internal Use CheckUserApiResponseFactory::newFromRequest() instead
	 */
	public function __construct(
		ApiQueryCheckUser $module,
		IConnectionProvider $dbProvider,
		Config $config,
		MessageLocalizer $messageLocalizer,
		CheckUserLogService $checkUserLogService,
		UserNameUtils $userNameUtils,
		CheckUserLookupUtils $checkUserLookupUtils
	) {
		$this->dbr = $dbProvider->getReplicaDatabase();
		$this->config = $config;
		$this->checkUserLogService = $checkUserLogService;
		$this->checkUserLookupUtils = $checkUserLookupUtils;
		$requestParams = $module->extractRequestParams();

		// Validate that a non-empty reason was provided if the force summary configuration is enabled.
		$reason = trim( $requestParams['reason'] );
		if ( $this->config->get( 'CheckUserForceSummary' ) && $reason === '' ) {
			$module->dieWithError( 'apierror-checkuser-missingsummary', 'missingdata' );
		}

		// Wrap the reason in the checkuser-reason-api message, which adds the indication that the check was made using
		// the CheckUser API.
		$reason = $messageLocalizer->msg( 'checkuser-reason-api', $reason )->inContentLanguage()->escaped();

		// Parse the timecond parameter and validate that it produces a valid timestamp.
		$timeCutoff = strtotime( $requestParams['timecond'], ConvertibleTimestamp::time() );
		if ( !$timeCutoff || $timeCutoff < 0 || $timeCutoff > ConvertibleTimestamp::time() ) {
			$module->dieWithError( 'apierror-checkuser-timelimit', 'invalidtime' );
		}

		// Normalise the target parameter.
		$target = $requestParams['target'] ?? '';
		if ( IPUtils::isValid( $target ) ) {
			$target = IPUtils::sanitizeIP( $target ) ?? '';
		} elseif ( IPUtils::isValidRange( $target ) ) {
			$target = IPUtils::sanitizeRange( $target );
		} else {
			// Convert the username to a canonical form. Don't try to validate that the user exists as some response
			// classes may only expect a username (and as such we need to leave the validation to the classes which
			// extend this).
			$target = $userNameUtils->getCanonical( $target );
			if ( $target === false ) {
				$target = '';
			}
		}

		if ( IPUtils::isIPAddress( $target ) ) {
			// If the xff parameter was provided, then the target is an XFF IP. Otherwise, the target is an IP.
			$this->xff = isset( $requestParams['xff'] );
		} else {
			// If the target is not an IP, then the XFF parameter is not applicable (and therefore is null).
			$this->xff = null;
		}

		$this->module = $module;
		$this->target = $target;
		$this->reason = $reason;
		$this->timeCutoff = $this->dbr->timestamp( $timeCutoff );
		$this->limit = $requestParams['limit'];
	}

	/**
	 * Generate the response array for the given request.
	 *
	 * @return array
	 */
	abstract public function getResponseData(): array;

	/**
	 * Return the type of request that this response is for, which is the value of the
	 * 'curequest' parameter provided to the API.
	 *
	 * @return string
	 */
	abstract public function getRequestType(): string;

	/**
	 * Perform the database queries needed to generate the rows which then used to generate the response.
	 *
	 * @param string $fname The name of the calling function, for logging purposes.
	 *
	 * @return IResultWrapper
	 */
	protected function performQuery( string $fname ) {
		// Run the SQL queries to select results from the result tables and merge the results into one array.
		$results = [];
		foreach ( self::RESULT_TABLES as $table ) {
			$results = array_merge(
				$results,
				iterator_to_array(
					$this->getQueryBuilderForTable( $table )
						->caller( $fname )
						->fetchResultSet()
				)
			);
		}
		// Order the results by the timestamp column descending.
		usort( $results, static function ( $a, $b ) {
			return $b->timestamp <=> $a->timestamp;
		} );
		// Apply the limit to the results.
		$results = array_slice( $results, 0, $this->limit );

		// Return the generated data as a FakeResultWrapper.
		return new FakeResultWrapper( $results );
	}

	/**
	 * Gets a SelectQueryBuilder that can be used to select rows from the given $table.
	 *
	 * This method should also validate the target and die with an appropriate error if the target is
	 * not valid for this request.
	 *
	 * @param string $table One of the tables in CheckUserQueryInterface::RESULT_TABLES.
	 */
	protected function getQueryBuilderForTable( string $table ): SelectQueryBuilder {
		// Get the query builder that is specific to the table.
		if ( $table === self::CHANGES_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuChanges();
		} elseif ( $table === self::LOG_EVENT_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuLogEvent();
		} elseif ( $table === self::PRIVATE_LOG_EVENT_TABLE ) {
			$queryBuilder = $this->getPartialQueryBuilderForCuPrivateEvent();
		} else {
			throw new InvalidArgumentException( "Unknown table: $table" );
		}
		// Add the target conditions to the query builder, as well as other table-independent information
		// and then return the SelectQueryBuilder.
		return $queryBuilder
			->andWhere( $this->validateTargetAndGenerateTargetConditions( $table ) )
			->useIndex( [ $table => $this->checkUserLookupUtils->getIndexName( $this->xff, $table ) ] )
			->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( $this->limit + 1 );
	}

	/**
	 * Validate that the target in $this->target is a valid target for this check type.
	 *
	 * If the target is valid then return an IExpression that can be used to select rows that are performed
	 * by this target. If the target is not valid then throw an exception with an appropriate error message.
	 *
	 * @param string $table If the target is valid, generate the conditions specific to this table.
	 * @return IExpression
	 */
	abstract protected function validateTargetAndGenerateTargetConditions( string $table ): IExpression;

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_changes for results. This must be
	 * extended with table independent query information (such as the WHERE conditions for the target) by
	 * ::getQueryBuilderForTable and as such must only be called by that method.
	 *
	 * This method should not add the WHERE conditions for the target, as this will be handled by
	 * ::getQueryBuilderForTable.
	 *
	 * @return SelectQueryBuilder The query builder specific to cu_changes
	 */
	abstract protected function getPartialQueryBuilderForCuChanges(): SelectQueryBuilder;

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_log_event for results. This must be
	 * extended with table independent query information (such as the WHERE conditions for the target) by
	 * ::getQueryBuilderForTable and as such must only be called by that method.
	 *
	 * This method should not add the WHERE conditions for the target, as this will be handled by
	 * ::getQueryBuilderForTable.
	 *
	 * @return SelectQueryBuilder The query builder specific to cu_log_event
	 */
	abstract protected function getPartialQueryBuilderForCuLogEvent(): SelectQueryBuilder;

	/**
	 * Get the SelectQueryBuilder which can be used to query cu_private_event for results. This must be
	 * extended with table independent query information (such as the WHERE conditions for the target) by
	 * ::getQueryBuilderForTable and as such must only be called by that method.
	 *
	 * This method should not add the WHERE conditions for the target, as this will be handled by
	 * ::getQueryBuilderForTable.
	 *
	 * @return SelectQueryBuilder The query builder specific to cu_private_event
	 */
	abstract protected function getPartialQueryBuilderForCuPrivateEvent(): SelectQueryBuilder;
}

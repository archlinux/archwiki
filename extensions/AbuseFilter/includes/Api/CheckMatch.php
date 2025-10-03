<?php

namespace MediaWiki\Extension\AbuseFilter\Api;

use LogicException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Extension\AbuseFilter\Variables\LazyLoadedVariable;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Json\FormatJson;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Logging\LogPage;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Revision\RevisionRecord;
use Wikimedia\ParamValidator\ParamValidator;

class CheckMatch extends ApiBase {

	private RuleCheckerFactory $ruleCheckerFactory;
	private AbuseFilterPermissionManager $afPermManager;
	private VariablesBlobStore $afVariablesBlobStore;
	private VariableGeneratorFactory $afVariableGeneratorFactory;
	private FilterLookup $filterLookup;
	private AbuseLoggerFactory $abuseLoggerFactory;

	public function __construct(
		ApiMain $main,
		string $action,
		RuleCheckerFactory $ruleCheckerFactory,
		AbuseFilterPermissionManager $afPermManager,
		VariablesBlobStore $afVariablesBlobStore,
		VariableGeneratorFactory $afVariableGeneratorFactory,
		FilterLookup $filterLookup,
		AbuseLoggerFactory $abuseLoggerFactory
	) {
		parent::__construct( $main, $action );
		$this->ruleCheckerFactory = $ruleCheckerFactory;
		$this->afPermManager = $afPermManager;
		$this->afVariablesBlobStore = $afVariablesBlobStore;
		$this->afVariableGeneratorFactory = $afVariableGeneratorFactory;
		$this->filterLookup = $filterLookup;
		$this->abuseLoggerFactory = $abuseLoggerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$performer = $this->getAuthority();
		$params = $this->extractRequestParams();
		$this->requireOnlyOneParameter( $params, 'vars', 'rcid', 'logid' );

		// "Anti-DoS"
		if ( !$this->afPermManager->canUseTestTools( $performer ) ) {
			$this->dieWithError( 'apierror-abusefilter-canttest', 'permissiondenied' );
		}

		$vars = null;
		if ( $params['vars'] ) {
			$pairs = FormatJson::decode( $params['vars'], true );
			$vars = VariableHolder::newFromArray( $pairs );
		} elseif ( $params['rcid'] ) {
			$rc = RecentChange::newFromId( $params['rcid'] );

			if ( !$rc ) {
				$this->dieWithError( [ 'apierror-nosuchrcid', $params['rcid'] ] );
			}

			$type = (int)$rc->getAttribute( 'rc_type' );
			$deletedValue = $rc->getAttribute( 'rc_deleted' );
			if (
				(
					$type === RC_LOG &&
					!LogEventsList::userCanBitfield(
						$deletedValue,
						LogPage::SUPPRESSED_ACTION | LogPage::SUPPRESSED_USER,
						$performer
					)
				) || (
					$type !== RC_LOG &&
					!RevisionRecord::userCanBitfield( $deletedValue, RevisionRecord::SUPPRESSED_ALL, $performer )
				)
			) {
				// T223654 - Same check as in AbuseFilterChangesList
				$this->dieWithError( 'apierror-permissiondenied-generic', 'deletedrc' );
			}

			$varGenerator = $this->afVariableGeneratorFactory->newRCGenerator( $rc, $this->getUser() );
			$vars = $varGenerator->getVars();
			if ( $vars === null ) {
				$this->dieWithError( 'apierror-abusefilter-incompatible' );
			}
		} elseif ( $params['logid'] ) {
			$row = $this->getDB()->newSelectQueryBuilder()
				->select( '*' )
				->from( 'abuse_filter_log' )
				->where( [ 'afl_id' => $params['logid'] ] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$row ) {
				$this->dieWithError( [ 'apierror-abusefilter-nosuchlogid', $params['logid'] ], 'nosuchlogid' );
			}

			$filter = $this->filterLookup->getFilter( $row->afl_filter_id, $row->afl_global );
			$canSeeDetails = $this->afPermManager->canSeeLogDetailsForFilter( $performer, $filter );
			if ( !$canSeeDetails ) {
				$this->dieWithError( 'apierror-permissiondenied-generic', 'cannotseedetails' );
			}

			$visibility = SpecialAbuseLog::getEntryVisibilityForUser( $row, $performer, $this->afPermManager );
			if ( $visibility !== SpecialAbuseLog::VISIBILITY_VISIBLE ) {
				// T223654 - Same check as in SpecialAbuseLog. Both the visibility of the AbuseLog entry
				// and the corresponding revision are checked.
				$this->dieWithError( 'apierror-permissiondenied-generic', 'deletedabuselog' );
			}

			$vars = $this->afVariablesBlobStore->loadVarDump( $row );

			// Check that the user can see all the protected filters in the abuse_filter_log log.
			if ( $filter->isProtected() ) {
				$permStatus = $this->afPermManager->canViewProtectedVariables(
					$this->getAuthority(), array_keys( $vars->getVars() )
				);
				if ( !$permStatus->isGood() ) {
					$this->dieWithError( 'apierror-permissiondenied-generic', 'cannotseedetails' );
				}
			}
		}
		if ( $vars === null ) {
			// @codeCoverageIgnoreStart
			throw new LogicException( 'Variables were not loaded, this should not happen.' );
			// @codeCoverageIgnoreEnd
		}

		$ruleChecker = $this->ruleCheckerFactory->newRuleChecker( $vars );
		if ( !$ruleChecker->checkSyntax( $params['filter'] )->isValid() ) {
			$this->dieWithError( 'apierror-abusefilter-badsyntax', 'badsyntax' );
		}

		// Check if the provided pattern uses protected variables. If it does, then refuse to check the pattern
		// if the user cannot see the used protected variables. This prevents matching against protected variables
		// generated when providing 'rcid' and is a fail-safe in the same way for 'logid'.
		$usedVars = $ruleChecker->getUsedVars( $params['filter'] );
		if ( $this->afPermManager->getForbiddenVariables( $this->getAuthority(), $usedVars ) ) {
			$this->dieWithError( 'apierror-permissiondenied-generic', 'cannotseeprotectedvariables' );
		}

		$result = [
			ApiResult::META_BC_BOOLS => [ 'result' ],
			'result' => $ruleChecker->checkConditions( $params['filter'] )->getResult(),
		];

		// If the test filter pattern contains protected variables and this entry had a value set for the
		// protected variables that were in the pattern, then log that protected variables were accessed.
		// This is to avoid a user being able to know the value of the variable if they repeatedly try values to
		// find the actual value through trial-and-error.
		$protectedVariableValuesShown = [];
		foreach ( $this->afPermManager->getUsedProtectedVariables( $usedVars ) as $protectedVariable ) {
			if ( $vars->varIsSet( $protectedVariable ) ) {
				$protectedVariableValue = $vars->getVarThrow( $protectedVariable );
				if ( !( $protectedVariableValue instanceof LazyLoadedVariable ) && $protectedVariableValue !== null ) {
					$protectedVariableValuesShown[] = $protectedVariable;
				}
			}
		}

		if ( count( $protectedVariableValuesShown ) ) {
			// Either 'user_name' or 'accountname' should be set which are not lazily loaded, so get one of
			// them to use as the target
			if ( $vars->varIsSet( 'user_name' ) ) {
				$target = $vars->getComputedVariable( 'user_name' )->toNative();
			} else {
				$target = $vars->getComputedVariable( 'accountname' )->toNative();
			}
			$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
			$logger->logViewProtectedVariableValue( $this->getUser(), $target, $protectedVariableValuesShown );
		}

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$result
		);
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'filter' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'vars' => null,
			'rcid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'logid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
		];
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=abusefiltercheckmatch&filter=!("autoconfirmed"%20in%20user_groups)&rcid=15'
				=> 'apihelp-abusefiltercheckmatch-example-1',
		];
	}
}

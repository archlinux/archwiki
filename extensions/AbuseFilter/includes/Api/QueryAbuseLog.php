<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

namespace MediaWiki\Extension\AbuseFilter\Api;

use InvalidArgumentException;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseLogConditionFactory;
use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\Filter\FilterNotFoundException;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Extension\AbuseFilter\TemporaryAccountIPsViewerSpecification;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\IPUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\ReadOnlyMode;

/**
 * Query module to list abuse log entries.
 *
 * @copyright 2009 Alex Z. <mrzmanwiki AT gmail DOT com>
 * Based mostly on code by Bryan Tong Minh and Roan Kattouw
 *
 * @ingroup API
 * @ingroup Extensions
 */
class QueryAbuseLog extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly FilterLookup $afFilterLookup,
		private readonly AbuseFilterPermissionManager $afPermManager,
		private readonly VariablesBlobStore $afVariablesBlobStore,
		private readonly VariablesManager $afVariablesManager,
		private readonly UserFactory $userFactory,
		private readonly AbuseLoggerFactory $abuseLoggerFactory,
		private readonly RuleCheckerFactory $ruleCheckerFactory,
		private readonly AbuseLogConditionFactory $abuseLogConditionFactory,
		private readonly TemporaryAccountIPsViewerSpecification $tempAccountIPsViewerSpecification,
		private readonly ReadOnlyMode $readOnlyMode,
	) {
		parent::__construct( $query, $moduleName, 'afl' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$lookup = $this->afFilterLookup;

		// Same check as in SpecialAbuseLog
		$this->checkUserRightsAny( 'abusefilter-log' );

		$performer = $this->getAuthority();
		$params = $this->extractRequestParams();

		$prop = array_fill_keys( $params['prop'], true );
		$fld_ids = isset( $prop['ids'] );
		$fld_filter = isset( $prop['filter'] );
		$fld_user = isset( $prop['user'] );
		$fld_title = isset( $prop['title'] );
		$fld_action = isset( $prop['action'] );
		$fld_details = isset( $prop['details'] );
		$fld_result = isset( $prop['result'] );
		$fld_timestamp = isset( $prop['timestamp'] );
		$fld_hidden = isset( $prop['hidden'] );
		$fld_revid = isset( $prop['revid'] );
		$isCentral = $this->getConfig()->get( 'AbuseFilterIsCentral' );
		$fld_wiki = $isCentral && isset( $prop['wiki'] );

		if ( $fld_details ) {
			$this->checkUserRightsAny( 'abusefilter-log-detail' );
		}

		$canViewPrivate = $this->afPermManager->canViewPrivateFiltersLogs( $performer );
		$canViewSuppressed = $this->afPermManager->canViewSuppressed( $performer );

		// Map of [ [ id, global ], ... ]
		$searchFilters = [];
		// Match permissions for viewing events on private filters to SpecialAbuseLog (bug 42814)
		// @todo Avoid code duplication with SpecialAbuseLog::showList, make it so that, if hidden
		// filters are specified, we only filter them out instead of failing.
		if ( $params['filter'] ) {
			if ( !is_array( $params['filter'] ) ) {
				$params['filter'] = [ $params['filter'] ];
			}
			$foundInvalid = false;
			foreach ( $params['filter'] as $filter ) {
				try {
					$searchFilters[] = GlobalNameUtils::splitGlobalName( $filter );
				} catch ( InvalidArgumentException ) {
					$foundInvalid = true;
					continue;
				}
			}

			foreach ( $searchFilters as [ $filterID, $global ] ) {
				try {
					$filter = $lookup->getFilter( $filterID, $global );
					$ruleChecker = $this->ruleCheckerFactory->newRuleChecker();
					$usedVariables = $ruleChecker->getUsedVars( $filter->getRules() );
				} catch ( CentralDBNotAvailableException ) {
					// Conservatively assume that it's suppressed, hidden and protected,
					// like in AbuseLogPager::doFormatRow.
					// Also assume that the filter contains all protected variables for the same reasons.
					$filter = MutableFilter::newDefault();
					$filter->setHidden( true );
					$filter->setProtected( true );
					$filter->setSuppressed( true );
					$usedVariables = $this->afPermManager->getProtectedVariables();
				} catch ( FilterNotFoundException ) {
					// If no filter is found, assume it has no restrictions (is public and uses no protected
					// variables) because it should be an non-existing filter ID.
					$filter = MutableFilter::newDefault();
					$usedVariables = [];
					$foundInvalid = true;
				}

				if ( !$canViewSuppressed && $filter->isSuppressed() ) {
					$this->dieWithError(
						[ 'apierror-permissiondenied', $this->msg( 'action-abusefilter-log-suppressed' ) ]
					);
				}

				if ( !$canViewPrivate && $filter->isHidden() ) {
					$this->dieWithError(
						[ 'apierror-permissiondenied', $this->msg( 'action-abusefilter-log-private' ) ]
					);
				}

				if ( $filter->isProtected() ) {
					$protectedVariableAccessStatus = $this->afPermManager
						->canViewProtectedVariables( $performer, $usedVariables );
					if ( !$protectedVariableAccessStatus->isGood() ) {
						if ( $protectedVariableAccessStatus->getBlock() ) {
							$this->dieWithError( 'apierror-blocked', 'blocked' );
						}
						if ( $protectedVariableAccessStatus->getPermission() ) {
							$this->dieWithError(
								[
									'apierror-permissiondenied',
									$this->msg( "action-{$protectedVariableAccessStatus->getPermission()}" )->plain()
								],
								'permissiondenied'
							);
						}

						$this->dieStatus( $protectedVariableAccessStatus );
					}
				}
			}

			if ( $foundInvalid ) {
				// @todo Tell what the invalid IDs are
				$this->addWarning( 'abusefilter-log-invalid-filter' );
			}
		}

		$result = $this->getResult();

		$this->addTables( 'abuse_filter_log' );
		$this->addFields( 'afl_timestamp' );
		$this->addFields( 'afl_rev_id' );
		$this->addFields( 'afl_deleted' );
		$this->addFields( 'afl_filter_id' );
		$this->addFields( 'afl_global' );
		$this->addFields( 'afl_ip_hex' );
		$this->addFieldsIf( 'afl_id', $fld_ids );
		$this->addFieldsIf( 'afl_user_text', $fld_user );
		$this->addFieldsIf( [ 'afl_namespace', 'afl_title' ], $fld_title );
		$this->addFieldsIf( 'afl_action', $fld_action );
		$this->addFieldsIf( 'afl_var_dump', $fld_details );
		$this->addFieldsIf( 'afl_actions', $fld_result );
		$this->addFieldsIf( 'afl_wiki', $fld_wiki );

		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$this->addWhereIf( [ 'afl_id' => $params['logid'] ], isset( $params['logid'] ) );

		$this->addWhereRange( 'afl_timestamp', $params['dir'], $params['start'], $params['end'] );

		if ( isset( $params['user'] ) ) {
			$this->addUserFilter( $performer, $params['user'] );
		}

		$this->addWhereIf( [ 'afl_deleted' => 0 ], !$this->afPermManager->canSeeHiddenLogEntries( $performer ) );

		if ( $searchFilters ) {
			// @todo Avoid code duplication with SpecialAbuseLog::showList
			$filterConds = [ 'local' => [], 'global' => [] ];
			foreach ( $searchFilters as $filter ) {
				$isGlobal = $filter[1];
				$key = $isGlobal ? 'global' : 'local';
				$filterConds[$key][] = $filter[0];
			}
			$dbr = $this->getDB();
			$conds = [];
			if ( $filterConds['local'] ) {
				$conds[] = $dbr->andExpr( [
					'afl_global' => 0,
					'afl_filter_id' => $filterConds['local'],
				] );
			}
			if ( $filterConds['global'] ) {
				$conds[] = $dbr->andExpr( [
					'afl_global' => 1,
					'afl_filter_id' => $filterConds['global'],
				] );
			}
			$this->addWhere( $dbr->orExpr( $conds ) );
		}

		if ( isset( $params['wiki'] ) ) {
			// 'wiki' won't be set if $wgAbuseFilterIsCentral = false
			$this->addWhereIf( [ 'afl_wiki' => $params['wiki'] ], $isCentral );
		}

		$title = $params['title'];
		if ( $title !== null ) {
			$titleObj = Title::newFromText( $title );
			if ( $titleObj === null ) {
				$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $title ) ] );
			}
			$this->addWhereFld( 'afl_namespace', $titleObj->getNamespace() );
			$this->addWhereFld( 'afl_title', $titleObj->getDBkey() );
		}
		$res = $this->select( __METHOD__ );

		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've had enough
				$ts = new MWTimestamp( $row->afl_timestamp );
				$this->setContinueEnumParameter( 'start', $ts->getTimestamp( TS_ISO_8601 ) );
				break;
			}
			$visibility = SpecialAbuseLog::getEntryVisibilityForUser( $row, $performer, $this->afPermManager );
			if ( $visibility !== SpecialAbuseLog::VISIBILITY_VISIBLE ) {
				continue;
			}

			$filterID = $row->afl_filter_id;
			$global = $row->afl_global;
			$fullName = GlobalNameUtils::buildGlobalName( $filterID, $global );
			$filterObj = $lookup->getFilter( $filterID, $global );
			$canSeeDetails = $this->afPermManager->canSeeLogDetailsForFilter( $performer, $filterObj );

			$entry = [];
			if ( $fld_ids ) {
				$entry['id'] = intval( $row->afl_id );
				$entry['filter_id'] = $canSeeDetails ? $fullName : '';
			}
			if ( $fld_filter ) {
				$entry['filter'] = $filterObj->getName();
			}
			if ( $fld_user ) {
				$entry['user'] = $row->afl_user_text;
			}
			if ( $fld_wiki ) {
				$entry['wiki'] = $row->afl_wiki;
			}
			if ( $fld_title ) {
				$title = Title::makeTitle( $row->afl_namespace, $row->afl_title );
				ApiQueryBase::addTitleInfo( $entry, $title );
			}
			if ( $fld_action ) {
				$entry['action'] = $row->afl_action;
			}
			if ( $fld_result ) {
				$entry['result'] = $row->afl_actions;
			}
			if ( $fld_revid && $row->afl_rev_id !== null ) {
				$entry['revid'] = $canSeeDetails ? (int)$row->afl_rev_id : '';
			}
			if ( $fld_timestamp ) {
				$ts = new MWTimestamp( $row->afl_timestamp );
				$entry['timestamp'] = $ts->getTimestamp( TS_ISO_8601 );
			}
			if ( $fld_details ) {
				$entry['details'] = [];
				if ( $canSeeDetails ) {
					$vars = $this->afVariablesBlobStore->loadVarDump( $row );
					$varManager = $this->afVariablesManager;
					$entry['details'] = $varManager->exportAllVars( $vars );

					$usedProtectedVars = $this->afPermManager
						->getUsedProtectedVariables( array_keys( $entry['details'] ) );
					if ( $usedProtectedVars ) {
						// Unset the variable if the user can't see protected variables.
						// Additionally, a protected variable is considered used if the key exists
						// but since it can have a null value, check isset before logging access
						$protectedVariableValuesShown = [];
						foreach ( $usedProtectedVars as $protectedVariable ) {
							if ( isset( $entry['details'][$protectedVariable] ) ) {
								if ( $this->afPermManager->canViewProtectedVariables(
									$performer, [ $protectedVariable ]
								)->isGood() ) {
									$protectedVariableValuesShown[] = $protectedVariable;
								} else {
									$entry['details'][$protectedVariable] = '';
								}
							}
						}

						if ( $filterObj->isProtected() ) {
							// We need to log any access of protected variable values. If the site is
							// in read only or user_name or account_name don't exist, then just blank
							// the protected variable values because we cannot create a log
							if (
								!$this->readOnlyMode->isReadOnly() &&
								(
									isset( $entry['details']['user_name'] ) ||
									isset( $entry['details']['account_name'] )
								)
							) {
								$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
								$logger->logViewProtectedVariableValue(
									$performer->getUser(),
									$entry['details']['user_name'] ?? $entry['details']['account_name'],
									$protectedVariableValuesShown
								);
							} else {
								foreach ( $usedProtectedVars as $protectedVariable ) {
									if ( isset( $entry['details'][$protectedVariable] ) ) {
										$entry['details'][$protectedVariable] = '';
									}
								}
							}

						}
					}
				}
			}

			if ( $fld_hidden ) {
				$entry['hidden'] = (bool)$row->afl_deleted;
			}

			if ( $entry ) {
				$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $entry );
				if ( !$fit ) {
					$ts = new MWTimestamp( $row->afl_timestamp );
					$this->setContinueEnumParameter( 'start', $ts->getTimestamp( TS_ISO_8601 ) );
					break;
				}
			}
		}
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'item' );
	}

	/**
	 * Updates the query params and the internal query builder to include the
	 * parameters and clauses required for filtering out entries not associated
	 * with the provided username, IP or IP range.
	 *
	 * @param Authority $performer The authority listing the AF logs.
	 * @param string $userName Username or IP address to filter for.
	 * @return void
	 */
	private function addUserFilter( Authority $performer, string $userName ): void {
		if ( IPUtils::isIPAddress( $userName ) ) {
			$cleanIP = IPUtils::sanitizeIP( $userName );
			$canAccessTempAccountIPs =
				$this->tempAccountIPsViewerSpecification->isSatisfiedBy(
					$performer
				);

			if ( !$canAccessTempAccountIPs ) {
				// Gets entries associated with anonymous users identified by
				// their IPs (i.e. filter by afl_user_text; legacy behaviour).
				$expression = $this->abuseLogConditionFactory
					->getUserFilterByUserIdentity(
						new UserIdentityValue( 0, $cleanIP )
					);
			} else {
				// Gets entries associated with anonymous users identified by
				// their IPs (i.e. filter by afl_user_text; legacy behavior) as
				// well as entries associated with temp accounts under the
				// provided IP (i.e. filter by afl_ip_hex).
				$expression = $this->abuseLogConditionFactory
					->getUserFilterByIPAddress( $cleanIP );
			}

			if ( $expression ) {
				$this->getQueryBuilder()->conds( $expression );
			}
		} else {
			$user = $this->userFactory->newFromName( $userName );

			if ( $user ) {
				$expression = $this->abuseLogConditionFactory
					->getUserFilterByUserIdentity( $user );

				if ( $expression ) {
					$this->addWhere( $expression );
				}
			}
		}
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		$params = [
			'logid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'start' => [
				ParamValidator::PARAM_TYPE => 'timestamp'
			],
			'end' => [
				ParamValidator::PARAM_TYPE => 'timestamp'
			],
			'dir' => [
				ParamValidator::PARAM_TYPE => [
					'newer',
					'older'
				],
				ParamValidator::PARAM_DEFAULT => 'older',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-direction',
			],
			'user' => null,
			'title' => null,
			'filter' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
				ApiBase::PARAM_HELP_MSG => [
					'apihelp-query+abuselog-param-filter',
					GlobalNameUtils::GLOBAL_FILTER_PREFIX
				]
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'ids|user|title|action|result|timestamp|hidden|revid',
				ParamValidator::PARAM_TYPE => [
					'ids',
					'filter',
					'user',
					'title',
					'action',
					'details',
					'result',
					'timestamp',
					'hidden',
					'revid',
				],
				ParamValidator::PARAM_ISMULTI => true
			]
		];
		if ( $this->getConfig()->get( 'AbuseFilterIsCentral' ) ) {
			$params['wiki'] = [
				ParamValidator::PARAM_TYPE => 'string',
			];
			$params['prop'][ParamValidator::PARAM_DEFAULT] .= '|wiki';
			$params['prop'][ParamValidator::PARAM_TYPE][] = 'wiki';
			$params['filter'][ApiBase::PARAM_HELP_MSG] = 'apihelp-query+abuselog-param-filter-central';
		}
		return $params;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=abuselog'
				=> 'apihelp-query+abuselog-example-1',
			'action=query&list=abuselog&afltitle=API'
				=> 'apihelp-query+abuselog-example-2',
		];
	}
}

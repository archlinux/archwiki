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

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\AbuseFilter\AbuseFilterLogDetailsLookup;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * API module to allow accessing private details (the user's IP) from AbuseLog entries
 *
 * @ingroup API
 * @ingroup Extensions
 */
class AbuseLogPrivateDetails extends ApiBase {

	private AbuseFilterPermissionManager $afPermManager;

	private IConnectionProvider $dbProvider;

	private AbuseFilterLogDetailsLookup $afLogPrivateDetailsLookup;

	private FilterLookup $afFilterLookup;

	public function __construct(
		ApiMain $main,
		string $action,
		AbuseFilterPermissionManager $afPermManager,
		IConnectionProvider $dbProvider,
		AbuseFilterLogDetailsLookup $afLogPrivateDetailsLookup,
		FilterLookup $afFilterLookup
	) {
		parent::__construct( $main, $action );
		$this->afPermManager = $afPermManager;
		$this->dbProvider = $dbProvider;
		$this->afLogPrivateDetailsLookup = $afLogPrivateDetailsLookup;
		$this->afFilterLookup = $afFilterLookup;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->afPermManager->canSeePrivateDetails( $this->getAuthority() ) ) {
			$this->dieWithError( 'abusefilter-log-cannot-see-privatedetails' );
		}
		$params = $this->extractRequestParams();
		$logId = $params['logid'];
		$reason = $params['reason'];

		if ( !SpecialAbuseLog::checkPrivateDetailsAccessReason( $reason ) ) {
			// Double check, in case we add some extra validation
			$this->dieWithError( 'abusefilter-noreason' );
		}

		$ipForAbuseFilterLogStatus = $this->afLogPrivateDetailsLookup->getIPForAbuseFilterLog(
			$this->getAuthority(), $logId
		);
		if ( !$ipForAbuseFilterLogStatus->isGood() ) {
			$this->dieStatus( $ipForAbuseFilterLogStatus );
		}

		$dbr = $this->dbProvider->getReplicaDatabase();
		$row = $dbr->newSelectQueryBuilder()
			->select( [ 'afl_user_text', 'afl_filter_id', 'afl_global' ] )
			->from( 'abuse_filter_log' )
			->where( [ 'afl_id' => $logId ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			$this->dieWithError( 'abusefilter-log-nonexistent' );
		}

		$ip = $ipForAbuseFilterLogStatus->getValue();
		$filter = $this->afFilterLookup->getFilter( $row->afl_filter_id, $row->afl_global );

		// Log accessing private details
		if ( $this->getConfig()->get( 'AbuseFilterLogPrivateDetailsAccess' ) ) {
			SpecialAbuseLog::addPrivateDetailsAccessLogEntry(
				$logId,
				$reason,
				$this->getUser()
			);
		}

		$result = [
			'log-id' => $logId,
			'user' => $row->afl_user_text,
			'filter-id' => $filter->getId(),
			'filter-description' => $filter->getName(),
			'ip-address' => $ip !== '' ? $ip : null
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
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
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
		if ( $this->getConfig()->get( 'AbuseFilterPrivateDetailsForceReason' ) ) {
			$params['reason'][ParamValidator::PARAM_REQUIRED] = true;
		} else {
			$params['reason'][ParamValidator::PARAM_DEFAULT] = '';
		}
		return $params;
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=abuselogprivatedetails&logid=1&reason=example&token=ABC123'
				=> 'apihelp-abuselogprivatedetails-example-1'
		];
	}
}

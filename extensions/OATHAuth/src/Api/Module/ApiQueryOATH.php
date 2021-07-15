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

namespace MediaWiki\Extension\OATHAuth\Api\Module;

use ApiQuery;
use ApiQueryBase;
use ApiResult;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use User;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to check if a user has OATH authentication enabled.
 *
 * Usage requires the 'oathauth-api-all' grant which is not given to any group
 * by default. Use of this API is security sensitive and should not be granted
 * lightly. Configuring a special 'oathauth' user group is recommended.
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiQueryOATH extends ApiQueryBase {
	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 */
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'oath' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		if ( $params['user'] === null ) {
			$params['user'] = $this->getUser()->getName();
		}

		$this->checkUserRightsAny( [ 'oathauth-api-all', 'oathauth-verify-user' ] );

		$hasOAthauthApiAll = $this->getPermissionManager()
			->userHasRight(
				$this->getUser(),
				'oathauth-api-all'
			);

		$reasonProvided = $params['reason'] !== null && $params['reason'] !== '';
		if ( !$hasOAthauthApiAll && !$reasonProvided ) {
			$this->dieWithError( [ 'apierror-missingparam', 'reason' ] );
		}

		$user = User::newFromName( $params['user'] );
		if ( $user === false ) {
			$this->dieWithError( 'noname' );
		}

		$result = $this->getResult();
		$data = [
			ApiResult::META_BC_BOOLS => [ 'enabled' ],
			'enabled' => false,
		];

		if ( !$user->isAnon() ) {
			$userRepo = MediaWikiServices::getInstance()->getService( 'OATHUserRepository' );
			$authUser = $userRepo->findByUser( $user );
			$data['enabled'] = $authUser &&
				$authUser->getModule() !== null &&
				$authUser->getModule()->isEnabled( $authUser );

			// Log if the user doesn't have oathauth-api-all or if a reason is provided
			if ( !$hasOAthauthApiAll || $reasonProvided ) {
				$logEntry = new ManualLogEntry( 'oath', 'verify' );
				$logEntry->setPerformer( $this->getUser() );
				$logEntry->setTarget( $user->getUserPage() );
				$logEntry->setComment( $params['reason'] );
				$logEntry->insert();
			}
		}
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * @param array $params
	 *
	 * @return string
	 */
	public function getCacheMode( $params ) {
		return 'private';
	}

	public function isInternal() {
		return true;
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&meta=oath'
				=> 'apihelp-query+oath-example-1',
			'action=query&meta=oath&oathuser=Example'
				=> 'apihelp-query+oath-example-2',
		];
	}
}

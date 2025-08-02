<?php

namespace MediaWiki\CheckUser\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\EnumDef;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * CheckUser API Query Module
 */
class ApiQueryCheckUser extends ApiQueryBase {

	private ApiQueryCheckUserResponseFactory $responseFactory;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		ApiQueryCheckUserResponseFactory $responseFactory
	) {
		parent::__construct( $query, $moduleName, 'cu' );
		$this->responseFactory = $responseFactory;
	}

	public function execute() {
		$this->checkUserRightsAny( 'checkuser' );

		$response = $this->responseFactory->newFromRequest( $this );
		$result = $this->getResult();

		switch ( $response->getRequestType() ) {
			case 'userips':
				$result->addValue( [ 'query', $this->getModuleName() ], 'userips', $response->getResponseData() );
				$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'userips' ], 'ip' );
				break;

			case 'actions':
				$result->addValue( [ 'query', $this->getModuleName() ], 'edits', $response->getResponseData() );
				$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'edits' ], 'action' );
				break;

			case 'ipusers':
				$result->addValue( [ 'query', $this->getModuleName() ], 'ipusers', $response->getResponseData() );
				$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'ipusers' ], 'user' );
				break;

			default:
				$this->dieWithError( 'apierror-checkuser-invalidmode', 'invalidmode' );
		}
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'request'  => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'userips',
					'edits',
					'actions',
					'ipusers',
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'edits' => 'apihelp-query+checkuser-paramvalue-request-actions'
				],
				EnumDef::PARAM_DEPRECATED_VALUES => [
					'edits' => true,
				]
			],
			'target'   => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'ip', 'temp', 'cidr' ],
			],
			'reason'   => [
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => $this->getConfig()->get( 'CheckUserForceSummary' )
			],
			'limit'    => [
				ParamValidator::PARAM_DEFAULT => 500,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN  => 1,
				IntegerDef::PARAM_MAX  => 500,
				IntegerDef::PARAM_MAX2 => $this->getConfig()->get( 'CheckUserMaximumRowCount' ),
			],
			'timecond' => [
				ParamValidator::PARAM_DEFAULT => '-2 weeks'
			],
			'xff'      => null,
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=checkuser&curequest=userips&cutarget=Jimbo_Wales'
				=> 'apihelp-query+checkuser-example-1',
			'action=query&list=checkuser&curequest=actions&cutarget=127.0.0.1/16&xff=1&cureason=Some_check'
				=> 'apihelp-query+checkuser-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:CheckUser#API';
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}
}

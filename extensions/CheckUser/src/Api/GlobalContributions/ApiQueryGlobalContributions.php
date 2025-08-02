<?php
namespace MediaWiki\CheckUser\Api\GlobalContributions;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPagerFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * API query module for global contributions.
 */
class ApiQueryGlobalContributions extends ApiQueryBase {
	private GlobalContributionsPagerFactory $pagerFactory;
	private UserNameUtils $userNameUtils;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		GlobalContributionsPagerFactory $pagerFactory,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( $query, $moduleName, 'guc' );
		$this->pagerFactory = $pagerFactory;
		$this->userNameUtils = $userNameUtils;
	}

	public function execute() {
		$performer = $this->getAuthority();
		$params = $this->extractRequestParams();
		$this->requireOnlyOneParameter( $params, 'target' );

		if ( !$performer->isRegistered() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic' );
		}

		// Only allow looking up registered targets via the API
		// until performance issues with IP targets are resolved (T384848).
		// That will also require mirroring the corresponding permission checks here.
		if ( !$this->userNameUtils->isValid( $params['target'] ) ) {
			$this->dieWithError( [ 'apierror-invaliduser', $params['target'] ] );
		}

		$target = new UserIdentityValue( 0, $params['target'] );

		$pager = $this->pagerFactory->createPager( $this, [], $target );
		$pager->setLimit( $params['limit'] );
		$pager->setOffset( $params['offset'] );

		$pager->doQuery();

		$pagingQueries = $pager->getPagingQueries();
		$result = $this->getResult();

		foreach ( $pager->getResult() as $row ) {
			$item = [
				'wikiid' => $row->sourcewiki,
				'revid' => $row->rev_id,
				'timestamp' => $row->rev_timestamp,
			];

			$result->addValue( [ 'query', $this->getModuleName(), 'entries' ], null, $item );
		}

		if ( isset( $pagingQueries['next']['offset'] ) ) {
			$this->setContinueEnumParameter( 'offset', $pagingQueries['next']['offset'] );
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName(), 'entries' ], 'entry' );

		if ( $pager->hasExternalApiLookupError() ) {
			$this->addWarning( 'checkuser-global-contributions-api-lookup-error' );
		}
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'target' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit'  => [
				ParamValidator::PARAM_DEFAULT => 50,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN  => 1,
				IntegerDef::PARAM_MAX  => ApiBase::LIMIT_SML1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_SML2,
			],
			'offset' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=globalcontributions&guctarget=Example' =>
				'apihelp-query+globalcontributions-example-1',
			'action=query&list=globalcontributions&guctarget=Example&guclimit=100&gucoffset=20250121165852|-1|1115' =>
				'apihelp-query+globalcontributions-example-2',
		];
	}
}

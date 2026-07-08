<?php

namespace MediaWiki\Extension\CheckUser\Services;

use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse;
use MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserActionsResponse;
use MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserIpUsersResponse;
use MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserUserIpsResponse;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Logging\LogFormatterFactory;
use MediaWiki\Revision\ArchivedRevisionLookup;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Rdbms\IConnectionProvider;

class ApiQueryCheckUserResponseFactory {

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly Config $config,
		private readonly MessageLocalizer $messageLocalizer,
		private readonly CheckUserLogService $checkUserLogService,
		private readonly UserNameUtils $userNameUtils,
		private readonly CheckUserLookupUtils $checkUserLookupUtils,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly CommentStore $commentStore,
		private readonly RevisionStore $revisionStore,
		private readonly ArchivedRevisionLookup $archivedRevisionLookup,
		private readonly UserFactory $userFactory,
		private readonly LogFormatterFactory $logFormatterFactory,
	) {
	}

	/**
	 * @param ApiQueryCheckUser $module The module that is handling the request (you should be able to use $this).
	 * @return ApiQueryCheckUserAbstractResponse
	 */
	public function newFromRequest( ApiQueryCheckUser $module ): ApiQueryCheckUserAbstractResponse {
		// No items for the factory method exist yet, but will be added later.
		switch ( $module->extractRequestParams()['request'] ) {
			case 'userips':
				return new ApiQueryCheckUserUserIpsResponse(
					$module,
					$this->dbProvider,
					$this->config,
					$this->messageLocalizer,
					$this->checkUserLogService,
					$this->userNameUtils,
					$this->checkUserLookupUtils,
					$this->userIdentityLookup,
				);
			case 'edits':
				$module->addDeprecation(
					[
						'apiwarn-deprecation-withreplacement', 'curequest=edits', 'curequest=actions',
					],
					'curequest=edits'
				);
			// fall-through to 'actions' for now, eventually delete this entire case statement once 'edits' is
			// removed after hard-deprecation.
			case 'actions':
				return new ApiQueryCheckUserActionsResponse(
					$module,
					$this->dbProvider,
					$this->config,
					$this->messageLocalizer,
					$this->checkUserLogService,
					$this->userNameUtils,
					$this->checkUserLookupUtils,
					$this->userIdentityLookup,
					$this->commentStore,
					$this->userFactory,
					$this->logFormatterFactory
				);
			case 'ipusers':
				return new ApiQueryCheckUserIpUsersResponse(
					$module,
					$this->dbProvider,
					$this->config,
					$this->messageLocalizer,
					$this->checkUserLogService,
					$this->userNameUtils,
					$this->checkUserLookupUtils,
					$this->userFactory
				);
			default:
				$module->dieWithError( 'apierror-checkuser-invalidmode', 'invalidmode' );
		}
	}
}

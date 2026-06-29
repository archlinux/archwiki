<?php

namespace MediaWiki\Extension\Notifications\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Controller\NotificationController;
use MediaWiki\Extension\Notifications\NotifUser;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEchoMarkRead extends ApiBase {
	use ApiCrossWiki;
	use ApiEchoPermissionsTrait;

	public function execute() {
		$this->checkReadNotificationsPermissions();

		// To avoid API warning, register the parameter used to bust browser cache
		$this->getMain()->getVal( '_' );

		$notifUser = NotifUser::newFromUser( $this->getUser() );

		$params = $this->extractRequestParams();

		// Mark as read/unread locally, if requested
		if ( in_array( WikiMap::getCurrentWikiId(), $this->getRequestedWikis() ) ) {
			// There is no need to trigger markRead if all notifications are read
			if ( $notifUser->getLocalNotificationCount() > 0 ) {
				if ( $params['list'] ) {
					// Make sure there is a limit to the update
					$notifUser->markRead( array_slice( $params['list'], 0, ApiBase::LIMIT_SML2 ) );
					// Mark all as read
				} elseif ( $params['all'] ) {
					$notifUser->markAllRead();
					// Mark all as read for sections
				} elseif ( $params['sections'] ) {
					$notifUser->markAllRead( $params['sections'] );
				}
			}

			// Mark as unread
			if ( $params['unreadlist'] !== null && $params['unreadlist'] !== [] ) {
				// Make sure there is a limit to the update
				$notifUser->markUnRead( array_slice( $params['unreadlist'], 0, ApiBase::LIMIT_SML2 ) );
			}
		}

		$foreignResults = $this->getFromForeign();

		$result = [
			'result' => 'success',
		];

		foreach ( $foreignResults as $wiki => $foreignResult ) {
			if ( isset( $foreignResult['error'] ) ) {
				$result['errors'][$wiki] = $foreignResult['error'];
			}
		}

		$rawCount = 0;
		foreach ( AttributeManager::$sections as $section ) {
			$rawSectionCount = $notifUser->getNotificationCount( $section );
			$result[$section]['rawcount'] = $rawSectionCount;
			$result[$section]['count'] = NotificationController::formatNotificationCount( $rawSectionCount );
			$rawCount += $rawSectionCount;
		}

		$result += [
			'rawcount' => $rawCount,
			'count' => NotificationController::formatNotificationCount( $rawCount ),
		];

		$this->getResult()->addValue( 'query', $this->getModuleName(), $result );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return $this->getCrossWikiParams() + [
			'list' => [
				ParamValidator::PARAM_ISMULTI => true,
			],
			'unreadlist' => [
				ParamValidator::PARAM_ISMULTI => true,
			],
			'all' => [
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'sections' => [
				ParamValidator::PARAM_TYPE => AttributeManager::$sections,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'token' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function mustBePosted() {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		return [
			'action=echomarkread&list=8'
				=> 'apihelp-echomarkread-example-1',
			'action=echomarkread&all=true'
				=> 'apihelp-echomarkread-example-2',
			'action=echomarkread&unreadlist=1'
				=> 'apihelp-echomarkread-example-3',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}

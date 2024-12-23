<?php

namespace MediaWiki\Extension\Notifications\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class ApiEchoCreateEvent extends ApiBase {

	private UserNameUtils $userNameUtils;

	public function __construct(
		ApiMain $mainModule,
		$moduleName,
		UserNameUtils $userNameUtils
	) {
		parent::__construct( $mainModule, $moduleName );
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * @see ApiBase::execute()
	 * @return void
	 */
	public function execute() {
		if ( !$this->getConfig()->get( 'EchoEnableApiEvents' ) ) {
			$this->dieWithError( [ 'apierror-moduledisabled', $this->getModuleName() ] );
		}

		// Only for logged in users
		$user = $this->getUser();
		if ( !$user->isNamed() ) {
			$this->dieWithError( 'apierror-mustbeloggedin-generic', 'login-required' );
		}

		$params = $this->extractRequestParams();

		// Default to self if unspecified
		/** @var UserIdentity $userToNotify */
		$userToNotify = $params['user'] ?? $user;

		if ( $userToNotify->getName() !== $user->getName() ) {
			$this->checkUserRightsAny( 'echo-create' );
		}

		if ( !$userToNotify->isRegistered() || $this->userNameUtils->isTemp( $userToNotify->getName() ) ) {
			$this->dieWithError( [ 'nosuchusershort', $userToNotify->getName() ] );
		}

		$event = Event::create( [
			// type is one of api-notice, api-alert
			'type' => 'api-' . $params['section'],
			'agent' => $user,
			'title' => $params['page'] ? Title::newFromLinkTarget( $params['page'] ) : null,
			'extra' => [
				'recipients' => [ $userToNotify->getId() ],
				'header' => $params['header'],
				'content' => $params['content'],
				// Send email only if specified
				'noemail' => !$params['email'],
			]
		] );

		// Return a success message
		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			[
				'result' => 'success'
			]
		);
	}

	/**
	 * @see ApiBase::needsToken()
	 * @return bool
	 */
	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @see ApiBase::getAllowedParams()
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'id' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'header' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_BYTES => 160,
			],
			'content' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_BYTES => 5000,
			],
			'page' => [
				ParamValidator::PARAM_TYPE => 'title',
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
			'section' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [ 'alert', 'notice' ],
				ParamValidator::PARAM_DEFAULT => 'notice',
			],
			'email' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return string[]
	 */
	protected function getExamplesMessages() {
		return [
			'action=echocreateevent&header=Hi&content=From_API' => 'apihelp-echocreateevent-example',
		];
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Echo_(Notifications)/API';
	}
}

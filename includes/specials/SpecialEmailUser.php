<?php
/**
 * Implements Special:Emailuser
 *
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
 *
 * @file
 * @ingroup SpecialPage
 */

namespace MediaWiki\Specials;

use ErrorPageError;
use HTMLForm;
use IContextSource;
use MediaWiki\Config\Config;
use MediaWiki\Mail\EmailUserFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNamePrefixSearch;
use MediaWiki\User\UserNameUtils;
use MediaWiki\User\UserOptionsLookup;
use Message;
use PermissionsError;
use StatusValue;
use ThrottledError;
use UserBlockedError;

/**
 * A special page that allows users to send e-mails to other users
 *
 * @ingroup SpecialPage
 */
class SpecialEmailUser extends SpecialPage {
	protected $mTarget;

	/**
	 * @var User|string
	 */
	protected $mTargetObj;

	private UserNameUtils $userNameUtils;
	private UserNamePrefixSearch $userNamePrefixSearch;
	private UserOptionsLookup $userOptionsLookup;
	private EmailUserFactory $emailUserFactory;
	private UserFactory $userFactory;

	/**
	 * @param UserNameUtils $userNameUtils
	 * @param UserNamePrefixSearch $userNamePrefixSearch
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param EmailUserFactory $emailUserFactory
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		UserNameUtils $userNameUtils,
		UserNamePrefixSearch $userNamePrefixSearch,
		UserOptionsLookup $userOptionsLookup,
		EmailUserFactory $emailUserFactory,
		UserFactory $userFactory
	) {
		parent::__construct( 'Emailuser' );
		$this->userNameUtils = $userNameUtils;
		$this->userNamePrefixSearch = $userNamePrefixSearch;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->emailUserFactory = $emailUserFactory;
		$this->userFactory = $userFactory;
	}

	public function doesWrites() {
		return true;
	}

	public function getDescription() {
		$target = self::getTarget( $this->mTarget ?? '', $this->getUser() );
		if ( !$target instanceof User ) {
			return $this->msg( 'emailuser-title-notarget' );
		}

		return $this->msg( 'emailuser-title-target', $target->getName() );
	}

	protected function getFormFields() {
		$linkRenderer = $this->getLinkRenderer();
		$user = $this->getUser();
		return [
			'From' => [
				'type' => 'info',
				'raw' => 1,
				'default' => $linkRenderer->makeLink(
					$user->getUserPage(),
					$user->getName()
				),
				'label-message' => 'emailfrom',
				'id' => 'mw-emailuser-sender',
			],
			'To' => [
				'type' => 'info',
				'raw' => 1,
				'default' => $linkRenderer->makeLink(
					$this->mTargetObj->getUserPage(),
					$this->mTargetObj->getName()
				),
				'label-message' => 'emailto',
				'id' => 'mw-emailuser-recipient',
			],
			'Target' => [
				'type' => 'hidden',
				'default' => $this->mTargetObj->getName(),
			],
			'Subject' => [
				'type' => 'text',
				'default' => $this->msg( 'defemailsubject', $user->getName() )->inContentLanguage()->text(),
				'label-message' => 'emailsubject',
				'maxlength' => 200,
				'size' => 60,
				'required' => true,
			],
			'Text' => [
				'type' => 'textarea',
				'rows' => 20,
				'label-message' => 'emailmessage',
				'required' => true,
			],
			'CCMe' => [
				'type' => 'check',
				'label-message' => 'emailccme',
				'default' => $this->userOptionsLookup->getBoolOption( $user, 'ccmeonemails' ),
			],
		];
	}

	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$out->addModuleStyles( 'mediawiki.special' );

		$this->mTarget = $par ?? $request->getVal( 'wpTarget', $request->getVal( 'target', '' ) );

		// This needs to be below assignment of $this->mTarget because
		// getDescription() needs it to determine the correct page title.
		$this->setHeaders();
		$this->outputHeader();

		// Error out if sending user cannot do this. Don't authorize yet.
		$error = self::getPermissionsError(
			$this->getUser(),
			$this->getRequest()->getVal( 'wpEditToken' ),
			$this->getConfig()
		);

		switch ( $error ) {
			case null:
				# Wahey!
				break;
			case 'badaccess':
				throw new PermissionsError( 'sendemail' );
			case 'blockedemailuser':
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Block is checked and not null
				throw new UserBlockedError( $this->getUser()->getBlock() );
			case 'actionthrottledtext':
				throw new ThrottledError;
			case 'mailnologin':
			case 'usermaildisabled':
				throw new ErrorPageError( $error, "{$error}text" );
			default:
				# It's a hook error
				[ $title, $msg, $params ] = $error;
				throw new ErrorPageError( $title, $msg, $params );
		}

		// A little hack: HTMLForm will check wpTarget only, if the form was posted, not
		// if the user opens Special:EmailUser/Florian (e.g.). So check, if the user did that
		// and show the "Send email to user" form directly, if so. Show the "enter username"
		// form, otherwise.
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable target is set
		$this->mTargetObj = self::getTarget( $this->mTarget, $this->getUser() );
		if ( !$this->mTargetObj instanceof User ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable target is set
			$this->userForm( $this->mTarget );
		} else {
			$this->sendEmailForm();
		}
	}

	/**
	 * Validate target User
	 *
	 * @param string $target Target user name
	 * @param User $sender User sending the email
	 * @return User|string User object on success or a string on error
	 * @todo Deprecate this once we have a better factory/lookup for UserEmailContact
	 */
	public static function getTarget( $target, User $sender ) {
		$targetObject = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $target );
		if ( !$targetObject instanceof User ) {
			return 'notarget';
		}

		$status = MediaWikiServices::getInstance()->getEmailUserFactory()
			->newEmailUser( $sender )
			->validateTarget( $targetObject );
		if ( !$status->isGood() ) {
			$msg = $status->getErrors()[0]['message'];
			$ret = $msg === 'emailnotarget' ? 'notarget' : preg_replace( '/text$/', '', $msg );
		} else {
			$ret = $targetObject;
		}
		return $ret;
	}

	/**
	 * Validate target User
	 *
	 * @param User $target Target user
	 * @param User $sender User sending the email
	 * @return string Error message or empty string if valid.
	 * @since 1.30
	 * @deprecated since 1.41 Use EmailUser::validateTarget()
	 */
	public static function validateTarget( $target, User $sender ) {
		if ( !$target instanceof User ) {
			return 'notarget';
		}
		$status = MediaWikiServices::getInstance()->getEmailUserFactory()
			->newEmailUser( $sender )
			->validateTarget( $target );
		if ( $status->isGood() ) {
			$ret = '';
		} else {
			$msg = $status->getErrors()[0]['message'];
			$ret = $msg === 'emailnotarget' ? 'notarget' : preg_replace( '/text$/', '', $msg );
		}
		return $ret;
	}

	/**
	 * Check whether a user is allowed to send email
	 *
	 * @param User $user
	 * @param string $editToken
	 * @param Config|null $config optional for backwards compatibility
	 * @param bool $authorize whether to authorize the immediate sending of mails,
	 *        rather than just checking beforehand.
	 *
	 * @return null|string|array Null on success, string on error, or array on
	 *  hook error
	 * @deprecated since 1.41 Use EmailUser::canSend() or EmailUser::authorizeSend()
	 */
	public static function getPermissionsError( $user, $editToken, Config $config = null, $authorize = false ) {
		$emailUser = MediaWikiServices::getInstance()->getEmailUserFactory()->newEmailUserBC( $user, $config );
		$emailUser->setEditToken( (string)$editToken );
		$status = $authorize ? $emailUser->authorizeSend() : $emailUser->canSend();

		if ( $status->isGood() ) {
			return null;
		}
		foreach ( $status->getErrors() as $err ) {
			$errKey = $err['message'] instanceof Message ? $err['message']->getKey() : $err['message'];
			if ( strpos( $errKey, 'blockedtext' ) !== false ) {
				// BC for block messages
				return "blockedemailuser";
			}
		}
		$error = $status->getErrors()[0];
		if ( $status->getValue() !== null ) {
			// BC for hook errors intended to be used with ErrorPageError
			return [ $status->getValue(), $error['message'], $error['params'] ];
		}
		return $error['message'];
	}

	/**
	 * Form to ask for target user name.
	 *
	 * @param string $name User name submitted.
	 */
	protected function userForm( $name ) {
		$htmlForm = HTMLForm::factory( 'ooui', [
			'Target' => [
				'type' => 'user',
				'exists' => true,
				'required' => true,
				'label-message' => 'emailusername',
				'id' => 'emailusertarget',
				'autofocus' => true,
				// Skip validation when visit directly without subpage (T347854)
				'default' => '',
				// Prefill for subpage syntax and old target param.
				'filter-callback' => static function ( $value ) use ( $name ) {
					return str_replace( '_', ' ',
						( $value !== '' && $value !== false && $value !== null ) ? $value : $name );
				}
			]
		], $this->getContext() );

		$htmlForm
			->setMethod( 'GET' )
			->setTitle( $this->getPageTitle() ) // Remove subpage
			->setSubmitCallback( [ $this, 'sendEmailForm' ] )
			->setId( 'askusername' )
			->setWrapperLegendMsg( 'emailtarget' )
			->setSubmitTextMsg( 'emailusernamesubmit' )
			->show();
	}

	public function sendEmailForm() {
		$out = $this->getOutput();

		if ( !$this->mTargetObj instanceof User ) {
			if ( $this->mTarget != '' ) {
				// Messages used here: noemailtext, nowikiemailtext
				$msg = ( $this->mTargetObj === 'notarget' ) ? 'emailnotarget' : ( $this->mTargetObj . 'text' );
				return Status::newFatal( $msg );
			}
			return false;
		}

		$htmlForm = HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() );
		// By now we are supposed to be sure that $this->mTarget is a user name
		$htmlForm
			->setTitle( $this->getPageTitle() ) // Remove subpage
			->addPreHtml( $this->msg( 'emailpagetext', $this->mTarget )->parse() )
			->setSubmitTextMsg( 'emailsend' )
			->setSubmitCallback( [ $this, 'onFormSubmit' ] )
			->setWrapperLegendMsg( 'email-legend' )
			->prepareForm();

		if ( !$this->getHookRunner()->onEmailUserForm( $htmlForm ) ) {
			return false;
		}

		$result = $htmlForm->show();

		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$out->setPageTitleMsg( $this->msg( 'emailsent' ) );
			$out->addWikiMsg( 'emailsenttext', $this->mTarget );
			$out->returnToMain( false, $this->mTargetObj->getUserPage() );
		}
		return true;
	}

	/**
	 * @param array $data
	 * @return StatusValue|false
	 * @internal Only public because it's used as an HTMLForm callback.
	 */
	public function onFormSubmit( array $data ) {
		$target = $this->userFactory->newFromName( $data['Target'] );
		if ( !$target instanceof User ) {
			return StatusValue::newFatal( 'emailnotarget' );
		}

		$emailUser = $this->emailUserFactory->newEmailUser( $this->getAuthority() );
		$emailUser->setEditToken( $this->getRequest()->getVal( 'wpEditToken' ) );

		// Fully authorize on sending emails.
		$status = $emailUser->authorizeSend();

		if ( !$status->isOK() ) {
			return $status;
		}

		$res = $emailUser->sendEmailUnsafe(
			$target,
			$data['Subject'],
			$data['Text'],
			$data['CCMe'],
			$this->getLanguage()->getCode()
		);
		if ( $res->hasMessage( 'hookaborted' ) ) {
			// BC: The method could previously return false if the EmailUser hook set the error to false. Preserve
			// that behaviour until we replace the hook.
			$res = false;
		} else {
			$res = Status::wrap( $res );
		}
		return $res;
	}

	/**
	 * Really send a mail. Permissions should have been checked using
	 * getPermissionsError(). It is probably also a good
	 * idea to check the edit token and ping limiter in advance.
	 *
	 * @param array $data
	 * @param IContextSource $context
	 * @return Status|false
	 * @deprecated since 1.41 Use EmailUser::sendEmailUnsafe()
	 */
	public static function submit( array $data, IContextSource $context ) {
		$target = MediaWikiServices::getInstance()->getUserFactory()->newFromName( (string)$data['Target'] );
		if ( !$target instanceof User ) {
			return Status::newFatal( 'emailnotarget' );
		}

		$emailUser = MediaWikiServices::getInstance()->getEmailUserFactory()
			->newEmailUserBC( $context->getAuthority(), $context->getConfig() );

		$ret = $emailUser->sendEmailUnsafe(
			$target,
			(string)$data['Subject'],
			(string)$data['Text'],
			(bool)$data['CCMe'],
			$context->getLanguage()->getCode()
		);
		if ( $ret->hasMessage( 'hookaborted' ) ) {
			// BC: The method could previously return false if the EmailUser hook set the error to false.
			$ret = false;
		} elseif ( $ret->hasMessage( 'noemailtarget' ) ) {
			// BC: The previous implementation would use notargettext even if noemailtarget would be the right
			// message to use here.
			return Status::newFatal( 'notargettext' );
		} else {
			$ret = Status::wrap( $ret );
		}
		return $ret;
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$search = $this->userNameUtils->getCanonical( $search );
		if ( !$search ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return $this->userNamePrefixSearch
			->search( UserNamePrefixSearch::AUDIENCE_PUBLIC, $search, $limit, $offset );
	}

	/**
	 * @return bool
	 */
	public function isListed() {
		return $this->getConfig()->get( MainConfigNames::EnableUserEmail );
	}

	protected function getGroupName() {
		return 'users';
	}
}

/**
 * @deprecated since 1.41
 */
class_alias( SpecialEmailUser::class, 'SpecialEmailUser' );

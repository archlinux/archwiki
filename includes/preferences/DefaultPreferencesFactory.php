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
 *
 * @file
 */

namespace MediaWiki\Preferences;

use DateTime;
use DateTimeZone;
use Exception;
use Html;
use HTMLForm;
use HTMLFormField;
use IContextSource;
use ILanguageConverter;
use Language;
use LanguageCode;
use LanguageConverter;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserOptionsLookup;
use MediaWiki\User\UserOptionsManager;
use Message;
use MessageLocalizer;
use MWException;
use MWTimestamp;
use NamespaceInfo;
use OutputPage;
use Parser;
use ParserOptions;
use PreferencesFormOOUI;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use SkinFactory;
use SpecialPage;
use Status;
use Title;
use UnexpectedValueException;
use User;
use UserGroupMembership;
use Wikimedia\RequestTimeout\TimeoutException;
use Xml;

/**
 * This is the default implementation of PreferencesFactory.
 */
class DefaultPreferencesFactory implements PreferencesFactory {
	use LoggerAwareTrait;

	/** @var ServiceOptions */
	protected $options;

	/** @var Language The wiki's content language. */
	protected $contLang;

	/** @var LanguageNameUtils */
	protected $languageNameUtils;

	/** @var AuthManager */
	protected $authManager;

	/** @var LinkRenderer */
	protected $linkRenderer;

	/** @var NamespaceInfo */
	protected $nsInfo;

	/** @var PermissionManager */
	protected $permissionManager;

	/** @var ILanguageConverter */
	private $languageConverter;

	/** @var HookRunner */
	private $hookRunner;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/** @var LanguageConverterFactory */
	private $languageConverterFactory;

	/** @var Parser */
	private $parser;

	/** @var SkinFactory */
	private $skinFactory;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var SignatureValidatorFactory */
	private $signatureValidatorFactory;

	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::AllowRequiringEmailForResets,
		MainConfigNames::AllowUserCss,
		MainConfigNames::AllowUserCssPrefs,
		MainConfigNames::AllowUserJs,
		MainConfigNames::DefaultSkin,
		MainConfigNames::EmailAuthentication,
		MainConfigNames::EmailConfirmToEdit,
		MainConfigNames::EnableEmail,
		MainConfigNames::EnableUserEmail,
		MainConfigNames::EnableUserEmailMuteList,
		MainConfigNames::EnotifMinorEdits,
		MainConfigNames::EnotifRevealEditorAddress,
		MainConfigNames::EnotifUserTalk,
		MainConfigNames::EnotifWatchlist,
		MainConfigNames::ForceHTTPS,
		MainConfigNames::HiddenPrefs,
		MainConfigNames::ImageLimits,
		MainConfigNames::LanguageCode,
		MainConfigNames::LocalTZoffset,
		MainConfigNames::MaxSigChars,
		MainConfigNames::RCMaxAge,
		MainConfigNames::RCShowWatchingUsers,
		MainConfigNames::RCWatchCategoryMembership,
		MainConfigNames::SearchMatchRedirectPreference,
		MainConfigNames::SecureLogin,
		MainConfigNames::ScriptPath,
		MainConfigNames::SignatureValidation,
		MainConfigNames::ThumbLimits,
	];

	/**
	 * @param ServiceOptions $options
	 * @param Language $contLang
	 * @param AuthManager $authManager
	 * @param LinkRenderer $linkRenderer
	 * @param NamespaceInfo $nsInfo
	 * @param PermissionManager $permissionManager
	 * @param ILanguageConverter $languageConverter
	 * @param LanguageNameUtils $languageNameUtils
	 * @param HookContainer $hookContainer
	 * @param UserOptionsLookup $userOptionsLookup Should be an instance of UserOptionsManager
	 * @param LanguageConverterFactory|null $languageConverterFactory
	 * @param Parser|null $parser
	 * @param SkinFactory|null $skinFactory
	 * @param UserGroupManager|null $userGroupManager
	 * @param SignatureValidatorFactory|null $signatureValidatorFactory
	 */
	public function __construct(
		ServiceOptions $options,
		Language $contLang,
		AuthManager $authManager,
		LinkRenderer $linkRenderer,
		NamespaceInfo $nsInfo,
		PermissionManager $permissionManager,
		ILanguageConverter $languageConverter,
		LanguageNameUtils $languageNameUtils,
		HookContainer $hookContainer,
		UserOptionsLookup $userOptionsLookup,
		LanguageConverterFactory $languageConverterFactory = null,
		Parser $parser = null,
		SkinFactory $skinFactory = null,
		UserGroupManager $userGroupManager = null,
		SignatureValidatorFactory $signatureValidatorFactory = null
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->options = $options;
		$this->contLang = $contLang;
		$this->authManager = $authManager;
		$this->linkRenderer = $linkRenderer;
		$this->nsInfo = $nsInfo;

		// We don't use the PermissionManager anymore, but we need to be careful
		// removing the parameter since this class is extended by GlobalPreferencesFactory
		// in the GlobalPreferences extension, and that class uses it
		$this->permissionManager = $permissionManager;

		$this->logger = new NullLogger();
		$this->languageConverter = $languageConverter;
		$this->languageNameUtils = $languageNameUtils;
		$this->hookRunner = new HookRunner( $hookContainer );

		// Don't break GlobalPreferences, fall back to global state if missing services
		// or if passed a UserOptionsLookup that isn't UserOptionsManager
		$services = static function () {
			// BC hack. Use a closure so this can be unit-tested.
			return MediaWikiServices::getInstance();
		};
		$this->userOptionsManager = ( $userOptionsLookup instanceof UserOptionsManager )
			? $userOptionsLookup
			: $services()->getUserOptionsManager();
		$this->languageConverterFactory = $languageConverterFactory ?? $services()->getLanguageConverterFactory();

		$this->parser = $parser ?? $services()->getParser();
		$this->skinFactory = $skinFactory ?? $services()->getSkinFactory();
		$this->userGroupManager = $userGroupManager ?? $services()->getUserGroupManager();
		$this->signatureValidatorFactory = $signatureValidatorFactory
			?? $services()->getSignatureValidatorFactory();
	}

	/**
	 * @inheritDoc
	 */
	public function getSaveBlacklist() {
		return [
			'realname',
			'emailaddress',
		];
	}

	/**
	 * @throws MWException
	 * @param User $user
	 * @param IContextSource $context
	 * @return array
	 */
	public function getFormDescriptor( User $user, IContextSource $context ) {
		$preferences = [];

		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		$this->profilePreferences( $user, $context, $preferences );
		$this->skinPreferences( $user, $context, $preferences );
		$this->datetimePreferences( $user, $context, $preferences );
		$this->filesPreferences( $context, $preferences );
		$this->renderingPreferences( $user, $context, $preferences );
		$this->editingPreferences( $user, $context, $preferences );
		$this->rcPreferences( $user, $context, $preferences );
		$this->watchlistPreferences( $user, $context, $preferences );
		$this->searchPreferences( $context, $preferences );

		$this->hookRunner->onGetPreferences( $user, $preferences );

		$this->loadPreferenceValues( $user, $context, $preferences );
		$this->logger->debug( "Created form descriptor for user '{$user->getName()}'" );
		return $preferences;
	}

	/**
	 * Simplify form descriptor for validation or something similar.
	 *
	 * @param array $descriptor HTML form descriptor.
	 * @return array
	 */
	public static function simplifyFormDescriptor( array $descriptor ) {
		foreach ( $descriptor as $name => &$params ) {
			// Info fields are useless and can use complicated closure to provide
			// text, skip all of them.
			if ( ( isset( $params['type'] ) && $params['type'] === 'info' ) ||
				( isset( $params['class'] ) && $params['class'] === \HTMLInfoField::class )
			) {
				unset( $descriptor[$name] );
				continue;
			}
			// Message parsing is the heaviest load when constructing the field,
			// but we just want to validate data.
			foreach ( $params as $key => $value ) {
				switch ( $key ) {
					// Special case, should be kept.
					case 'options-message':
						break;
					// Special case, should be transferred.
					case 'options-messages':
						unset( $params[$key] );
						$params['options'] = $value;
						break;
					default:
						if ( preg_match( '/-messages?$/', $key ) ) {
							// Unwanted.
							unset( $params[$key] );
						}
				}
			}
		}
		return $descriptor;
	}

	/**
	 * Loads existing values for a given array of preferences
	 * @throws MWException
	 * @param User $user
	 * @param IContextSource $context
	 * @param array &$defaultPreferences Array to load values for
	 * @return array|null
	 */
	private function loadPreferenceValues( User $user, IContextSource $context, &$defaultPreferences ) {
		// Remove preferences that wikis don't want to use
		foreach ( $this->options->get( MainConfigNames::HiddenPrefs ) as $pref ) {
			unset( $defaultPreferences[$pref] );
		}

		// For validation.
		$simplified = self::simplifyFormDescriptor( $defaultPreferences );
		$form = new HTMLForm( $simplified, $context );

		$disable = !$user->isAllowed( 'editmyoptions' );

		$defaultOptions = $this->userOptionsManager->getDefaultOptions();
		$userOptions = $this->userOptionsManager->getOptions( $user );
		$this->applyFilters( $userOptions, $defaultPreferences, 'filterForForm' );
		// Add in defaults from the user
		foreach ( $simplified as $name => $_ ) {
			$info = &$defaultPreferences[$name];
			if ( $disable && !in_array( $name, $this->getSaveBlacklist() ) ) {
				$info['disabled'] = 'disabled';
			}
			if ( isset( $info['default'] ) ) {
				// Already set, no problem
				continue;
			}
			$field = $form->getField( $name );
			$globalDefault = $defaultOptions[$name] ?? null;
			$prefFromUser = $this->getOptionFromUser( $name, $info, $userOptions );

			// If it validates, set it as the default
			// FIXME: That's not how the validate() function works! Values of nested fields
			// (e.g. CheckMatix) would be missing.
			if ( $prefFromUser !== null && // Make sure we're not just pulling nothing
					$field->validate( $prefFromUser, $this->userOptionsManager->getOptions( $user ) ) === true ) {
				$info['default'] = $prefFromUser;
			} elseif ( $field->validate( $globalDefault, $this->userOptionsManager->getOptions( $user ) ) === true ) {
				$info['default'] = $globalDefault;
			} else {
				$globalDefault = json_encode( $globalDefault );
				throw new MWException(
					"Default '$globalDefault' is invalid for preference $name of user " . $user->getName()
				);
			}
		}

		return $defaultPreferences;
	}

	/**
	 * Pull option from a user account. Handles stuff like array-type preferences.
	 *
	 * @param string $name
	 * @param array $info
	 * @param array $userOptions
	 * @return array|string
	 */
	protected function getOptionFromUser( $name, $info, array $userOptions ) {
		$val = $userOptions[$name] ?? null;

		// Handling for multiselect preferences
		if ( ( isset( $info['type'] ) && $info['type'] == 'multiselect' ) ||
				( isset( $info['class'] ) && $info['class'] == \HTMLMultiSelectField::class ) ) {
			$options = HTMLFormField::flattenOptions( $info['options-messages'] ?? $info['options'] );
			$prefix = $info['prefix'] ?? $name;
			$val = [];

			foreach ( $options as $value ) {
				if ( $userOptions["$prefix$value"] ?? false ) {
					$val[] = $value;
				}
			}
		}

		// Handling for checkmatrix preferences
		if ( ( isset( $info['type'] ) && $info['type'] == 'checkmatrix' ) ||
				( isset( $info['class'] ) && $info['class'] == \HTMLCheckMatrix::class ) ) {
			$columns = HTMLFormField::flattenOptions( $info['columns'] );
			$rows = HTMLFormField::flattenOptions( $info['rows'] );
			$prefix = $info['prefix'] ?? $name;
			$val = [];

			foreach ( $columns as $column ) {
				foreach ( $rows as $row ) {
					if ( $userOptions["$prefix$column-$row"] ?? false ) {
						$val[] = "$column-$row";
					}
				}
			}
		}

		return $val;
	}

	/**
	 * @todo Inject user Language instead of using context.
	 * @param User $user
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 * @return void
	 */
	protected function profilePreferences(
		User $user, IContextSource $context, &$defaultPreferences
	) {
		// retrieving user name for GENDER and misc.
		$userName = $user->getName();

		// Information panel
		$defaultPreferences['username'] = [
			'type' => 'info',
			'label-message' => [ 'username', $userName ],
			'default' => $userName,
			'section' => 'personal/info',
		];

		$lang = $context->getLanguage();

		// Get groups to which the user belongs, Skip the default * group, seems useless here
		$userEffectiveGroups = array_diff(
			$this->userGroupManager->getUserEffectiveGroups( $user ),
			[ '*' ]
		);
		$defaultPreferences['usergroups'] = [
			'type' => 'info',
			'label-message' => [ 'prefs-memberingroups',
				\Message::numParam( count( $userEffectiveGroups ) ), $userName ],
			'default' => function () use ( $user, $userEffectiveGroups, $context, $lang, $userName ) {
				$userGroupMemberships = $this->userGroupManager->getUserGroupMemberships( $user );
				$userGroups = $userMembers = $userTempGroups = $userTempMembers = [];
				foreach ( $userEffectiveGroups as $ueg ) {
					$groupStringOrObject = $userGroupMemberships[$ueg] ?? $ueg;

					$userG = UserGroupMembership::getLink( $groupStringOrObject, $context, 'html' );
					$userM = UserGroupMembership::getLink( $groupStringOrObject, $context, 'html',
						$userName );

					// Store expiring groups separately, so we can place them before non-expiring
					// groups in the list. This is to avoid the ambiguity of something like
					// "administrator, bureaucrat (until X date)" -- users might wonder whether the
					// expiry date applies to both groups, or just the last one
					if ( $groupStringOrObject instanceof UserGroupMembership &&
						$groupStringOrObject->getExpiry()
					) {
						$userTempGroups[] = $userG;
						$userTempMembers[] = $userM;
					} else {
						$userGroups[] = $userG;
						$userMembers[] = $userM;
					}
				}
				sort( $userGroups );
				sort( $userMembers );
				sort( $userTempGroups );
				sort( $userTempMembers );
				$userGroups = array_merge( $userTempGroups, $userGroups );
				$userMembers = array_merge( $userTempMembers, $userMembers );
				return $context->msg( 'prefs-memberingroups-type' )
					->rawParams( $lang->commaList( $userGroups ), $lang->commaList( $userMembers ) )
					->escaped();
			},
			'raw' => true,
			'section' => 'personal/info',
		];

		$contribTitle = SpecialPage::getTitleFor( "Contributions", $userName );
		$formattedEditCount = $lang->formatNum( $user->getEditCount() );
		$editCount = $this->linkRenderer->makeLink( $contribTitle, $formattedEditCount );

		$defaultPreferences['editcount'] = [
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-edits',
			'default' => $editCount,
			'section' => 'personal/info',
		];

		if ( $user->getRegistration() ) {
			$displayUser = $context->getUser();
			$userRegistration = $user->getRegistration();
			$defaultPreferences['registrationdate'] = [
				'type' => 'info',
				'label-message' => 'prefs-registration',
				'default' => $context->msg(
					'prefs-registration-date-time',
					$lang->userTimeAndDate( $userRegistration, $displayUser ),
					$lang->userDate( $userRegistration, $displayUser ),
					$lang->userTime( $userRegistration, $displayUser )
				)->text(),
				'section' => 'personal/info',
			];
		}

		$canViewPrivateInfo = $user->isAllowed( 'viewmyprivateinfo' );
		$canEditPrivateInfo = $user->isAllowed( 'editmyprivateinfo' );

		// Actually changeable stuff
		$defaultPreferences['realname'] = [
			// (not really "private", but still shouldn't be edited without permission)
			'type' => $canEditPrivateInfo && $this->authManager->allowsPropertyChange( 'realname' )
				? 'text' : 'info',
			'default' => $user->getRealName(),
			'section' => 'personal/info',
			'label-message' => 'yourrealname',
			'help-message' => 'prefs-help-realname',
		];

		if ( $canEditPrivateInfo && $this->authManager->allowsAuthenticationDataChange(
			new PasswordAuthenticationRequest(), false )->isGood()
		) {
			$defaultPreferences['password'] = [
				'type' => 'info',
				'raw' => true,
				'default' => (string)new \OOUI\ButtonWidget( [
					'href' => SpecialPage::getTitleFor( 'ChangePassword' )->getLinkURL( [
						'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
					] ),
					'label' => $context->msg( 'prefs-resetpass' )->text(),
				] ),
				'label-message' => 'yourpassword',
				// email password reset feature only works for users that have an email set up
				'help' => $this->options->get( MainConfigNames::AllowRequiringEmailForResets ) &&
						$user->getEmail()
					? $context->msg( 'prefs-help-yourpassword',
						'[[#mw-prefsection-personal-email|{{int:prefs-email}}]]' )->parse()
					: '',
				'section' => 'personal/info',
			];
		}
		// Only show prefershttps if secure login is turned on
		if ( !$this->options->get( MainConfigNames::ForceHTTPS )
			&& $this->options->get( MainConfigNames::SecureLogin )
		) {
			$defaultPreferences['prefershttps'] = [
				'type' => 'toggle',
				'label-message' => 'tog-prefershttps',
				'help-message' => 'prefs-help-prefershttps',
				'section' => 'personal/info'
			];
		}

		$defaultPreferences['downloaduserdata'] = [
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-user-downloaddata-label',
			'default' => Html::element(
				'a',
				[
					'href' => $this->options->get( MainConfigNames::ScriptPath ) .
						'/api.php?action=query&meta=userinfo&uiprop=*',
				],
				$context->msg( 'prefs-user-downloaddata-info' )->text()
			),
			'help-message' => [ 'prefs-user-downloaddata-help-message', urlencode( $user->getTitleKey() ) ],
			'section' => 'personal/info',
		];

		$defaultPreferences['restoreprefs'] = [
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-user-restoreprefs-label',
			'default' => Html::element(
				'a',
				[
					'href' => SpecialPage::getTitleFor( 'Preferences' )
						->getSubpage( 'reset' )->getLocalURL()
				],
				$context->msg( 'prefs-user-restoreprefs-info' )->text()
			),
			'section' => 'personal/info',
		];

		$languages = $this->languageNameUtils->getLanguageNames(
			LanguageNameUtils::AUTONYMS,
			LanguageNameUtils::SUPPORTED
		);
		$languageCode = $this->options->get( MainConfigNames::LanguageCode );
		if ( !array_key_exists( $languageCode, $languages ) ) {
			$languages[$languageCode] = $languageCode;
			// Sort the array again
			ksort( $languages );
		}

		$options = [];
		foreach ( $languages as $code => $name ) {
			$display = LanguageCode::bcp47( $code ) . ' - ' . $name;
			$options[$display] = $code;
		}
		$defaultPreferences['language'] = [
			'type' => 'select',
			'section' => 'personal/i18n',
			'options' => $options,
			'label-message' => 'yourlanguage',
		];

		$neutralGenderMessage = $context->msg( 'gender-notknown' )->escaped() . (
			!$context->msg( 'gender-unknown' )->isDisabled()
				? "<br>" . $context->msg( 'parentheses' )
					->params( $context->msg( 'gender-unknown' )->plain() )
					->escaped()
				: ''
		);

		$defaultPreferences['gender'] = [
			'type' => 'radio',
			'section' => 'personal/i18n',
			'options' => [
				$neutralGenderMessage => 'unknown',
				$context->msg( 'gender-female' )->escaped() => 'female',
				$context->msg( 'gender-male' )->escaped() => 'male',
			],
			'label-message' => 'yourgender',
			'help-message' => 'prefs-help-gender',
		];

		// see if there are multiple language variants to choose from
		if ( !$this->languageConverterFactory->isConversionDisabled() ) {

			foreach ( LanguageConverter::$languagesWithVariants as $langCode ) {
				if ( $langCode == $this->contLang->getCode() ) {
					if ( !$this->languageConverter->hasVariants() ) {
						continue;
					}

					$variants = $this->languageConverter->getVariants();
					$variantArray = [];
					foreach ( $variants as $v ) {
						$v = str_replace( '_', '-', strtolower( $v ) );
						$variantArray[$v] = $lang->getVariantname( $v, false );
					}

					$options = [];
					foreach ( $variantArray as $code => $name ) {
						$display = LanguageCode::bcp47( $code ) . ' - ' . $name;
						$options[$display] = $code;
					}

					$defaultPreferences['variant'] = [
						'label-message' => 'yourvariant',
						'type' => 'select',
						'options' => $options,
						'section' => 'personal/i18n',
						'help-message' => 'prefs-help-variant',
					];
				} else {
					$defaultPreferences["variant-$langCode"] = [
						'type' => 'api',
					];
				}
			}
		}

		// show a preview of the old signature first
		$oldsigWikiText = $this->parser->preSaveTransform(
			'~~~',
			$context->getTitle(),
			$user,
			ParserOptions::newFromContext( $context )
		);
		$oldsigHTML = Parser::stripOuterParagraph(
			$context->getOutput()->parseAsContent( $oldsigWikiText )
		);
		$signatureFieldConfig = [];
		// Validate existing signature and show a message about it
		$signature = $this->userOptionsManager->getOption( $user, 'nickname' );
		$useFancySig = $this->userOptionsManager->getBoolOption( $user, 'fancysig' );
		if ( $useFancySig && $signature !== '' ) {
			$parserOpts = ParserOptions::newFromContext( $context );
			$validator = $this->signatureValidatorFactory
				->newSignatureValidator( $user, $context, $parserOpts );
			$signatureErrors = $validator->validateSignature( $signature );
			if ( $signatureErrors ) {
				$sigValidation = $this->options->get( MainConfigNames::SignatureValidation );
				$oldsigHTML .= '<p><strong>' .
					// Messages used here:
					// * prefs-signature-invalid-warning
					// * prefs-signature-invalid-new
					// * prefs-signature-invalid-disallow
					$context->msg( "prefs-signature-invalid-$sigValidation" )->parse() .
					'</strong></p>';

				// On initial page load, show the warnings as well
				// (when posting, you get normal validation errors instead)
				foreach ( $signatureErrors as &$sigError ) {
					$sigError = new \OOUI\HtmlSnippet( $sigError );
				}
				if ( !$context->getRequest()->wasPosted() ) {
					$signatureFieldConfig = [
						'warnings' => $sigValidation !== 'disallow' ? $signatureErrors : null,
						'errors' => $sigValidation === 'disallow' ? $signatureErrors : null,
					];
				}
			}
		}
		$defaultPreferences['oldsig'] = [
			'type' => 'info',
			// Normally HTMLFormFields do not display warnings, so we need to use 'rawrow'
			// and provide the entire OOUI\FieldLayout here
			'rawrow' => true,
			'default' => new \OOUI\FieldLayout(
				new \OOUI\LabelWidget( [
					'label' => new \OOUI\HtmlSnippet( $oldsigHTML ),
				] ),
				[
					'align' => 'top',
					'label' => new \OOUI\HtmlSnippet( $context->msg( 'tog-oldsig' )->parse() )
				] + $signatureFieldConfig
			),
			'section' => 'personal/signature',
		];
		$defaultPreferences['nickname'] = [
			'type' => $this->authManager->allowsPropertyChange( 'nickname' ) ? 'text' : 'info',
			'maxlength' => $this->options->get( MainConfigNames::MaxSigChars ),
			'label-message' => 'yournick',
			'validation-callback' => function ( $signature, $alldata, HTMLForm $form ) {
				return $this->validateSignature( $signature, $alldata, $form );
			},
			'section' => 'personal/signature',
			'filter-callback' => function ( $signature, array $alldata, HTMLForm $form ) {
				return $this->cleanSignature( $signature, $alldata, $form );
			},
		];
		$defaultPreferences['fancysig'] = [
			'type' => 'toggle',
			'label-message' => 'tog-fancysig',
			// show general help about signature at the bottom of the section
			'help-message' => 'prefs-help-signature',
			'section' => 'personal/signature'
		];

		// Email preferences
		if ( $this->options->get( MainConfigNames::EnableEmail ) ) {
			if ( $canViewPrivateInfo ) {
				$helpMessages = [];
				$helpMessages[] = $this->options->get( MainConfigNames::EmailConfirmToEdit )
						? 'prefs-help-email-required'
						: 'prefs-help-email';

				if ( $this->options->get( MainConfigNames::EnableUserEmail ) ) {
					// additional messages when users can send email to each other
					$helpMessages[] = 'prefs-help-email-others';
				}

				$emailAddress = $user->getEmail() ? htmlspecialchars( $user->getEmail() ) : '';
				if ( $canEditPrivateInfo && $this->authManager->allowsPropertyChange( 'emailaddress' ) ) {
					$button = new \OOUI\ButtonWidget( [
						'href' => SpecialPage::getTitleFor( 'ChangeEmail' )->getLinkURL( [
							'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
						] ),
						'label' =>
							$context->msg( $user->getEmail() ? 'prefs-changeemail' : 'prefs-setemail' )->text(),
					] );

					$emailAddress .= $emailAddress == '' ? $button : ( '<br />' . $button );
				}

				$defaultPreferences['emailaddress'] = [
					'type' => 'info',
					'raw' => true,
					'default' => $emailAddress,
					'label-message' => 'youremail',
					'section' => 'personal/email',
					'help-messages' => $helpMessages,
					// 'cssclass' chosen below
				];
			}

			$disableEmailPrefs = false;

			if ( $this->options->get( MainConfigNames::AllowRequiringEmailForResets ) ) {
				$defaultPreferences['requireemail'] = [
					'type' => 'toggle',
					'label-message' => 'tog-requireemail',
					'help-message' => 'prefs-help-requireemail',
					'section' => 'personal/email',
					'disabled' => $user->getEmail() ? false : true,
				];
			}

			if ( $this->options->get( MainConfigNames::EmailAuthentication ) ) {
				if ( $user->getEmail() ) {
					if ( $user->getEmailAuthenticationTimestamp() ) {
						// date and time are separate parameters to facilitate localisation.
						// $time is kept for backward compat reasons.
						// 'emailauthenticated' is also used in SpecialConfirmemail.php
						$displayUser = $context->getUser();
						$emailTimestamp = $user->getEmailAuthenticationTimestamp();
						$time = $lang->userTimeAndDate( $emailTimestamp, $displayUser );
						$d = $lang->userDate( $emailTimestamp, $displayUser );
						$t = $lang->userTime( $emailTimestamp, $displayUser );
						$emailauthenticated = $context->msg( 'emailauthenticated',
							$time, $d, $t )->parse() . '<br />';
						$emailauthenticationclass = 'mw-email-authenticated';
					} else {
						$disableEmailPrefs = true;
						$emailauthenticated = $context->msg( 'emailnotauthenticated' )->parse() . '<br />' .
							new \OOUI\ButtonWidget( [
								'href' => SpecialPage::getTitleFor( 'Confirmemail' )->getLinkURL(),
								'label' => $context->msg( 'emailconfirmlink' )->text(),
							] );
						$emailauthenticationclass = "mw-email-not-authenticated";
					}
				} else {
					$disableEmailPrefs = true;
					$emailauthenticated = $context->msg( 'noemailprefs' )->escaped();
					$emailauthenticationclass = 'mw-email-none';
				}

				if ( $canViewPrivateInfo ) {
					$defaultPreferences['emailauthentication'] = [
						'type' => 'info',
						'raw' => true,
						'section' => 'personal/email',
						'label-message' => 'prefs-emailconfirm-label',
						'default' => $emailauthenticated,
						// Apply the same CSS class used on the input to the message:
						'cssclass' => $emailauthenticationclass,
					];
				}
			}

			if ( $this->options->get( MainConfigNames::EnableUserEmail ) &&
				$user->isAllowed( 'sendemail' )
			) {
				$defaultPreferences['disablemail'] = [
					'id' => 'wpAllowEmail',
					'type' => 'toggle',
					'invert' => true,
					'section' => 'personal/email',
					'label-message' => 'allowemail',
					'disabled' => $disableEmailPrefs,
				];

				$defaultPreferences['email-allow-new-users'] = [
					'id' => 'wpAllowEmailFromNewUsers',
					'type' => 'toggle',
					'section' => 'personal/email',
					'label-message' => 'email-allow-new-users-label',
					'disabled' => $disableEmailPrefs,
					'disable-if' => [ '!==', 'disablemail', '1' ],
				];

				$defaultPreferences['ccmeonemails'] = [
					'type' => 'toggle',
					'section' => 'personal/email',
					'label-message' => 'tog-ccmeonemails',
					'disabled' => $disableEmailPrefs,
				];

				if ( $this->options->get( MainConfigNames::EnableUserEmailMuteList ) ) {
					$defaultPreferences['email-blacklist'] = [
						'type' => 'usersmultiselect',
						'label-message' => 'email-mutelist-label',
						'section' => 'personal/email',
						'disabled' => $disableEmailPrefs,
						'filter' => MultiUsernameFilter::class,
					];
				}
			}

			if ( $this->options->get( MainConfigNames::EnotifWatchlist ) ) {
				$defaultPreferences['enotifwatchlistpages'] = [
					'type' => 'toggle',
					'section' => 'personal/email',
					'label-message' => 'tog-enotifwatchlistpages',
					'disabled' => $disableEmailPrefs,
				];
			}
			if ( $this->options->get( MainConfigNames::EnotifUserTalk ) ) {
				$defaultPreferences['enotifusertalkpages'] = [
					'type' => 'toggle',
					'section' => 'personal/email',
					'label-message' => 'tog-enotifusertalkpages',
					'disabled' => $disableEmailPrefs,
				];
			}
			if ( $this->options->get( MainConfigNames::EnotifUserTalk ) ||
			$this->options->get( MainConfigNames::EnotifWatchlist ) ) {
				if ( $this->options->get( MainConfigNames::EnotifMinorEdits ) ) {
					$defaultPreferences['enotifminoredits'] = [
						'type' => 'toggle',
						'section' => 'personal/email',
						'label-message' => 'tog-enotifminoredits',
						'disabled' => $disableEmailPrefs,
					];
				}

				if ( $this->options->get( MainConfigNames::EnotifRevealEditorAddress ) ) {
					$defaultPreferences['enotifrevealaddr'] = [
						'type' => 'toggle',
						'section' => 'personal/email',
						'label-message' => 'tog-enotifrevealaddr',
						'disabled' => $disableEmailPrefs,
					];
				}
			}
		}
	}

	/**
	 * @param User $user
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 * @return void
	 */
	protected function skinPreferences( User $user, IContextSource $context, &$defaultPreferences ) {
		// Skin selector, if there is at least one valid skin
		$skinOptions = $this->generateSkinOptions( $user, $context );
		if ( $skinOptions ) {
			$defaultPreferences['skin'] = [
				// @phan-suppress-next-line SecurityCheck-XSS False positive, key is escaped
				'type' => 'radio',
				'options' => $skinOptions,
				'section' => 'rendering/skin',
			];
			$defaultPreferences['skin-responsive'] = [
				'type' => 'check',
				'label-message' => 'prefs-skin-responsive',
				'section' => 'rendering/skin/skin-prefs',
				'help-message' => 'prefs-help-skin-responsive',
			];
		}

		$allowUserCss = $this->options->get( MainConfigNames::AllowUserCss );
		$allowUserJs = $this->options->get( MainConfigNames::AllowUserJs );
		// Create links to user CSS/JS pages for all skins.
		// This code is basically copied from generateSkinOptions().
		// @todo Refactor this and the similar code in generateSkinOptions().
		if ( $allowUserCss || $allowUserJs ) {
			$linkTools = [];
			$userName = $user->getName();

			if ( $allowUserCss ) {
				$cssPage = Title::makeTitleSafe( NS_USER, $userName . '/common.css' );
				$cssLinkText = $context->msg( 'prefs-custom-css' )->text();
				$linkTools[] = $this->linkRenderer->makeLink( $cssPage, $cssLinkText );
			}

			if ( $allowUserJs ) {
				$jsPage = Title::makeTitleSafe( NS_USER, $userName . '/common.js' );
				$jsLinkText = $context->msg( 'prefs-custom-js' )->text();
				$linkTools[] = $this->linkRenderer->makeLink( $jsPage, $jsLinkText );
			}

			$defaultPreferences['commoncssjs'] = [
				'type' => 'info',
				'raw' => true,
				'default' => $context->getLanguage()->pipeList( $linkTools ),
				'label-message' => 'prefs-common-config',
				'section' => 'rendering/skin',
			];
		}
	}

	/**
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 */
	protected function filesPreferences( IContextSource $context, &$defaultPreferences ) {
		$defaultPreferences['imagesize'] = [
			'type' => 'select',
			'options' => $this->getImageSizes( $context ),
			'label-message' => 'imagemaxsize',
			'section' => 'rendering/files',
		];
		$defaultPreferences['thumbsize'] = [
			'type' => 'select',
			'options' => $this->getThumbSizes( $context ),
			'label-message' => 'thumbsize',
			'section' => 'rendering/files',
		];
	}

	/**
	 * @param User $user
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 * @return void
	 */
	protected function datetimePreferences(
		User $user, IContextSource $context, &$defaultPreferences
	) {
		$dateOptions = $this->getDateOptions( $context );
		if ( $dateOptions ) {
			$defaultPreferences['date'] = [
				'type' => 'radio',
				'options' => $dateOptions,
				'section' => 'rendering/dateformat',
			];
		}

		// Info
		$now = wfTimestampNow();
		$lang = $context->getLanguage();
		$nowlocal = Xml::element( 'span', [ 'id' => 'wpLocalTime' ],
			$lang->userTime( $now, $user ) );
		$nowserver = $lang->userTime( $now, $user,
				[ 'format' => false, 'timecorrection' => false ] ) .
			Html::hidden( 'wpServerTime', (int)substr( $now, 8, 2 ) * 60 + (int)substr( $now, 10, 2 ) );

		$defaultPreferences['nowserver'] = [
			'type' => 'info',
			'raw' => 1,
			'label-message' => 'servertime',
			'default' => $nowserver,
			'section' => 'rendering/timeoffset',
		];

		$defaultPreferences['nowlocal'] = [
			'type' => 'info',
			'raw' => 1,
			'label-message' => 'localtime',
			'default' => $nowlocal,
			'section' => 'rendering/timeoffset',
		];

		// Grab existing pref.
		$tzOffset = $this->userOptionsManager->getOption( $user, 'timecorrection' );
		$tz = explode( '|', $tzOffset, 3 );

		$tzOptions = $this->getTimezoneOptions( $context );

		$tzSetting = $tzOffset;
		if ( count( $tz ) > 1 && $tz[0] == 'ZoneInfo' &&
			!in_array( $tzOffset, HTMLFormField::flattenOptions( $tzOptions ) )
		) {
			// Timezone offset can vary with DST
			try {
				$userTZ = new DateTimeZone( $tz[2] );
				$minDiff = floor( $userTZ->getOffset( new DateTime( 'now' ) ) / 60 );
				$tzSetting = "ZoneInfo|$minDiff|{$tz[2]}";
			} catch ( TimeoutException $e ) {
				throw $e;
			} catch ( Exception $e ) {
				// User has an invalid time zone set. Fall back to just using the offset
				$tz[0] = 'Offset';
			}
		}
		if ( count( $tz ) > 1 && $tz[0] == 'Offset' ) {
			$minDiff = (int)$tz[1];
			$tzSetting = sprintf( '%+03d:%02d', floor( $minDiff / 60 ), abs( $minDiff ) % 60 );
		}

		$defaultPreferences['timecorrection'] = [
			'class' => \HTMLSelectOrOtherField::class,
			'label-message' => 'timezonelegend',
			'options' => $tzOptions,
			'default' => $tzSetting,
			'size' => 20,
			'section' => 'rendering/timeoffset',
			'id' => 'wpTimeCorrection',
			'filter' => TimezoneFilter::class,
			'placeholder-message' => 'timezone-useoffset-placeholder',
		];
	}

	/**
	 * @param User $user
	 * @param MessageLocalizer $l10n
	 * @param array &$defaultPreferences
	 */
	protected function renderingPreferences(
		User $user,
		MessageLocalizer $l10n,
		&$defaultPreferences
	) {
		// Diffs
		$defaultPreferences['diffonly'] = [
			'type' => 'toggle',
			'section' => 'rendering/diffs',
			'label-message' => 'tog-diffonly',
		];
		$defaultPreferences['norollbackdiff'] = [
			'type' => 'toggle',
			'section' => 'rendering/diffs',
			'label-message' => 'tog-norollbackdiff',
		];

		// Page Rendering
		if ( $this->options->get( MainConfigNames::AllowUserCssPrefs ) ) {
			$defaultPreferences['underline'] = [
				'type' => 'select',
				'options' => [
					$l10n->msg( 'underline-never' )->text() => 0,
					$l10n->msg( 'underline-always' )->text() => 1,
					$l10n->msg( 'underline-default' )->text() => 2,
				],
				'label-message' => 'tog-underline',
				'section' => 'rendering/advancedrendering',
			];
		}

		$defaultPreferences['showhiddencats'] = [
			'type' => 'toggle',
			'section' => 'rendering/advancedrendering',
			'label-message' => 'tog-showhiddencats'
		];

		if ( $user->isAllowed( 'rollback' ) ) {
			$defaultPreferences['showrollbackconfirmation'] = [
				'type' => 'toggle',
				'section' => 'rendering/advancedrendering',
				'label-message' => 'tog-showrollbackconfirmation',
			];
		}
	}

	/**
	 * @param User $user
	 * @param MessageLocalizer $l10n
	 * @param array &$defaultPreferences
	 */
	protected function editingPreferences( User $user, MessageLocalizer $l10n, &$defaultPreferences ) {
		$defaultPreferences['editsectiononrightclick'] = [
			'type' => 'toggle',
			'section' => 'editing/advancedediting',
			'label-message' => 'tog-editsectiononrightclick',
		];
		$defaultPreferences['editondblclick'] = [
			'type' => 'toggle',
			'section' => 'editing/advancedediting',
			'label-message' => 'tog-editondblclick',
		];

		if ( $this->options->get( MainConfigNames::AllowUserCssPrefs ) ) {
			$defaultPreferences['editfont'] = [
				'type' => 'select',
				'section' => 'editing/editor',
				'label-message' => 'editfont-style',
				'options' => [
					$l10n->msg( 'editfont-monospace' )->text() => 'monospace',
					$l10n->msg( 'editfont-sansserif' )->text() => 'sans-serif',
					$l10n->msg( 'editfont-serif' )->text() => 'serif',
				]
			];
		}

		if ( $user->isAllowed( 'minoredit' ) ) {
			$defaultPreferences['minordefault'] = [
				'type' => 'toggle',
				'section' => 'editing/editor',
				'label-message' => 'tog-minordefault',
			];
		}

		$defaultPreferences['forceeditsummary'] = [
			'type' => 'toggle',
			'section' => 'editing/editor',
			'label-message' => 'tog-forceeditsummary',
		];
		$defaultPreferences['useeditwarning'] = [
			'type' => 'toggle',
			'section' => 'editing/editor',
			'label-message' => 'tog-useeditwarning',
		];

		$defaultPreferences['previewonfirst'] = [
			'type' => 'toggle',
			'section' => 'editing/preview',
			'label-message' => 'tog-previewonfirst',
		];
		$defaultPreferences['previewontop'] = [
			'type' => 'toggle',
			'section' => 'editing/preview',
			'label-message' => 'tog-previewontop',
		];
		$defaultPreferences['uselivepreview'] = [
			'type' => 'toggle',
			'section' => 'editing/preview',
			'label-message' => 'tog-uselivepreview',
		];
	}

	/**
	 * @param User $user
	 * @param MessageLocalizer $l10n
	 * @param array &$defaultPreferences
	 */
	protected function rcPreferences( User $user, MessageLocalizer $l10n, &$defaultPreferences ) {
		$rcMaxAge = $this->options->get( MainConfigNames::RCMaxAge );
		$rcMax = ceil( $rcMaxAge / ( 3600 * 24 ) );
		$defaultPreferences['rcdays'] = [
			'type' => 'float',
			'label-message' => 'recentchangesdays',
			'section' => 'rc/displayrc',
			'min' => 1 / 24,
			'max' => $rcMax,
			'help-message' => [ 'recentchangesdays-max', Message::numParam( $rcMax ) ],
		];
		$defaultPreferences['rclimit'] = [
			'type' => 'int',
			'min' => 1,
			'max' => 1000,
			'label-message' => 'recentchangescount',
			'help-message' => 'prefs-help-recentchangescount',
			'section' => 'rc/displayrc',
			'filter' => IntvalFilter::class,
		];
		$defaultPreferences['usenewrc'] = [
			'type' => 'toggle',
			'label-message' => 'tog-usenewrc',
			'section' => 'rc/advancedrc',
		];
		$defaultPreferences['hideminor'] = [
			'type' => 'toggle',
			'label-message' => 'tog-hideminor',
			'section' => 'rc/changesrc',
		];
		$defaultPreferences['pst-cssjs'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-rc-collapsed'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-wl-collapsed'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-saved-queries'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-wl-saved-queries'] = [
			'type' => 'api',
		];
		// Override RCFilters preferences for RecentChanges 'limit'
		$defaultPreferences['rcfilters-limit'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-saved-queries-versionbackup'] = [
			'type' => 'api',
		];
		$defaultPreferences['rcfilters-wl-saved-queries-versionbackup'] = [
			'type' => 'api',
		];

		if ( $this->options->get( MainConfigNames::RCWatchCategoryMembership ) ) {
			$defaultPreferences['hidecategorization'] = [
				'type' => 'toggle',
				'label-message' => 'tog-hidecategorization',
				'section' => 'rc/changesrc',
			];
		}

		if ( $user->useRCPatrol() ) {
			$defaultPreferences['hidepatrolled'] = [
				'type' => 'toggle',
				'section' => 'rc/changesrc',
				'label-message' => 'tog-hidepatrolled',
			];
		}

		if ( $user->useNPPatrol() ) {
			$defaultPreferences['newpageshidepatrolled'] = [
				'type' => 'toggle',
				'section' => 'rc/changesrc',
				'label-message' => 'tog-newpageshidepatrolled',
			];
		}

		if ( $this->options->get( MainConfigNames::RCShowWatchingUsers ) ) {
			$defaultPreferences['shownumberswatching'] = [
				'type' => 'toggle',
				'section' => 'rc/advancedrc',
				'label-message' => 'tog-shownumberswatching',
			];
		}

		$defaultPreferences['rcenhancedfilters-disable'] = [
			'type' => 'toggle',
			'section' => 'rc/advancedrc',
			'label-message' => 'rcfilters-preference-label',
			'help-message' => 'rcfilters-preference-help',
		];
	}

	/**
	 * @param User $user
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 */
	protected function watchlistPreferences(
		User $user, IContextSource $context, &$defaultPreferences
	) {
		$watchlistdaysMax = ceil( $this->options->get( MainConfigNames::RCMaxAge ) / ( 3600 * 24 ) );

		if ( $user->isAllowed( 'editmywatchlist' ) ) {
			$editWatchlistLinks = '';
			$editWatchlistModes = [
				'edit' => [ 'subpage' => false, 'flags' => [] ],
				'raw' => [ 'subpage' => 'raw', 'flags' => [] ],
				'clear' => [ 'subpage' => 'clear', 'flags' => [ 'destructive' ] ],
			];
			foreach ( $editWatchlistModes as $mode => $options ) {
				// Messages: prefs-editwatchlist-edit, prefs-editwatchlist-raw, prefs-editwatchlist-clear
				$editWatchlistLinks .=
					new \OOUI\ButtonWidget( [
						'href' => SpecialPage::getTitleFor( 'EditWatchlist', $options['subpage'] )->getLinkURL(),
						'flags' => $options[ 'flags' ],
						'label' => new \OOUI\HtmlSnippet(
							$context->msg( "prefs-editwatchlist-{$mode}" )->parse()
						),
					] );
			}

			$defaultPreferences['editwatchlist'] = [
				'type' => 'info',
				'raw' => true,
				'default' => $editWatchlistLinks,
				'label-message' => 'prefs-editwatchlist-label',
				'section' => 'watchlist/editwatchlist',
			];
		}

		$defaultPreferences['watchlistdays'] = [
			'type' => 'float',
			'min' => 1 / 24,
			'max' => $watchlistdaysMax,
			'section' => 'watchlist/displaywatchlist',
			'help-message' => [ 'prefs-watchlist-days-max', Message::numParam( $watchlistdaysMax ) ],
			'label-message' => 'prefs-watchlist-days',
		];
		$defaultPreferences['wllimit'] = [
			'type' => 'int',
			'min' => 1,
			'max' => 1000,
			'label-message' => 'prefs-watchlist-edits',
			'help-message' => 'prefs-watchlist-edits-max',
			'section' => 'watchlist/displaywatchlist',
			'filter' => IntvalFilter::class,
		];
		$defaultPreferences['extendwatchlist'] = [
			'type' => 'toggle',
			'section' => 'watchlist/advancedwatchlist',
			'label-message' => 'tog-extendwatchlist',
		];
		$defaultPreferences['watchlisthideminor'] = [
			'type' => 'toggle',
			'section' => 'watchlist/changeswatchlist',
			'label-message' => 'tog-watchlisthideminor',
		];
		$defaultPreferences['watchlisthidebots'] = [
			'type' => 'toggle',
			'section' => 'watchlist/changeswatchlist',
			'label-message' => 'tog-watchlisthidebots',
		];
		$defaultPreferences['watchlisthideown'] = [
			'type' => 'toggle',
			'section' => 'watchlist/changeswatchlist',
			'label-message' => 'tog-watchlisthideown',
		];
		$defaultPreferences['watchlisthideanons'] = [
			'type' => 'toggle',
			'section' => 'watchlist/changeswatchlist',
			'label-message' => 'tog-watchlisthideanons',
		];
		$defaultPreferences['watchlisthideliu'] = [
			'type' => 'toggle',
			'section' => 'watchlist/changeswatchlist',
			'label-message' => 'tog-watchlisthideliu',
		];

		if ( !\SpecialWatchlist::checkStructuredFilterUiEnabled( $user ) ) {
			$defaultPreferences['watchlistreloadautomatically'] = [
				'type' => 'toggle',
				'section' => 'watchlist/advancedwatchlist',
				'label-message' => 'tog-watchlistreloadautomatically',
			];
		}

		$defaultPreferences['watchlistunwatchlinks'] = [
			'type' => 'toggle',
			'section' => 'watchlist/advancedwatchlist',
			'label-message' => 'tog-watchlistunwatchlinks',
		];

		if ( $this->options->get( MainConfigNames::RCWatchCategoryMembership ) ) {
			$defaultPreferences['watchlisthidecategorization'] = [
				'type' => 'toggle',
				'section' => 'watchlist/changeswatchlist',
				'label-message' => 'tog-watchlisthidecategorization',
			];
		}

		if ( $user->useRCPatrol() ) {
			$defaultPreferences['watchlisthidepatrolled'] = [
				'type' => 'toggle',
				'section' => 'watchlist/changeswatchlist',
				'label-message' => 'tog-watchlisthidepatrolled',
			];
		}

		$watchTypes = [
			'edit' => 'watchdefault',
			'move' => 'watchmoves',
			'delete' => 'watchdeletion'
		];

		// Kinda hacky
		if ( $user->isAllowedAny( 'createpage', 'createtalk' ) ) {
			$watchTypes['read'] = 'watchcreations';
		}

		if ( $user->isAllowed( 'rollback' ) ) {
			$watchTypes['rollback'] = 'watchrollback';
		}

		if ( $user->isAllowed( 'upload' ) ) {
			$watchTypes['upload'] = 'watchuploads';
		}

		foreach ( $watchTypes as $action => $pref ) {
			if ( $user->isAllowed( $action ) ) {
				// Messages:
				// tog-watchdefault, tog-watchmoves, tog-watchdeletion, tog-watchcreations, tog-watchuploads
				// tog-watchrollback
				$defaultPreferences[$pref] = [
					'type' => 'toggle',
					'section' => 'watchlist/pageswatchlist',
					'label-message' => "tog-$pref",
				];
			}
		}

		$defaultPreferences['watchlisttoken'] = [
			'type' => 'api',
		];

		$tokenButton = new \OOUI\ButtonWidget( [
			'href' => SpecialPage::getTitleFor( 'ResetTokens' )->getLinkURL( [
				'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText()
			] ),
			'label' => $context->msg( 'prefs-watchlist-managetokens' )->text(),
		] );
		$defaultPreferences['watchlisttoken-info'] = [
			'type' => 'info',
			'section' => 'watchlist/tokenwatchlist',
			'label-message' => 'prefs-watchlist-token',
			'help-message' => 'prefs-help-tokenmanagement',
			'raw' => true,
			'default' => (string)$tokenButton,
		];

		$defaultPreferences['wlenhancedfilters-disable'] = [
			'type' => 'toggle',
			'section' => 'watchlist/advancedwatchlist',
			'label-message' => 'rcfilters-watchlist-preference-label',
			'help-message' => 'rcfilters-watchlist-preference-help',
		];
	}

	/**
	 * @param IContextSource $context
	 * @param array &$defaultPreferences
	 */
	protected function searchPreferences( $context, &$defaultPreferences ) {
		$defaultPreferences['search-special-page'] = [
			'type' => 'api',
		];

		foreach ( $this->nsInfo->getValidNamespaces() as $n ) {
			$defaultPreferences['searchNs' . $n] = [
				'type' => 'api',
			];
		}

		if ( $this->options->get( MainConfigNames::SearchMatchRedirectPreference ) ) {
			$defaultPreferences['search-match-redirect'] = [
				'type' => 'toggle',
				'section' => 'searchoptions/searchmisc',
				'label-message' => 'search-match-redirect-label',
				'help-message' => 'search-match-redirect-help',
			];
		} else {
			$defaultPreferences['search-match-redirect'] = [
				'type' => 'api',
			];
		}

		$defaultPreferences['searchlimit'] = [
			'type' => 'int',
			'min' => 1,
			'max' => 500,
			'section' => 'searchoptions/searchmisc',
			'label-message' => 'searchlimit-label',
			'help-message' => $context->msg( 'searchlimit-help', 500 ),
			'filter' => IntvalFilter::class,
		];
	}

	/*
	 * Custom skin string comparison function that takes into account current and preferred skins.
	 *
	 * @param string $a
	 * @param string $b
	 * @param string $currentSkin
	 * @param array $preferredSkins
	 * @return int
	 */
	private static function sortSkinNames( $a, $b, $currentSkin, $preferredSkins ) {
		// Display the current skin first in the list
		if ( strcasecmp( $a, $currentSkin ) === 0 ) {
			return -1;
		}
		if ( strcasecmp( $b, $currentSkin ) === 0 ) {
			return 1;
		}
		// Display preferred skins over other skins
		if ( count( $preferredSkins ) ) {
			$aPreferred = array_search( $a, $preferredSkins );
			$bPreferred = array_search( $b, $preferredSkins );
			// Cannot use ! operator because array_search returns the
			// index of the array item if found (i.e. 0) and false otherwise
			if ( $aPreferred !== false && $bPreferred === false ) {
				return -1;
			}
			if ( $aPreferred === false && $bPreferred !== false ) {
				return 1;
			}
			// When both skins are preferred, default to the ordering
			// specified by the preferred skins config array
			if ( $aPreferred !== false && $bPreferred !== false ) {
				return strcasecmp( $aPreferred, $bPreferred );
			}
		}
		// Use normal string comparison if both strings are not preferred
		return strcasecmp( $a, $b );
	}

	/**
	 * @param User $user
	 * @param IContextSource $context
	 * @return array Text/links to display as key; $skinkey as value
	 */
	protected function generateSkinOptions( User $user, IContextSource $context ) {
		$ret = [];

		$mptitle = Title::newMainPage();
		$previewtext = $context->msg( 'skin-preview' )->escaped();

		// Only show skins that aren't disabled
		$validSkinNames = $this->skinFactory->getAllowedSkins();
		$allInstalledSkins = $this->skinFactory->getInstalledSkins();

		// Display the installed skin the user has specifically requested via useskin=….
		$useSkin = $context->getRequest()->getRawVal( 'useskin' );
		if ( isset( $allInstalledSkins[$useSkin] )
			&& $context->msg( "skinname-$useSkin" )->exists()
		) {
			$validSkinNames[$useSkin] = $useSkin;
		}

		// Display the skin if the user has set it as a preference already before it was hidden.
		$currentUserSkin = $this->userOptionsManager->getOption( $user, 'skin' );
		if ( isset( $allInstalledSkins[$currentUserSkin] )
			&& $context->msg( "skinname-$currentUserSkin" )->exists()
		) {
			$validSkinNames[$currentUserSkin] = $currentUserSkin;
		}

		foreach ( $validSkinNames as $skinkey => &$skinname ) {
			$msg = $context->msg( "skinname-{$skinkey}" );
			if ( $msg->exists() ) {
				$skinname = htmlspecialchars( $msg->text() );
			}
		}

		$preferredSkins = MediaWikiServices::getInstance()->getMainConfig()->get(
			MainConfigNames::SkinsPreferred );
		// Sort by the internal name, so that the ordering is the same for each display language,
		// especially if some skin names are translated to use a different alphabet and some are not.
		uksort( $validSkinNames, function ( $a, $b ) use ( $currentUserSkin, $preferredSkins ) {
			return $this->sortSkinNames( $a, $b, $currentUserSkin, $preferredSkins );
		} );

		$defaultSkin = $this->options->get( MainConfigNames::DefaultSkin );
		$allowUserCss = $this->options->get( MainConfigNames::AllowUserCss );
		$allowUserJs = $this->options->get( MainConfigNames::AllowUserJs );
		$foundDefault = false;
		foreach ( $validSkinNames as $skinkey => $sn ) {
			$linkTools = [];

			// Mark the default skin
			if ( strcasecmp( $skinkey, $defaultSkin ) === 0 ) {
				$linkTools[] = $context->msg( 'default' )->escaped();
				$foundDefault = true;
			}

			// Create talk page link if relevant message exists.
			$talkPageMsg = $context->msg( "$skinkey-prefs-talkpage" );
			if ( $talkPageMsg->exists() ) {
				$linkTools[] = $talkPageMsg->parse();
			}

			// Create preview link
			$mplink = htmlspecialchars( $mptitle->getLocalURL( [ 'useskin' => $skinkey ] ) );
			$linkTools[] = "<a target='_blank' href=\"$mplink\">$previewtext</a>";

			// Create links to user CSS/JS pages
			// @todo Refactor this and the similar code in skinPreferences().
			if ( $allowUserCss ) {
				$cssPage = Title::makeTitleSafe( NS_USER, $user->getName() . '/' . $skinkey . '.css' );
				$cssLinkText = $context->msg( 'prefs-custom-css' )->text();
				$linkTools[] = $this->linkRenderer->makeLink( $cssPage, $cssLinkText );
			}

			if ( $allowUserJs ) {
				$jsPage = Title::makeTitleSafe( NS_USER, $user->getName() . '/' . $skinkey . '.js' );
				$jsLinkText = $context->msg( 'prefs-custom-js' )->text();
				$linkTools[] = $this->linkRenderer->makeLink( $jsPage, $jsLinkText );
			}

			$display = $sn . ' ' . $context->msg( 'parentheses' )
				->rawParams( $context->getLanguage()->pipeList( $linkTools ) )
				->escaped();
			$ret[$display] = $skinkey;
		}

		if ( !$foundDefault ) {
			// If the default skin is not available, things are going to break horribly because the
			// default value for skin selector will not be a valid value. Let's just not show it then.
			return [];
		}

		return $ret;
	}

	/**
	 * @param IContextSource $context
	 * @return array
	 */
	protected function getDateOptions( IContextSource $context ) {
		$lang = $context->getLanguage();
		$dateopts = $lang->getDatePreferences();

		$ret = [];

		if ( $dateopts ) {
			if ( !in_array( 'default', $dateopts ) ) {
				$dateopts[] = 'default'; // Make sure default is always valid T21237
			}

			// FIXME KLUGE: site default might not be valid for user language
			global $wgDefaultUserOptions;
			if ( !in_array( $wgDefaultUserOptions['date'], $dateopts ) ) {
				$wgDefaultUserOptions['date'] = 'default';
			}

			$epoch = wfTimestampNow();
			foreach ( $dateopts as $key ) {
				if ( $key == 'default' ) {
					$formatted = $context->msg( 'datedefault' )->escaped();
				} else {
					$formatted = htmlspecialchars( $lang->timeanddate( $epoch, false, $key ) );
				}
				$ret[$formatted] = $key;
			}
		}
		return $ret;
	}

	/**
	 * @param MessageLocalizer $l10n
	 * @return array
	 */
	protected function getImageSizes( MessageLocalizer $l10n ) {
		$ret = [];
		$pixels = $l10n->msg( 'unit-pixel' )->text();

		foreach ( $this->options->get( MainConfigNames::ImageLimits ) as $index => $limits ) {
			// Note: A left-to-right marker (U+200E) is inserted, see T144386
			$display = "{$limits[0]}\u{200E}×{$limits[1]}$pixels";
			$ret[$display] = $index;
		}

		return $ret;
	}

	/**
	 * @param MessageLocalizer $l10n
	 * @return array
	 */
	protected function getThumbSizes( MessageLocalizer $l10n ) {
		$ret = [];
		$pixels = $l10n->msg( 'unit-pixel' )->text();

		foreach ( $this->options->get( MainConfigNames::ThumbLimits ) as $index => $size ) {
			$display = $size . $pixels;
			$ret[$display] = $index;
		}

		return $ret;
	}

	/**
	 * @param mixed $signature
	 * @param array $alldata
	 * @param HTMLForm $form
	 * @return bool|string|string[]
	 */
	protected function validateSignature( $signature, $alldata, HTMLForm $form ) {
		$sigValidation = $this->options->get( MainConfigNames::SignatureValidation );
		$maxSigChars = $this->options->get( MainConfigNames::MaxSigChars );
		if ( is_string( $signature ) && mb_strlen( $signature ) > $maxSigChars ) {
			return $form->msg( 'badsiglength' )->numParams( $maxSigChars )->escaped();
		}

		if ( $signature === null || $signature === '' ) {
			// Make sure leaving the field empty is valid, since that's used as the default (T288151).
			// Code using this preference in Parser::getUserSig() handles this case specially.
			return true;
		}

		// Remaining checks only apply to fancy signatures
		if ( !( isset( $alldata['fancysig'] ) && $alldata['fancysig'] ) ) {
			return true;
		}

		// HERE BE DRAGONS:
		//
		// If this value is already saved as the user's signature, treat it as valid, even if it
		// would be invalid to save now, and even if $wgSignatureValidation is set to 'disallow'.
		//
		// It can become invalid when we introduce new validation, or when the value just transcludes
		// some page containing the real signature and that page is edited (which we can't validate),
		// or when someone's username is changed.
		//
		// Otherwise it would be completely removed when the user opens their preferences page, which
		// would be very unfriendly.
		$user = $form->getUser();
		if (
			$signature === $this->userOptionsManager->getOption( $user, 'nickname' ) &&
			(bool)$alldata['fancysig'] === $this->userOptionsManager->getBoolOption( $user, 'fancysig' )
		) {
			return true;
		}

		if ( $sigValidation === 'new' || $sigValidation === 'disallow' ) {
			// Validate everything
			$parserOpts = ParserOptions::newFromContext( $form->getContext() );
			$validator = $this->signatureValidatorFactory
				->newSignatureValidator( $user, $form->getContext(), $parserOpts );
			$errors = $validator->validateSignature( $signature );
			if ( $errors ) {
				return $errors;
			}
		}

		// Quick check for mismatched HTML tags in the input.
		// Note that this is easily fooled by wikitext templates or bold/italic markup.
		// We're only keeping this until Parsoid is integrated and guaranteed to be available.
		if ( $this->parser->validateSig( $signature ) === false ) {
			return $form->msg( 'badsig' )->escaped();
		}

		return true;
	}

	/**
	 * @param string $signature
	 * @param array $alldata
	 * @param HTMLForm $form
	 * @return string
	 */
	protected function cleanSignature( $signature, $alldata, HTMLForm $form ) {
		if ( isset( $alldata['fancysig'] ) && $alldata['fancysig'] ) {
			$signature = $this->parser->cleanSig( $signature );
		} else {
			// When no fancy sig used, make sure ~{3,5} get removed.
			$signature = Parser::cleanSigInSig( $signature );
		}

		return $signature;
	}

	/**
	 * @param User $user
	 * @param IContextSource $context
	 * @param string $formClass
	 * @param array $remove Array of items to remove
	 * @return HTMLForm
	 */
	public function getForm(
		User $user,
		IContextSource $context,
		$formClass = PreferencesFormOOUI::class,
		array $remove = []
	) {
		// We use ButtonWidgets in some of the getPreferences() functions
		$context->getOutput()->enableOOUI();

		// Note that the $user parameter of getFormDescriptor() is deprecated.
		$formDescriptor = $this->getFormDescriptor( $user, $context );
		if ( count( $remove ) ) {
			$removeKeys = array_fill_keys( $remove, true );
			$formDescriptor = array_diff_key( $formDescriptor, $removeKeys );
		}

		// Remove type=api preferences. They are not intended for rendering in the form.
		foreach ( $formDescriptor as $name => $info ) {
			if ( isset( $info['type'] ) && $info['type'] === 'api' ) {
				unset( $formDescriptor[$name] );
			}
		}

		/**
		 * @var PreferencesFormOOUI $htmlForm
		 */
		$htmlForm = new $formClass( $formDescriptor, $context, 'prefs' );

		// This allows users to opt-in to hidden skins. While this should be discouraged and is not
		// discoverable, this allows users to still use hidden skins while preventing new users from
		// adopting unsupported skins. If no useskin=… parameter was provided, it will not show up
		// in the resulting URL.
		$htmlForm->setAction( $context->getTitle()->getLocalURL( [
			'useskin' => $context->getRequest()->getRawVal( 'useskin' )
		] ) );

		$htmlForm->setModifiedUser( $user );
		$htmlForm->setOptionsEditable( $user->isAllowed( 'editmyoptions' ) );
		$htmlForm->setPrivateInfoEditable( $user->isAllowed( 'editmyprivateinfo' ) );
		$htmlForm->setId( 'mw-prefs-form' );
		$htmlForm->setAutocomplete( 'off' );
		$htmlForm->setSubmitTextMsg( 'saveprefs' );
		// Used message keys: 'accesskey-preferences-save', 'tooltip-preferences-save'
		$htmlForm->setSubmitTooltip( 'preferences-save' );
		$htmlForm->setSubmitID( 'prefcontrol' );
		$htmlForm->setSubmitCallback(
			function ( array $formData, PreferencesFormOOUI $form ) use ( $formDescriptor ) {
				return $this->submitForm( $formData, $form, $formDescriptor );
			}
		);

		return $htmlForm;
	}

	/**
	 * @param IContextSource $context
	 * @return array
	 */
	protected function getTimezoneOptions( IContextSource $context ) {
		$opt = [];

		$localTZoffset = $this->options->get( MainConfigNames::LocalTZoffset );
		$timeZoneList = $this->getTimeZoneList( $context->getLanguage() );

		$timestamp = MWTimestamp::getLocalInstance();
		// Check that the LocalTZoffset is the same as the local time zone offset
		if ( $localTZoffset === (int)$timestamp->format( 'Z' ) / 60 ) {
			$timezoneName = $timestamp->getTimezone()->getName();
			// Localize timezone
			if ( isset( $timeZoneList[$timezoneName] ) ) {
				$timezoneName = $timeZoneList[$timezoneName]['name'];
			}
			$server_tz_msg = $context->msg(
				'timezoneuseserverdefault',
				$timezoneName
			)->text();
		} else {
			$tzstring = sprintf(
				'%+03d:%02d',
				floor( $localTZoffset / 60 ),
				abs( $localTZoffset ) % 60
			);
			$server_tz_msg = $context->msg( 'timezoneuseserverdefault', $tzstring )->text();
		}
		$opt[$server_tz_msg] = "System|$localTZoffset";
		$opt[$context->msg( 'timezoneuseoffset' )->text()] = 'other';
		$opt[$context->msg( 'guesstimezone' )->text()] = 'guess';

		foreach ( $timeZoneList as $timeZoneInfo ) {
			$region = $timeZoneInfo['region'];
			if ( !isset( $opt[$region] ) ) {
				$opt[$region] = [];
			}
			$opt[$region][$timeZoneInfo['name']] = $timeZoneInfo['timecorrection'];
		}
		return $opt;
	}

	/**
	 * Handle the form submission if everything validated properly
	 *
	 * @param array $formData
	 * @param PreferencesFormOOUI $form
	 * @param array[] $formDescriptor
	 * @return bool|Status|string
	 */
	protected function saveFormData( $formData, PreferencesFormOOUI $form, array $formDescriptor ) {
		$user = $form->getModifiedUser();
		$hiddenPrefs = $this->options->get( MainConfigNames::HiddenPrefs );
		$result = true;

		if ( !$user->isAllowedAny( 'editmyprivateinfo', 'editmyoptions' )
		) {
			return Status::newFatal( 'mypreferencesprotected' );
		}

		// Filter input
		$this->applyFilters( $formData, $formDescriptor, 'filterFromForm' );

		// Fortunately, the realname field is MUCH simpler
		// (not really "private", but still shouldn't be edited without permission)

		if ( !in_array( 'realname', $hiddenPrefs )
			&& $user->isAllowed( 'editmyprivateinfo' )
			&& array_key_exists( 'realname', $formData )
		) {
			$realName = $formData['realname'];
			$user->setRealName( $realName );
		}

		if ( $user->isAllowed( 'editmyoptions' ) ) {
			$oldUserOptions = $this->userOptionsManager->getOptions( $user );

			foreach ( $this->getSaveBlacklist() as $b ) {
				unset( $formData[$b] );
			}

			// If users have saved a value for a preference which has subsequently been disabled
			// via $wgHiddenPrefs, we don't want to destroy that setting in case the preference
			// is subsequently re-enabled
			foreach ( $hiddenPrefs as $pref ) {
				// If the user has not set a non-default value here, the default will be returned
				// and subsequently discarded
				$formData[$pref] = $this->userOptionsManager->getOption( $user, $pref, null, true );
			}

			// If the user changed the rclimit preference, also change the rcfilters-rclimit preference
			if (
				isset( $formData['rclimit'] ) &&
				intval( $formData[ 'rclimit' ] ) !== $this->userOptionsManager->getIntOption( $user, 'rclimit' )
			) {
				$formData['rcfilters-limit'] = $formData['rclimit'];
			}

			// Keep old preferences from interfering due to back-compat code, etc.
			$this->userOptionsManager->resetOptions( $user, $form->getContext(), 'unused' );

			foreach ( $formData as $key => $value ) {
				$this->userOptionsManager->setOption( $user, $key, $value );
			}

			$this->hookRunner->onPreferencesFormPreSave(
				$formData, $form, $user, $result, $oldUserOptions );
		}

		$user->saveSettings();

		return $result;
	}

	/**
	 * Applies filters to preferences either before or after form usage
	 *
	 * @param array &$preferences
	 * @param array $formDescriptor
	 * @param string $verb Name of the filter method to call, either 'filterFromForm' or
	 * 		'filterForForm'
	 */
	protected function applyFilters( array &$preferences, array $formDescriptor, $verb ) {
		foreach ( $formDescriptor as $preference => $desc ) {
			if ( !isset( $desc['filter'] ) || !isset( $preferences[$preference] ) ) {
				continue;
			}
			$filterDesc = $desc['filter'];
			if ( $filterDesc instanceof Filter ) {
				$filter = $filterDesc;
			} elseif ( class_exists( $filterDesc ) ) {
				$filter = new $filterDesc();
			} elseif ( is_callable( $filterDesc ) ) {
				$filter = $filterDesc();
			} else {
				throw new UnexpectedValueException(
					"Unrecognized filter type for preference '$preference'"
				);
			}
			$preferences[$preference] = $filter->$verb( $preferences[$preference] );
		}
	}

	/**
	 * Save the form data and reload the page
	 *
	 * @param array $formData
	 * @param PreferencesFormOOUI $form
	 * @param array $formDescriptor
	 * @return Status
	 */
	protected function submitForm(
		array $formData,
		PreferencesFormOOUI $form,
		array $formDescriptor
	) {
		$res = $this->saveFormData( $formData, $form, $formDescriptor );

		if ( $res === true ) {
			$context = $form->getContext();
			$urlOptions = [];

			$urlOptions += $form->getExtraSuccessRedirectParameters();

			$url = $form->getTitle()->getFullURL( $urlOptions );

			// Set session data for the success message
			$context->getRequest()->getSession()->set( 'specialPreferencesSaveSuccess', 1 );

			$context->getOutput()->redirect( $url );
		}

		return ( $res === true ? Status::newGood() : $res );
	}

	/**
	 * Get a list of all time zones
	 * @param Language $language Language used for the localized names
	 * @return array[] A list of all time zones. The system name of the time zone is used as key and
	 *  the value is an array which contains localized name, the timecorrection value used for
	 *  preferences and the region
	 * @since 1.26
	 */
	protected function getTimeZoneList( Language $language ) {
		$identifiers = DateTimeZone::listIdentifiers();
		// @phan-suppress-next-line PhanTypeComparisonFromArray See phan issue #3162
		if ( $identifiers === false ) {
			return [];
		}
		sort( $identifiers );

		$tzRegions = [
			'Africa' => wfMessage( 'timezoneregion-africa' )->inLanguage( $language )->text(),
			'America' => wfMessage( 'timezoneregion-america' )->inLanguage( $language )->text(),
			'Antarctica' => wfMessage( 'timezoneregion-antarctica' )->inLanguage( $language )->text(),
			'Arctic' => wfMessage( 'timezoneregion-arctic' )->inLanguage( $language )->text(),
			'Asia' => wfMessage( 'timezoneregion-asia' )->inLanguage( $language )->text(),
			'Atlantic' => wfMessage( 'timezoneregion-atlantic' )->inLanguage( $language )->text(),
			'Australia' => wfMessage( 'timezoneregion-australia' )->inLanguage( $language )->text(),
			'Europe' => wfMessage( 'timezoneregion-europe' )->inLanguage( $language )->text(),
			'Indian' => wfMessage( 'timezoneregion-indian' )->inLanguage( $language )->text(),
			'Pacific' => wfMessage( 'timezoneregion-pacific' )->inLanguage( $language )->text(),
		];
		asort( $tzRegions );

		$timeZoneList = [];

		$now = new DateTime();

		foreach ( $identifiers as $identifier ) {
			$parts = explode( '/', $identifier, 2 );

			// DateTimeZone::listIdentifiers() returns a number of
			// backwards-compatibility entries. This filters them out of the
			// list presented to the user.
			if ( count( $parts ) !== 2 || !array_key_exists( $parts[0], $tzRegions ) ) {
				continue;
			}

			// Localize region
			$parts[0] = $tzRegions[$parts[0]];

			$dateTimeZone = new DateTimeZone( $identifier );
			$minDiff = floor( $dateTimeZone->getOffset( $now ) / 60 );

			$display = str_replace( '_', ' ', $parts[0] . '/' . $parts[1] );
			$value = "ZoneInfo|$minDiff|$identifier";

			$timeZoneList[$identifier] = [
				'name' => $display,
				'timecorrection' => $value,
				'region' => $parts[0],
			];
		}

		return $timeZoneList;
	}
}

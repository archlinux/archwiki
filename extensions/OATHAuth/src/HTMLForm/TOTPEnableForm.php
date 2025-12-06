<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use MediaWiki\Config\ConfigException;
use MediaWiki\Extension\OATHAuth\IAuthKey;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Html\Html;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use OOUI\FieldLayout;
use OOUI\HtmlSnippet;
use OOUI\Widget;
use UnexpectedValueException;

class TOTPEnableForm extends OATHAuthOOUIHTMLForm {

	use KeySessionStorageTrait;
	use RecoveryCodesTrait;

	/**
	 * @param array|bool|Status|string $submitResult
	 * @return string
	 */
	public function getHTML( $submitResult ) {
		$out = $this->getOutput();
		$out->addModuleStyles( 'ext.oath.totpenable.styles' );
		$out->addModuleStyles( 'ext.oath.recovery.styles' );
		$out->addModules( 'ext.oath.recovery' );
		return parent::getHTML( $submitResult );
	}

	/**
	 * Add content to output when operation was successful
	 */
	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'oathauth-validatedoath' );
	}

	/**
	 * @return array
	 */
	protected function getDescriptors() {
		/** @var TOTPKey $key */
		$key = $this->setKeyDataInSession( 'TOTPKey' );
		'@phan-var TOTPKey $key';
		$secret = $key->getSecret();
		$issuer = $this->oathUser->getIssuer();
		$account = $this->oathUser->getAccount();
		$label = "{$issuer}:{$account}";
		$qrcodeUrl = "otpauth://totp/"
			. rawurlencode( $label )
			. "?secret="
			. rawurlencode( $secret )
			. "&issuer="
			. rawurlencode( $issuer );

		$qrCode = ( new Builder(
			writer: new SvgWriter(),
			writerOptions: [ SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true ],
			data: $qrcodeUrl,
			encoding: new Encoding( 'UTF-8' ),
			errorCorrectionLevel: ErrorCorrectionLevel::High,
			size: 256,
			margin: 0,
			roundBlockSizeMode: RoundBlockSizeMode::None,
		) )->build();

		// messages used: oathauth-step1, oathauth-step-friendly-name oathauth-step2, oathauth-step3, oathauth-step4
		return [
			'app' => [
				'type' => 'info',
				'default' => $this->msg( 'oathauth-step1-test' )->parse(),
				'raw' => true,
				'section' => 'step1',
			],
			'friendly_name' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-step-friendly-name-text',
				'name' => 'friendly-name',
				'section' => 'step-friendly-name',
			],
			'qrcode' => [
				'type' => 'info',
				'default' =>
					$this->msg( 'oathauth-step2-qrcode' )->escaped() . '<br/>'
					. Html::element( 'img', [
						'class' => 'mw-oauth-qrcode',
						'src' => $qrCode->getDataUri(),
						'alt' => $this->msg( 'oathauth-qrcode-alt' )->text(),
						'width' => 256,
						'height' => 256,
					] ),
				'raw' => true,
				'section' => 'step2',
			],
			'manual' => [
				'type' => 'info',
				'default' => $this->generateAltStep2Content( $key, $label ),
				'raw' => true,
				// We need to use a "rawrow" to prevent being wrapped by a label element.
				'rawrow' => true,
				'section' => 'step2',
			],
			'recoverycodes' => [
				'type' => 'info',
				'default' =>
					$this->generateRecoveryCodesContent(
						$this->getRecoveryCodesForDisplay( $this->getRecoveryKeysFromSessionOrDefault() ),
						true
					),
				'raw' => true,
				// We need to use a "rawrow" to prevent being wrapped by a label element.
				'rawrow' => true,
				'section' => 'step3',
			],
			'token' => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'oathauth-entertoken',
				'name' => 'token',
				'section' => 'step4',
				'dir' => 'ltr',
				'autocomplete' => 'one-time-code',
				'spellcheck' => false,
			]
		];
	}

	private function getRecoveryKeysFromSessionOrDefault(): RecoveryCodeKeys {
		$keyDataRecCodes = $this->getKeyDataInSession( 'RecoveryCodeKeys' );
		if ( $keyDataRecCodes ) {
			return RecoveryCodeKeys::newFromArray( $keyDataRecCodes );
		}

		$keyDataRecCodes = [ 'recoverycodekeys' => [] ];
		return $this->setKeyDataInSession(
			'RecoveryCodeKeys',
			$keyDataRecCodes
		);
	}

	private function generateAltStep2Content( IAuthKey $key, string $label ): FieldLayout {
		$snippet = new HtmlSnippet( '<p>'
			. $this->msg( 'oathauth-step2alt' )->escaped() . '</p>'
			. '<strong>' . $this->msg( 'oathauth-secret' )->escaped() . '</strong><br>'
			. '<kbd>' . $this->getSecretForDisplay( $key ) . '</kbd></p>'
			. '<p><strong>' . $this->msg( 'oathauth-account' )->escaped() . '</strong><br>'
			. htmlspecialchars( $label ) . '</p>' );
		// rawrow only accepts fieldlayouts
		return new FieldLayout( new Widget( [ 'content' => $snippet ] ) );
	}

	/**
	 * Retrieve the current secret for display purposes
	 *
	 * The characters of the token are split in groups of 4
	 *
	 * @param IAuthKey $key
	 * @return string
	 */
	protected function getSecretForDisplay( IAuthKey $key ) {
		/** @var TOTPKey $key */
		'@phan-var TOTPKey $key';
		return $this->tokenFormatterFunction( $key->getSecret() );
	}

	/**
	 * @param array $formData
	 * @return array|bool
	 * @throws ConfigException
	 * @throws UnexpectedValueException
	 */
	public function onSubmit( array $formData ) {
		$keyData = $this->getKeyDataInSession( 'TOTPKey' );
		$keyData['friendly_name'] = $formData["friendly_name"];
		$TOTPkey = TOTPKey::newFromArray( $keyData );
		if ( !$TOTPkey instanceof TOTPKey ) {
			return [ 'oathauth-invalidrequest' ];
		}

		if ( $this->getRecoveryKeysFromSessionOrDefault()->isValidRecoveryCode( $formData['token'] ) ) {
			// A recovery code is not allowed for enrollment
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} attempted to enable 2FA using a recovery code from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-noscratchforvalidation' ];
		}
		if ( !$TOTPkey->verify( [ 'token' => $formData['token'] ], $this->oathUser ) ) {
			LoggerFactory::getInstance( 'authentication' )->info(
				'OATHAuth {user} failed to provide a correct token while enabling 2FA from {clientip}', [
					'user' => $this->getUser()->getName(),
					'clientip' => $this->getRequest()->getIP(),
				]
			);
			return [ 'oathauth-failedtovalidateoath' ];
		}

		$moduleDbKeys = $this->oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );

		// only create the recovery code module entry if this is the first 2FA key a user is creating
		if ( count( $moduleDbKeys ) > RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException(
				$this->msg( 'oathauth-recoverycodes-too-many-instances' )->escaped()
			);
		}

		if ( count( $moduleDbKeys ) < RecoveryCodeKeys::RECOVERY_CODE_MODULE_COUNT ) {
			$keyData = $this->getKeyDataInSession( 'RecoveryCodeKeys' );
			$recCodeKeys = RecoveryCodeKeys::newFromArray( $keyData );
			$this->setKeyDataInSessionToNull( 'RecoveryCodeKeys' );
			$moduleRegistry = OATHAuthServices::getInstance()->getModuleRegistry();
			$this->oathRepo->createKey(
				$this->oathUser,
				$moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME ),
				$recCodeKeys->jsonSerialize(),
				$this->getRequest()->getIP()
			);
		}

		$this->setKeyDataInSessionToNull( 'TOTPKey' );
		$this->oathRepo->createKey(
			$this->oathUser,
			$this->module,
			$TOTPkey->jsonSerialize(),
			$this->getRequest()->getIP()
		);

		return true;
	}
}

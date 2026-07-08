<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use OOUI\FieldLayout;
use OOUI\HtmlSnippet;
use OOUI\Widget;
use UnexpectedValueException;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * Helper trait to display and manage recovery codes within various contexts
 *
 * @property OATHUser $oathUser
 */
trait RecoveryCodesTrait {

	/** @return OutputPage */
	abstract public function getOutput();

	/** @return Language */
	abstract public function getLanguage();

	/**
	 * @param string $key
	 * @param string ...$params
	 * @return Message
	 */
	abstract public function msg( $key, ...$params );

	/**
	 * Retrieve current permanent recovery codes for display purposes
	 *
	 * The characters of the token are split in groups of 4
	 */
	public function getRecoveryCodesForDisplay( RecoveryCodeKeys $key ): array {
		$permanentCodes = array_filter( $key->getRecoveryCodes(), static fn ( $code ) => $code->isPermanent() );
		return array_map(
			fn ( $code ) => $this->tokenFormatterFunction( $code->getCode() ),
			array_values( $permanentCodes )
		);
	}

	public function setOutputJsConfigVars( array $recoveryCodes ) {
		$this->getOutput()->addJsConfigVars( 'oathauth-recoverycodes', $this->createTextList( $recoveryCodes ) );
	}

	/**
	 * Formats a key or recovery code by creating groups of 4 separated by space characters
	 *
	 * @param string $token Token to format
	 * @return string The token formatted for display
	 */
	private function tokenFormatterFunction( string $token ): string {
		return implode( ' ', str_split( $token, 4 ) );
	}

	private function generateRecoveryCodesContent( array $recoveryCodes, bool $displayExisting = false ): FieldLayout {
		/** @var RecoveryCodeKeys[] $moduleDbKeys */
		$moduleDbKeys = $this->oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		'@phan-var RecoveryCodeKeys[] $moduleDbKeys';

		if ( count( $moduleDbKeys ) > RecoveryCodes::RECOVERY_CODE_MODULE_COUNT ) {
			throw new UnexpectedValueException( $this->msg( 'oathauth-recoverycodes-too-many-instances' )->escaped() );
		}

		if ( $displayExisting && count( $moduleDbKeys ) === RecoveryCodes::RECOVERY_CODE_MODULE_COUNT ) {
			$key = array_shift( $moduleDbKeys );
			$recoveryCodes = $this->getRecoveryCodesForDisplay( $key );

			$timestamp = $key->getCreatedTimestamp();
			$snippet = '<p>' . $this->msg( 'oathauth-recoverycodes-exist' )->escaped() . '</p>';
		} else {
			$timestamp = wfTimestampNow();
			$snippet =
				'<strong>' . $this->msg( 'oathauth-recoverycodes-important' )->escaped() . '</strong><p>' .
				$this->msg( 'oathauth-recoverycodes' )->escaped() . '</p>';
		}

		$snippet .= '<p>' .
			$this->msg( 'rawmessage' )->rawParams(
				$this->msg(
					'oathauth-recoverytokens-createdat',
					$this->getLanguage()->userTimeAndDate( $timestamp, $this->oathUser->getUser() )
				)->parse()
				. $this->msg( 'word-separator' )->escaped()
				. $this->msg( 'parentheses' )->rawParams(
					wfTimestamp( TimestampFormat::ISO_8601, $timestamp )
				)->escaped()
			) .
			'</p>' .
			$this->createResourceList( $recoveryCodes ) .
			'<br />' .
			$this->createRecoveryCodesCopyButton() .
			$this->createRecoveryCodesDownloadLink( $recoveryCodes );

		$this->setOutputJsConfigVars( $recoveryCodes );

		// rawrow only accepts fieldlayouts
		return new FieldLayout( new Widget( [ 'content' => new HtmlSnippet( $snippet ) ] ) );
	}

	private function createTextList( array $items ): string {
		if ( count( $items ) === 1 ) {
			return array_shift( $items );
		}

		return "* " . implode( "\n* ", $items );
	}

	private function createResourceList( array $resources ): string {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', [], Html::rawElement( 'kbd', [], $resource ) );
		}
		return Html::rawElement( 'ul', [], $resourceList );
	}

	public function createRecoveryCodesCopyButton(): string {
		return Html::rawElement( 'button',
			[
				'class' => 'cdx-button mw-oathauth-recoverycodes-copy-button',
				'type' => 'button',
			], Html::element( 'span', [
				'class' => 'cdx-button__icon',
				'aria-hidden' => 'true',
			] ) . $this->msg( 'oathauth-recoverycodes-copy' )->escaped()
		);
	}

	public function createRecoveryCodesDownloadLink( array $tokens ): string {
		$icon = Html::element( 'span', [
			'class' => [ 'mw-oathauth-recoverycodes-download-icon', 'cdx-button__icon' ],
			'aria-hidden' => 'true',
		] );
		// Use site name as a prefix, so that users know which site the codes belong to.
		$issuer = $this->oathUser->getIssuer();
		$msg = $this->msg( 'oathauth-recoverycodes-filename' )->text();
		// Remove most special characters to ensure the filename is valid.
		$filename = preg_replace( '/[^\p{L} ,.()\'_-]/u', '_', "$issuer - $msg.txt" );
		return Html::rawElement(
			'a',
			[
				'href' => 'data:text/plain;charset=utf-8,'
				// https://bugzilla.mozilla.org/show_bug.cgi?id=1895687
				. rawurlencode( implode( PHP_EOL, $tokens ) ),
				'download' => $filename,
				'class' => [
					'mw-oathauth-recoverycodes-download',
					'cdx-button', 'cdx-button--fake-button', 'cdx-button--fake-button--enabled',
				],
			],
			$icon . $this->msg( 'oathauth-recoverycodes-download' )->escaped()
		);
	}
}

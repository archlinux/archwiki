<?php

/**
 * QuestyCaptcha class
 *
 * @file
 * @author Benjamin Lees <emufarmers@gmail.com>
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\ConfirmEdit\QuestyCaptcha;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;

class QuestyCaptcha extends SimpleCaptcha {
	/**
	 * @var string used for questycaptcha-edit, questycaptcha-addurl, questycaptcha-badlogin,
	 * questycaptcha-createaccount, questycaptcha-create, questycaptcha-sendemail via getMessage()
	 */
	protected static $messagePrefix = 'questycaptcha';

	/**
	 * Validate a CAPTCHA response
	 *
	 * @note Trimming done as per T368112
	 *
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	protected function keyMatch( $answer, $info ) {
		if ( is_array( $info['answer'] ) ) {
			return in_array( strtolower( trim( $answer ) ), array_map( 'strtolower', $info['answer'] ) );
		} else {
			return strtolower( trim( $answer ) ) == strtolower( $info['answer'] );
		}
	}

	/** @inheritDoc */
	public function describeCaptchaType( ?string $action = null ) {
		return [
			'type' => 'question',
			'mime' => 'text/html',
		];
	}

	/** @inheritDoc */
	public function getCaptcha() {
		global $wgCaptchaQuestions;

		// Backwards compatibility
		if ( $wgCaptchaQuestions === array_values( $wgCaptchaQuestions ) ) {
			return $wgCaptchaQuestions[ random_int( 0, count( $wgCaptchaQuestions ) - 1 ) ];
		}

		$question = array_rand( $wgCaptchaQuestions, 1 );
		$answer = $wgCaptchaQuestions[ $question ];
		return [ 'question' => $question, 'answer' => $answer ];
	}

	/** @inheritDoc */
	public function getFormInformation( $tabIndex = 1, ?OutputPage $out = null ) {
		$captcha = $this->getCaptcha();
		if ( !$captcha ) {
			die(
				"No questions found; set some in LocalSettings.php using the format from QuestyCaptcha.php."
			);
		}
		$index = $this->storeCaptcha( $captcha );
		return [
			'html' => "<p><label for=\"wpCaptchaWord\">{$captcha['question']}</label> " .
				Html::element( 'input', [
					'name' => 'wpCaptchaWord',
					'id'   => 'wpCaptchaWord',
					'required',
					'autocomplete' => 'off',
					// tab in before the edit textarea
					'tabindex' => $tabIndex ]
				) . "</p>\n" .
				Html::element( 'input', [
					'type'  => 'hidden',
					'name'  => 'wpCaptchaId',
					'id'    => 'wpCaptchaId',
					'value' => $index ]
				)
		];
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return mixed
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return $captchaData['question'];
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields( array $requests, array $fieldInfo,
		array &$formDescriptor, $action ) {
		/** @var CaptchaAuthenticationRequest $req */
		$req =
			AuthenticationRequest::getRequestByClass(
				$requests,
				CaptchaAuthenticationRequest::class,
				true
			);
		if ( !$req ) {
			return;
		}

		// declare RAW HTML output.
		$formDescriptor['captchaInfo']['raw'] = true;
		$formDescriptor['captchaWord']['label-message'] = null;
	}
}

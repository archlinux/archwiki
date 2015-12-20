<?php

class MathCaptcha extends SimpleCaptcha {

	/** Validate a captcha response */
	function keyMatch( $answer, $info ) {
		return (int)$answer == (int)$info['answer'];
	}

	function addCaptchaAPI( &$resultArr ) {
		list( $sum, $answer ) = $this->pickSum();
		$index = $this->storeCaptcha( array( 'answer' => $answer ) );
		$resultArr['captcha']['type'] = 'math';
		$resultArr['captcha']['mime'] = 'text/tex';
		$resultArr['captcha']['id'] = $index;
		$resultArr['captcha']['question'] = $sum;
	}

	/**
	 * Produce a nice little form
	 * @param OutputPage $out
	 */
	function getForm( OutputPage $out ) {
		list( $sum, $answer ) = $this->pickSum();
		$index = $this->storeCaptcha( array( 'answer' => $answer ) );

		$form = '<table><tr><td>' . $this->fetchMath( $sum ) . '</td>';
		$form .= '<td>' . Html::input( 'wpCaptchaWord', false, false, array( 'tabindex' => '1', 'autocomplete' => 'off', 'required' ) ) . '</td></tr></table>';
		$form .= Html::hidden( 'wpCaptchaId', $index );
		return $form;
	}

	/** Pick a random sum */
	function pickSum() {
		$a = mt_rand( 0, 100 );
		$b = mt_rand( 0, 10 );
		$op = mt_rand( 0, 1 ) ? '+' : '-';
		$sum = "{$a} {$op} {$b} = ";
		$ans = $op == '+' ? ( $a + $b ) : ( $a - $b );
		return array( $sum, $ans );
	}

	/** Fetch the math */
	function fetchMath( $sum ) {
		if ( class_exists( 'MathRenderer' ) ) {
			$math = MathRenderer::getRenderer( $sum, array(), MW_MATH_PNG );
		} else {
			throw new Exception( 'MathCaptcha requires the Math extension for MediaWiki versions 1.18 and above.' );
		}
		$html = $math->render();
		return preg_replace( '/alt=".*?"/', '', $html );
	}
}

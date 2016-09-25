<?php

require_once __DIR__ . '/../ReCaptchaNoCaptcha/HTMLReCaptchaNoCaptchaField.php';

class HTMLReCaptchaNoCaptchaFieldTest extends PHPUnit_Framework_TestCase {
	public function testSubmit() {
		$form = new HTMLForm( [
			'foo' => [
				'class' => HTMLReCaptchaNoCaptchaField::class,
				'key' => '123',
			],
		] );
		$request = new FauxRequest( [
			'foo' => 'abc',
			'g-recaptcha-response' => 'def',
		], true );
		$mockClosure = $this->getMockBuilder( 'object' )->setMethods( [ '__invoke' ] )->getMock();
		$mockClosure->expects( $this->once() )->method( '__invoke' )
			->with( [ 'foo' => 'def' ] )->willReturn( true );

		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );
		$form->setTitle( Title::newFromText( 'Title' ) );
		$form->setContext( $context );
		$form->setSubmitCallback( $mockClosure );
		$form->prepareForm();
		$form->trySubmit();
	}
}

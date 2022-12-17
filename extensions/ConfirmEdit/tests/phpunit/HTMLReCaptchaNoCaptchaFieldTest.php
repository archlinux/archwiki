<?php

/**
 * @covers HTMLReCaptchaNoCaptchaField
 */
class HTMLReCaptchaNoCaptchaFieldTest extends PHPUnit\Framework\TestCase {
	public function testSubmit() {
		$request = new FauxRequest( [
			'foo' => 'abc',
			'g-recaptcha-response' => 'def',
		], true );
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setRequest( $request );

		$form = new HTMLForm( [
			'foo' => [
				'class' => HTMLReCaptchaNoCaptchaField::class,
				'key' => '123',
			],
		], $context );

		$mockClosure = $this->getMockBuilder( stdClass::class )
			->addMethods( [ '__invoke' ] )->getMock();
		$mockClosure->expects( $this->once() )->method( '__invoke' )
			->with( [ 'foo' => 'def' ] )->willReturn( true );

		$form->setTitle( Title::newFromText( 'Title' ) );
		$form->setSubmitCallback( $mockClosure );
		$form->prepareForm();
		$form->trySubmit();
	}
}

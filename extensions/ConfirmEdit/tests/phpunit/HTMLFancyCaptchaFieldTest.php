<?php

/**
 * @covers HTMLFancyCaptchaField
 */
class HTMLFancyCaptchaFieldTest extends PHPUnit\Framework\TestCase {
	public function testGetHTML() {
		$html = $this->getForm( [ 'imageUrl' => 'https://example.com/' ] )->getHTML( false );
		$this->assertRegExp( '/"fancycaptcha-image"/', $html );
		$this->assertRegExp( '#src="https://example.com/"#', $html );
		$this->assertNotRegExp( '/"mw-createacct-captcha-assisted"/', $html );

		$html = $this->getForm( [ 'imageUrl' => '', 'showCreateHelp' => true ] )->getHTML( false );
		$this->assertRegExp( '/"mw-createacct-captcha-assisted"/', $html );

		$html = $this->getForm( [ 'imageUrl' => '', 'label' => 'FooBarBaz' ] )->getHTML( false );
		$this->assertRegExp( '/FooBarBaz/', $html );
	}

	public function testValue() {
		$mockClosure = $this->getMockBuilder( stdClass::class )
			->addMethods( [ '__invoke' ] )->getMock();
		$request = new FauxRequest( [ 'wpcaptchaWord' => 'abc' ], true );
		$form = $this->getForm( [ 'imageUrl' => 'https://example.com/' ], $request );
		$form->setSubmitCallback( $mockClosure );

		$mockClosure->expects( $this->once() )->method( '__invoke' )
			->with( [ 'captchaWord' => 'abc' ] )->willReturn( true );
		$form->trySubmit();
	}

	protected function getForm( $params = [], WebRequest $request = null ) {
		if ( $request ) {
			$context = new DerivativeContext( RequestContext::getMain() );
			$context->setRequest( $request );
		} else {
			$context = RequestContext::getMain();
		}
		$params['class'] = HTMLFancyCaptchaField::class;
		$form = new HTMLForm( [ 'captchaWord' => $params ], $context );
		$form->setTitle( Title::newFromText( 'Foo' ) );
		$form->prepareForm();
		return $form;
	}
}

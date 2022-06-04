<?php

namespace MediaWiki\Tests\Message;

use MediaWiki\Message\Converter;
use MediaWiki\Message\TextFormatter;
use MediaWiki\Message\UserGroupMembershipParam;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Message;
use Wikimedia\Message\MessageValue;
use Wikimedia\Message\ParamType;
use Wikimedia\Message\ScalarParam;

/**
 * @covers \MediaWiki\Message\TextFormatter
 * @covers \Wikimedia\Message\MessageValue
 * @covers \Wikimedia\Message\ListParam
 * @covers \Wikimedia\Message\ScalarParam
 * @covers \Wikimedia\Message\MessageParam
 */
class TextFormatterTest extends MediaWikiIntegrationTestCase {
	private function createTextFormatter( $langCode,
		$includeWikitext = false,
		$format = Message::FORMAT_TEXT
	) {
		$converter = $this->getMockBuilder( Converter::class )
			->onlyMethods( [ 'createMessage' ] )
			->getMock();
		$converter->method( 'createMessage' )
			->willReturnCallback( function ( $key ) use ( $includeWikitext ) {
				$message = $this->getMockBuilder( Message::class )
					->setConstructorArgs( [ $key ] )
					->onlyMethods( [ 'fetchMessage' ] )
					->getMock();

				$message->method( 'fetchMessage' )
					->willReturnCallback( static function () use ( $message, $includeWikitext ) {
						/** @var Message $message */
						$result = "{$message->getKey()} $1 $2";
						if ( $includeWikitext ) {
							$result .= " {{SITENAME}}";
						}
						return $result;
					} );

				return $message;
			} );

		return new TextFormatter( $langCode, $converter, $format );
	}

	public function testGetLangCode() {
		$formatter = new TextFormatter( 'fr', new Converter );
		$this->assertSame( 'fr', $formatter->getLangCode() );
	}

	public function testFormatBitrate() {
		$formatter = $this->createTextFormatter( 'en' );
		$mv = ( new MessageValue( 'test' ) )->bitrateParams( 100, 200 );
		$result = $formatter->format( $mv );
		$this->assertSame( 'test 100 bps 200 bps', $result );
	}

	public function testFormatShortDuration() {
		$formatter = $this->createTextFormatter( 'en' );
		$mv = ( new MessageValue( 'test' ) )->shortDurationParams( 100, 200 );
		$result = $formatter->format( $mv );
		$this->assertSame( 'test 1 min 40 s 3 min 20 s', $result );
	}

	public function testFormatList() {
		$formatter = $this->createTextFormatter( 'en' );
		$mv = ( new MessageValue( 'test' ) )->commaListParams( [
			'a',
			new ScalarParam( ParamType::BITRATE, 100 ),
		] );
		$result = $formatter->format( $mv );
		$this->assertSame( 'test a, 100 bps $2', $result );
	}

	public function provideTestFormatMessage() {
		yield [ ( new MessageValue( 'test' ) )
			->params( new MessageValue( 'test2', [ 'a', 'b' ] ) )
			->commaListParams( [
				'x',
				new ScalarParam( ParamType::BITRATE, 100 ),
				new MessageValue( 'test3', [ 'c', new MessageValue( 'test4', [ 'd', 'e' ] ) ] )
			] ),
			'test test2 a b x(comma-separator)(bitrate-bits)(comma-separator)test3 c test4 d e'
		];

		yield [ ( new MessageValue( 'test' ) )
			->userGroupParams( 'bot' ),
			'test (group-bot) $2'
		];

		yield [ ( new MessageValue( 'test' ) )
			->objectParams(
				new UserGroupMembershipParam( 'bot', new UserIdentityValue( 1, 'user' ) )
			),
			'test (group-bot-member: user) $2'
		];
	}

	/**
	 * @dataProvider provideTestFormatMessage
	 */
	public function testFormatMessage( $message, $expected ) {
		$formatter = $this->createTextFormatter( 'qqx' );
		$result = $formatter->format( $message );
		$this->assertSame( $expected, $result );
	}

	public function testFormatMessageFormatsWikitext() {
		global $wgSitename;
		$formatter = $this->createTextFormatter( 'en', true );
		$mv = MessageValue::new( 'test' )
			->plaintextParams( '1', '2' );
		$this->assertSame( "test 1 2 $wgSitename", $formatter->format( $mv ) );
	}

	public function testFormatMessageNotWikitext() {
		$formatter = $this->createTextFormatter( 'en', true, Message::FORMAT_PLAIN );
		$mv = MessageValue::new( 'test' )
			->plaintextParams( '1', '2' );
		$this->assertSame( "test 1 2 {{SITENAME}}", $formatter->format( $mv ) );
	}
}

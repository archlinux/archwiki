<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Consequences\Consequence;

use ConsequenceGetMessageTestTrait;
use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Warn;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequenceNotPrecheckedException;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Session\Session;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Warn
 */
class WarnTest extends MediaWikiUnitTestCase {
	use ConsequenceGetMessageTestTrait;

	private function getWarn( ?Parameters $params = null ): Warn {
		return new Warn(
			$params ?? $this->createMock( Parameters::class ),
			'foo-bar-message',
			$this->createMock( Session::class )
		);
	}

	private function getParamsAndWarnKey(): array {
		$filter = $this->createMock( ExistingFilter::class );
		$filter->method( 'getID' )->willReturn( 42 );
		$params = new Parameters(
			$filter,
			false,
			new ActionSpecifier(
				'edit',
				new TitleValue( NS_HELP, 'Some title' ),
				new UserIdentityValue( 1, 'Warned user' ),
				'1.2.3.4',
				null
			)
		);
		/** @var Warn $warnWrap */
		$warnWrap = TestingAccessWrapper::newFromObject( $this->getWarn( $params ) );
		return [ $params, $warnWrap->getWarnKey() ];
	}

	public function testExecute_notPrechecked() {
		$warn = $this->getWarn();
		$this->expectException( ConsequenceNotPrecheckedException::class );
		$warn->execute();
	}

	public static function provideWarnsAndSuccess() {
		yield 'should warn' => [ false, true ];
		yield 'already warned' => [ true, false ];
	}

	private function getMocks( bool $keyExists ): array {
		[ $params, $key ] = $this->getParamsAndWarnKey();
		$session = $this->createMock( Session::class );
		if ( $keyExists ) {
			$session->method( 'offsetExists' )->with( $key )->willReturn( true );
			$session->method( 'offsetGet' )->with( $key )->willReturn( true );
		}
		$warn = new Warn(
			$params,
			'some-msg',
			$session
		);
		return [ $warn, $session ];
	}

	/**
	 * @dataProvider provideWarnsAndSuccess
	 */
	public function testShouldDisableOtherConsequences( bool $keyExists, bool $shouldDisable ) {
		[ $warn ] = $this->getMocks( $keyExists );
		$this->assertSame( $shouldDisable, $warn->shouldDisableOtherConsequences() );
	}

	/**
	 * @dataProvider provideWarnsAndSuccess
	 */
	public function testExecute( bool $keyExists, bool $shouldDisable ) {
		[ $warn, $session ] = $this->getMocks( $keyExists );
		$session->expects( $this->once() )
			->method( 'offsetSet' )
			->with( $this->anything(), $shouldDisable );
		$warn->shouldDisableOtherConsequences();
		$this->assertSame( $shouldDisable, $warn->execute() );
	}

	/**
	 * @dataProvider provideGetMessageParameters
	 */
	public function testGetMessage( callable $params ) {
		$params = $params( $this );
		$msg = 'some-warning-message';
		$rangeBlock = new Warn( $params, $msg, $this->createMock( Session::class ) );
		$this->doTestGetMessage( $rangeBlock, $params, $msg );
	}
}

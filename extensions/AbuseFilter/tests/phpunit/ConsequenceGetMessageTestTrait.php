<?php

use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\HookAborterConsequence;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @method MockObject createMock($class)
 * @method assertSame($x,$y,$msg='')
 * @method assertStringMatchesFormat($fmt,$str,$msg='')
 */
trait ConsequenceGetMessageTestTrait {
	public function getLocalFilterParams( UserIdentity $user ): Parameters {
		$localFilter = $this->createMock( ExistingFilter::class );
		$localFilter->method( 'getID' )->willReturn( 1 );
		$localFilter->method( 'getName' )->willReturn( 'Local filter' );
		$localParams = new Parameters(
			$localFilter,
			false,
			new ActionSpecifier(
				'edit',
				$this->createMock( LinkTarget::class ),
				$user,
				'1.2.3.4',
				null
			)
		);
		return $localParams;
	}

	public static function provideGetMessageParameters(): Generator {
		yield 'local filter' => [ static function ( $testCase ) {
			$localFilter = $testCase->createMock( ExistingFilter::class );
			$localFilter->method( 'getID' )->willReturn( 1 );
			$localFilter->method( 'getName' )->willReturn( 'Local filter' );
			$localParams = new Parameters(
				$localFilter,
				false,
				new ActionSpecifier(
					'edit',
					$testCase->createMock( LinkTarget::class ),
					new UserIdentityValue( 1, 'getMessage test user' ),
					'1.2.3.4',
					null
				)
			);
			return $localParams;
		} ];

		yield 'global filter' => [ static function ( $testCase ) {
			$globalFilter = $testCase->createMock( ExistingFilter::class );
			$globalFilter->method( 'getID' )->willReturn( 3 );
			$globalFilter->method( 'getName' )->willReturn( 'Global filter' );
			$globalParams = new Parameters(
				$globalFilter,
				true,
				new ActionSpecifier(
					'edit',
					$testCase->createMock( LinkTarget::class ),
					new UserIdentityValue( 1, 'getMessage test user' ),
					'1.2.3.4',
					null
				)
			);
			return $globalParams;
		} ];
	}

	/**
	 * @param HookAborterConsequence $consequence
	 * @param Parameters $params
	 * @param string $msg
	 */
	protected function doTestGetMessage(
		HookAborterConsequence $consequence,
		Parameters $params,
		string $msg
	): void {
		$actualMsg = $consequence->getMessage();
		$this->assertSame( $msg, $actualMsg[0], 'message' );
		$this->assertSame( $params->getFilter()->getName(), $actualMsg[1], 'name' );
		$format = $params->getIsGlobalFilter() ? 'global-%d' : '%d';
		$this->assertStringMatchesFormat( $format, $actualMsg[2], 'global name' );
	}
}

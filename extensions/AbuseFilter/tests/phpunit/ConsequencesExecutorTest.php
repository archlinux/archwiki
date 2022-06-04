<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutor;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesLookup;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesRegistry;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use Psr\Log\NullLogger;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Test
 * @group AbuseFilter
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutor
 */
class ConsequencesExecutorTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param array $rawConsequences A raw, unfiltered list of consequences
	 * @param array $expectedKeys
	 * @param Title $title
	 * @covers \MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesExecutor
	 * @dataProvider provideConsequences
	 * @todo Rewrite this test
	 */
	public function testGetFilteredConsequences( $rawConsequences, $expectedKeys, Title $title ) {
		$locallyDisabledActions = [
			'flag' => false,
			'throttle' => false,
			'warn' => false,
			'disallow' => false,
			'blockautopromote' => true,
			'block' => true,
			'rangeblock' => true,
			'degroup' => true,
			'tag' => false
		];
		$options = $this->createMock( ServiceOptions::class );
		$options->method( 'get' )
			->with( 'AbuseFilterLocallyDisabledGlobalActions' )
			->willReturn( $locallyDisabledActions );
		$fakeFilter = $this->createMock( ExistingFilter::class );
		$fakeFilter->method( 'getName' )->willReturn( 'unused name' );
		$fakeFilter->method( 'getID' )->willReturn( 1 );
		$fakeLookup = $this->createMock( FilterLookup::class );
		$fakeLookup->method( 'getFilter' )->willReturn( $fakeFilter );
		$consRegistry = $this->createMock( ConsequencesRegistry::class );
		$dangerousActions = TestingAccessWrapper::constant( ConsequencesRegistry::class, 'DANGEROUS_ACTIONS' );
		$consRegistry->method( 'getDangerousActionNames' )->willReturn( $dangerousActions );
		$user = $this->createMock( User::class );
		$vars = VariableHolder::newFromArray( [ 'action' => 'edit' ] );
		$executor = new ConsequencesExecutor(
			$this->createMock( ConsequencesLookup::class ),
			AbuseFilterServices::getConsequencesFactory(),
			$consRegistry,
			$fakeLookup,
			new NullLogger,
			$options,
			$user,
			$title,
			$vars
		);
		$actual = $executor->getFilteredConsequences(
			$executor->replaceArraysWithConsequences( $rawConsequences ) );

		$actualKeys = [];
		foreach ( $actual as $filter => $actions ) {
			$actualKeys[$filter] = array_keys( $actions );
		}

		$this->assertEquals( $expectedKeys, $actualKeys );
	}

	/**
	 * Data provider for testGetFilteredConsequences
	 * @todo Split these
	 * @return array
	 */
	public function provideConsequences() {
		$pageName = 'TestFilteredConsequences';
		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( $pageName );

		return [
			'warn and throttle exclude other actions' => [
				[
					2 => [
						'warn' => [
							'abusefilter-warning'
						],
						'tag' => [
							'some tag'
						]
					],
					13 => [
						'throttle' => [
							'13',
							'14,15',
							'user'
						],
						'disallow' => []
					],
					168 => [
						'degroup' => []
					]
				],
				[
					2 => [ 'warn' ],
					13 => [ 'throttle' ],
					168 => [ 'degroup' ]
				],
				$title
			],
			'warn excludes other actions, block excludes disallow' => [
				[
					3 => [
						'tag' => [
							'some tag'
						]
					],
					'global-2' => [
						'warn' => [
							'abusefilter-beautiful-warning'
						],
						'degroup' => []
					],
					4 => [
						'disallow' => [],
						'block' => [
							'blocktalk',
							'15 minutes',
							'indefinite'
						]
					]
				],
				[
					3 => [ 'tag' ],
					'global-2' => [ 'warn' ],
					4 => [ 'block' ]
				],
				$title
			],
			'some global actions are disabled locally, the longest block is chosen' => [
				[
					'global-1' => [
						'blockautopromote' => [],
						'block' => [
							'blocktalk',
							'indefinite',
							'indefinite'
						]
					],
					1 => [
						'block' => [
							'blocktalk',
							'4 hours',
							'4 hours'
						]
					],
					2 => [
						'degroup' => [],
						'block' => [
							'blocktalk',
							'infinity',
							'never'
						]
					]
				],
				[
					'global-1' => [],
					1 => [],
					2 => [ 'degroup', 'block' ]
				],
				$title
			],
		];
	}
}

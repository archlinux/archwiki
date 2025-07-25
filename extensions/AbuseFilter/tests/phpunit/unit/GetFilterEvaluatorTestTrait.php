<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use LanguageEn;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Language\Language;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Equivset\Equivset;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\Stats\NullStatsdDataFactory;

/**
 * Trait used to get an instance of the {@link FilterEvaluator} class which can be used in unit tests.
 */
trait GetFilterEvaluatorTestTrait {
	/**
	 * Gets an instance of the {@link FilterEvaluator} class which can be used in unit tests.
	 *
	 * @param LoggerInterface|null $logger
	 * @return FilterEvaluator
	 */
	protected function getFilterEvaluator( ?LoggerInterface $logger = null ): FilterEvaluator {
		// We're not interested in caching or logging; tests should call respectively setCache
		// and setLogger if they want to test any of those.
		$keywordsManager = new KeywordsManager( $this->createMock( AbuseFilterHookRunner::class ) );
		$varManager = new VariablesManager(
			$keywordsManager,
			$this->createMock( LazyVariableComputer::class )
		);
		$equivset = $this->createMock( Equivset::class );
		$equivset->method( 'normalize' )->willReturnArgument( 0 );

		$evaluator = new FilterEvaluator(
			$this->getLanguageMock(),
			new EmptyBagOStuff(),
			$logger ?? new NullLogger(),
			$keywordsManager,
			$varManager,
			new NullStatsdDataFactory(),
			$equivset,
			1000
		);
		$evaluator->toggleConditionLimit( false );
		return $evaluator;
	}

	/**
	 * Get a mock of LanguageEn with only the methods we need in the FilterEvaluator
	 *
	 * @return Language|MockObject
	 */
	private function getLanguageMock() {
		$lang = $this->createMock( LanguageEn::class );
		$lang->method( 'uc' )
			->willReturnCallback( static function ( $x ) {
				return mb_strtoupper( $x );
			} );
		$lang->method( 'lc' )
			->willReturnCallback( static function ( $x ) {
				return mb_strtolower( $x );
			} );
		return $lang;
	}
}

<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Parser;

use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Language\Language;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Equivset\Equivset;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Stats\NullStatsdDataFactory;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterParser
 *
 * @covers \MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory
 */
class RuleCheckerFactoryTest extends MediaWikiUnitTestCase {
	public function testNewRuleChecker() {
		$factory = new RuleCheckerFactory(
			$this->createMock( Language::class ),
			$this->createMock( BagOStuff::class ),
			new NullLogger(),
			$this->createMock( KeywordsManager::class ),
			$this->createMock( VariablesManager::class ),
			new NullStatsdDataFactory(),
			$this->createMock( Equivset::class ),
			1000
		);
		$this->assertInstanceOf( FilterEvaluator::class, $factory->newRuleChecker() );
	}
}

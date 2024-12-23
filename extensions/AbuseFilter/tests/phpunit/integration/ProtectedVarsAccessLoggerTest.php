<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use Generator;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger
 * @group Database
 */
class ProtectedVarsAccessLoggerTest extends MediaWikiIntegrationTestCase {
	public function provideProtectedVarsLogTypes(): Generator {
		yield 'enable access to protected vars values' => [
			[
				'logAction' => 'logAccessEnabled',
				'params' => [],
			],
			[
				'expectedCULogType' => 'af-change-access-enable',
				'expectedAFLogType' => 'change-access-enable',
			]
		];

		yield 'disable access to protected vars values' => [
			[
				'logAction' => 'logAccessDisabled',
				'params' => []
			],
			[
				'expectedCULogType' => 'af-change-access-disable',
				'expectedAFLogType' => 'change-access-disable'
			]
		];
	}

	/**
	 * @dataProvider provideProtectedVarsLogTypes
	 */
	public function testLogs_CUDisabled( $options, $expected ) {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )->with( 'CheckUser' )->willReturn( false );
		$this->setService( 'ExtensionRegistry', $extensionRegistry );

		$performer = $this->getTestSysop();
		$logAction = $options['logAction'];
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->$logAction( $performer->getUserIdentity(), ...$options['params'] );

		// Assert that the action wasn't inserted into CheckUsers' temp account logging table
		$this->assertSame(
			0,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => $expected['expectedCULogType'],
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					] )
				->fetchField()
		);
		// and also that it was inserted into abusefilter's protected vars logging table
		$this->assertSame(
			1,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => $expected['expectedAFLogType'],
					'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
					] )
				->fetchField()
		);

		$this->resetServices();
	}

	/**
	 * @dataProvider provideProtectedVarsLogTypes
	 */
	public function testLogs_CUEnabled( $options, $expected ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$performer = $this->getTestSysop();
		$logAction = $options['logAction'];
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->$logAction( $performer->getUserIdentity(), ...$options['params'] );

		// Assert that the action was inserted into CheckUsers' temp account logging table
		$this->assertSame(
			1,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => $expected['expectedCULogType'],
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					] )
				->fetchField()
		);
		// and also that it wasn't inserted into abusefilter's protected vars logging table
		$this->assertSame(
			0,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => $expected['expectedAFLogType'],
					'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
					] )
				->fetchField()
		);
	}

	public function testDebouncedLogs_CUDisabled() {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )->with( 'CheckUser' )->willReturn( false );
		$this->setService( 'ExtensionRegistry', $extensionRegistry );

		// Run the same action twice
		$performer = $this->getTestSysop();
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );

		// Assert that the action wasn't inserted into CheckUsers' temp account logging table
		$this->assertSame(
			0,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => 'af-view-protected-var-value',
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					] )
				->fetchField()
		);
		// and also that it only inserted once into abusefilter's protected vars logging table
		$this->assertSame(
			1,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => 'view-protected-var-value',
					'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
					] )
				->fetchField()
		);

		$this->resetServices();
	}

	public function testDebouncedLogs_CUEnabled() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		// Run the same action twice
		$performer = $this->getTestSysop();
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );
		AbuseFilterServices::getAbuseLoggerFactory()
			->getProtectedVarsAccessLogger()
			->logViewProtectedVariableValue( $performer->getUserIdentity(), '~2024-01', (int)wfTimestamp() );

		// Assert that the action only inserted once into CheckUsers' temp account logging table
		$this->assertSame(
			1,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => 'af-view-protected-var-value',
					'log_type' => TemporaryAccountLogger::LOG_TYPE,
					] )
				->fetchField()
		);
		// and also that it wasn't inserted into abusefilter's protected vars logging table
		$this->assertSame(
			0,
			(int)$this->getDb()->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'logging' )
				->where( [
					'log_action' => 'view-protected-var-value',
					'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
					] )
				->fetchField()
		);
	}
}

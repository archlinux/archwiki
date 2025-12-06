<?php

/**
 * @covers \MediaWiki\Extension\AbuseFilter\LogFormatter\ProtectedVarsAccessLogFormatter
 */
class ProtectedVarsAccessLogFormatterTest extends LogFormatterTestCase {

	/**
	 * Provide different rows from the logging table to test
	 * for backward compatibility.
	 * Do not change the existing data, just add a new database row
	 */
	public static function provideDatabaseRows() {
		return [
			'Format log with single variable' => [
				[
					'type' => 'abusefilter-protected-vars',
					'action' => 'view-protected-var-value',
					'comment' => 'log comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [
						'variables' => [ 'protected_variable_name' ],
					],
				],
				[
					'text' => 'Sysop viewed protected variable associated with User: '
						. 'protected_variable_name',
					'api' => [
						'variables' => [ 'protected_variable_name' ],
					],
				],
			],
			'Format log with two variables' => [
				[
					'type' => 'abusefilter-protected-vars',
					'action' => 'view-protected-var-value',
					'comment' => 'log comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [
						'variables' => [
							'protected_variable_name1',
							'protected_variable_name2',
						],
					],
				],
				[
					'text' => 'Sysop viewed protected variables associated with User: '
						. 'protected_variable_name1, protected_variable_name2',
					'api' => [
						'variables' => [
							'protected_variable_name1',
							'protected_variable_name2',
						],
					],
				],
			],
			'Format log with empty variable array' => [
				[
					'type' => 'abusefilter-protected-vars',
					'action' => 'view-protected-var-value',
					'comment' => 'log comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [
						'variables' => [],
					],
				],
				[
					'text' => 'Sysop viewed protected variables associated with User',
					'api' => [
						'variables' => [],
					],
				],
			],
			'Format log with missing variables' => [
				[
					'type' => 'abusefilter-protected-vars',
					'action' => 'view-protected-var-value',
					'comment' => 'log comment',
					'user' => 0,
					'user_text' => 'Sysop',
					'namespace' => NS_USER,
					'title' => 'User',
					'params' => [],
				],
				[
					'text' => 'Sysop viewed protected variables associated with User',
					'api' => [],
				],
			],
		];
	}

	/**
	 * @dataProvider provideDatabaseRows
	 */
	public function testLogDatabaseRows( $row, $extra ) {
		$this->doTestLogFormatter( $row, $extra, [ 'sysop' ] );
	}
}

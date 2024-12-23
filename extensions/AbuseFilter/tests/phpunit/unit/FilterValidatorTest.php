<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use Generator;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagValidator;
use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Extension\AbuseFilter\FilterValidator;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\ExceptionBase;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\InternalException;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleException;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Status\Status;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterSave
 * @covers \MediaWiki\Extension\AbuseFilter\FilterValidator
 */
class FilterValidatorTest extends MediaWikiUnitTestCase {
	use MockAuthorityTrait;

	/**
	 * @param AbuseFilterPermissionManager|null $permissionManager
	 * @param FilterEvaluator|null $ruleChecker
	 * @param array $restrictions
	 * @param array $validFilterGroups
	 * @return FilterValidator
	 */
	private function getFilterValidator(
		?AbuseFilterPermissionManager $permissionManager = null,
		?FilterEvaluator $ruleChecker = null,
		array $restrictions = [],
		array $validFilterGroups = [ 'default' ]
	): FilterValidator {
		if ( !$ruleChecker ) {
			$ruleChecker = $this->createMock( FilterEvaluator::class );
			$ruleChecker->method( 'checkSyntax' )->willReturn(
				new ParserStatus( null, [], 1 )
			);
			$ruleChecker->method( 'getUsedVars' )->willReturnCallback( static function ( string $rules ) {
				preg_match_all( '/user_\w+/i', $rules, $matches, PREG_PATTERN_ORDER );
				return array_map( 'strtolower', $matches[0] );
			} );
		}
		$checkerFactory = $this->createMock( RuleCheckerFactory::class );
		$checkerFactory->method( 'newRuleChecker' )->willReturn( $ruleChecker );
		if ( !$permissionManager ) {
			$permissionManager = $this->createMock( AbuseFilterPermissionManager::class );
			$permissionManager->method( 'canEditFilter' )->willReturn( true );
		}
		return new FilterValidator(
			$this->createMock( ChangeTagValidator::class ),
			$checkerFactory,
			$permissionManager,
			new ServiceOptions(
				FilterValidator::CONSTRUCTOR_OPTIONS,
				[
					'AbuseFilterActionRestrictions' => array_fill_keys( $restrictions, true ),
					'AbuseFilterValidGroups' => $validFilterGroups,
					'AbuseFilterProtectedVariables' => [ 'user_unnamed_ip' ],
				]
			)
		);
	}

	/**
	 * @param array $actions
	 * @return AbstractFilter|MockObject
	 */
	private function getFilterWithActions( array $actions ): AbstractFilter {
		$ret = $this->createMock( AbstractFilter::class );
		$ret->method( 'getRules' )->willReturn( '1' );
		$ret->method( 'getName' )->willReturn( 'Foo' );
		$ret->method( 'getActions' )->willReturn( $actions );
		return $ret;
	}

	/**
	 * Helper to check that $expectedError is null and $actual is good, or the messages match.
	 */
	private function assertFilterValidatorStatus( ?string $expectedError, Status $actual ): void {
		if ( $expectedError ) {
			// Most of the FilterValidator error statuses are marked as non-fatal errors, and
			// assertStatusWarning() (despite its name) checks for non-fatal errors, not warnings.
			// The name and the distinction is confusing. See also T309859.
			$this->assertStatusWarning( $expectedError, $actual );
		} else {
			$this->assertStatusGood( $actual );
		}
	}

	/**
	 * @param ExceptionBase|null $excep
	 * @param Status $expected
	 * @dataProvider provideSyntax
	 */
	public function testCheckValidSyntax( ?ExceptionBase $excep, Status $expected ) {
		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$syntaxStatus = new ParserStatus( $excep, [], 1 );
		$ruleChecker->method( 'checkSyntax' )->willReturn( $syntaxStatus );
		$validator = $this->getFilterValidator( null, $ruleChecker );

		$actual = $validator->checkValidSyntax( $this->createMock( AbstractFilter::class ) );
		$this->assertStatusMessagesExactly( $expected, $actual );
	}

	public function provideSyntax(): Generator {
		yield 'valid' => [ null, Status::newGood() ];
		$excText = 'Internal error text';
		yield 'invalid, internal error' => [
			new InternalException( $excText ),
			Status::newFatal( 'abusefilter-edit-badsyntax', $excText )
		];
		$excMsg = $this->getMockMessage( $excText );
		$excep = $this->createMock( UserVisibleException::class );
		$excep->method( 'getMessageObj' )->willReturn( $excMsg );
		yield 'invalid, user error' => [ $excep, Status::newFatal( 'abusefilter-edit-badsyntax', $excMsg ) ];
	}

	/**
	 * @param string $rules
	 * @param string $name
	 * @param string|null $expectedError
	 * @dataProvider provideRequiredFields
	 */
	public function testCheckRequiredFields( string $rules, string $name, ?string $expectedError ) {
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( $rules );
		$filter->method( 'getName' )->willReturn( $name );
		$actual = $this->getFilterValidator()->checkRequiredFields( $filter );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public static function provideRequiredFields(): array {
		return [
			'valid' => [ '0', '0', null ],
			'no rules' => [ '', 'bar', 'abusefilter-edit-missingfields' ],
			'no name' => [ 'bar', '   ', 'abusefilter-edit-missingfields' ],
			'no rules and no name' => [ '', '', 'abusefilter-edit-missingfields' ]
		];
	}

	/**
	 * @param array $actions
	 * @param string|null $expectedError
	 * @dataProvider provideEmptyMessages
	 */
	public function testCheckEmptyMessages( array $actions, ?string $expectedError ) {
		$actual = $this->getFilterValidator()->checkEmptyMessages( $this->getFilterWithActions( $actions ) );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public static function provideEmptyMessages(): array {
		return [
			'valid' => [ [ 'warn' => [ 'foo' ], 'disallow' => [ 'bar' ] ], null ],
			'empty warn' => [ [ 'warn' => [ '' ], 'disallow' => [ 'bar' ] ], 'abusefilter-edit-invalid-warn-message' ],
			'empty disallow' =>
				[ [ 'warn' => [ 'foo' ], 'disallow' => [ '' ] ], 'abusefilter-edit-invalid-disallow-message' ],
			'both empty' => [ [ 'warn' => [ '' ], 'disallow' => [ '' ] ], 'abusefilter-edit-invalid-warn-message' ]
		];
	}

	/**
	 * @param bool $enabled
	 * @param bool $deleted
	 * @param string|null $expectedError
	 * @dataProvider provideConflictingFields
	 */
	public function testCheckConflictingFields( bool $enabled, bool $deleted, ?string $expectedError ) {
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'isEnabled' )->willReturn( $enabled );
		$filter->method( 'isDeleted' )->willReturn( $deleted );
		$actual = $this->getFilterValidator()->checkConflictingFields( $filter );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public static function provideConflictingFields(): array {
		return [
			'valid' => [ true, false, null ],
			'invalid' => [ true, true, 'abusefilter-edit-deleting-enabled' ]
		];
	}

	/**
	 * @param bool $canEditNew
	 * @param bool $canEditOrig
	 * @param string|null $expectedError
	 * @dataProvider provideCheckGlobalFilterEditPermission
	 */
	public function testCheckGlobalFilterEditPermission(
		bool $canEditNew,
		bool $canEditOrig,
		?string $expectedError
	) {
		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'canEditFilter' )->willReturnOnConsecutiveCalls( $canEditNew, $canEditOrig );
		$validator = $this->getFilterValidator( $permManager );
		$actual = $validator->checkGlobalFilterEditPermission(
			$this->createMock( Authority::class ),
			$this->createMock( AbstractFilter::class ),
			$this->createMock( AbstractFilter::class )
		);
		if ( $expectedError ) {
			$this->assertStatusError( $expectedError, $actual );
		} else {
			$this->assertStatusGood( $actual );
		}
	}

	public static function provideCheckGlobalFilterEditPermission(): array {
		return [
			'none' => [ false, false, 'abusefilter-edit-notallowed-global' ],
			'cur only' => [ true, false, 'abusefilter-edit-notallowed-global' ],
			'orig only' => [ false, true, 'abusefilter-edit-notallowed-global' ],
			'both' => [ true, true, null ]
		];
	}

	/**
	 * @param array $actions
	 * @param bool $isGlobal
	 * @param string|null $expectedError
	 * @dataProvider provideMessagesOnGlobalFilters
	 */
	public function testCheckMessagesOnGlobalFilters( array $actions, bool $isGlobal, ?string $expectedError ) {
		$filter = $this->getFilterWithActions( $actions );
		$filter->method( 'isGlobal' )->willReturn( $isGlobal );
		$actual = $this->getFilterValidator()->checkMessagesOnGlobalFilters( $filter );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public static function provideMessagesOnGlobalFilters(): array {
		return [
			'valid' => [
				[ 'warn' => [ 'abusefilter-warning' ], 'disallow' => [ 'abusefilter-disallowed' ] ],
				true,
				null
			],
			'custom warn' => [
				[ 'warn' => [ 'foo' ], 'disallow' => [ 'abusefilter-disallowed' ] ],
				true,
				'abusefilter-edit-notallowed-global-custom-msg'
			],
			'custom disallow' => [
				[ 'warn' => [ 'abusefilter-warn' ], 'disallow' => [ 'bar' ] ],
				true,
				'abusefilter-edit-notallowed-global-custom-msg'
			],
			'both custom' => [
				[ 'warn' => [ 'xxx' ], 'disallow' => [ 'yyy' ] ],
				true,
				'abusefilter-edit-notallowed-global-custom-msg'
			],
			'both custom but not global' => [ [ 'warn' => [ 'xxx' ], 'disallow' => [ 'yyy' ] ], false, null ]
		];
	}

	/**
	 * @param AbstractFilter $newFilter
	 * @param AbstractFilter $oldFilter
	 * @param array $restrictions
	 * @param AbuseFilterPermissionManager $permManager
	 * @param string|null $expectedError
	 * @dataProvider provideRestrictedActions
	 */
	public function testCheckRestrictedActions(
		AbstractFilter $newFilter,
		AbstractFilter $oldFilter,
		array $restrictions,
		AbuseFilterPermissionManager $permManager,
		?string $expectedError
	) {
		$validator = $this->getFilterValidator( $permManager, null, $restrictions );
		$performer = $this->createMock( Authority::class );
		$actual = $validator->checkRestrictedActions( $performer, $newFilter, $oldFilter );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public function provideRestrictedActions(): Generator {
		$canModifyRestrictedPM = $this->createMock( AbuseFilterPermissionManager::class );
		$canModifyRestrictedPM->method( 'canEditFilterWithRestrictedActions' )->willReturn( true );
		$cannotModifyRestrictedPM = $this->createMock( AbuseFilterPermissionManager::class );
		$cannotModifyRestrictedPM->method( 'canEditFilterWithRestrictedActions' )->willReturn( false );

		$newFilter = $oldFilter = $this->getFilterWithActions( [] );
		yield 'no restricted actions, with modify-restricted' =>
			[ $newFilter, $oldFilter, [], $canModifyRestrictedPM, null ];
		yield 'no restricted actions, no modify-restricted' =>
			[ $newFilter, $oldFilter, [], $cannotModifyRestrictedPM, null ];

		$restrictions = [ 'degroup' ];
		$restricted = $this->getFilterWithActions( [ 'warn' => [ 'foo' ], 'degroup' => [] ] );
		$unrestricted = $this->getFilterWithActions( [ 'warn' => [ 'foo' ] ] );

		yield 'restricted actions in new version, no modify-restricted' =>
			[ $restricted, $unrestricted, $restrictions, $cannotModifyRestrictedPM, 'abusefilter-edit-restricted' ];

		yield 'restricted actions in old version, no modify-restricted' =>
			[ $unrestricted, $restricted, $restrictions, $cannotModifyRestrictedPM, 'abusefilter-edit-restricted' ];

		yield 'restricted actions in new version, with modify-restricted' =>
			[ $restricted, $unrestricted, $restrictions, $canModifyRestrictedPM, null ];

		yield 'restricted actions in old version, with modify-restricted' =>
			[ $unrestricted, $restricted, $restrictions, $canModifyRestrictedPM, null ];
	}

	public function testCheckProtectedVariablesGood() {
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( 'user_unnamed_ip' );
		$filter->method( 'isProtected' )->willReturn( true );
		$this->assertStatusGood(
			$this->getFilterValidator()->checkProtectedVariables( $filter )
		);
	}

	public function testCheckProtectedVariablesUpdatedFilter() {
		$oldFilterUnprotected = $this->createMock( AbstractFilter::class );
		$oldFilterUnprotected->method( 'getRules' )->willReturn( 'user_name' );
		$oldFilterUnprotected->method( 'isProtected' )->willReturn( false );

		$oldFilterProtected = $this->createMock( AbstractFilter::class );
		$oldFilterProtected->method( 'getRules' )->willReturn( 'user_unnamed_ip' );
		$oldFilterProtected->method( 'isProtected' )->willReturn( true );

		$newFilterUnprotected = $this->createMock( AbstractFilter::class );
		$newFilterUnprotected->method( 'getRules' )->willReturn( 'user_name' );
		$newFilterUnprotected->method( 'isProtected' )->willReturn( false );

		$newFilterProtected = $this->createMock( AbstractFilter::class );
		$newFilterProtected->method( 'getRules' )->willReturn( 'user_unnamed_ip' );
		$newFilterProtected->method( 'isProtected' )->willReturn( true );

		$this->assertStatusGood(
			$this->getFilterValidator()->checkProtectedVariables( $newFilterUnprotected, $oldFilterProtected )
		);

		$this->assertStatusGood(
			$this->getFilterValidator()->checkProtectedVariables( $newFilterProtected, $oldFilterUnprotected )
		);
	}

	public function testCheckProtectedVariablesError() {
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( 'user_unnamed_ip' );
		$filter->method( 'isProtected' )->willReturn( false );
		$this->assertFilterValidatorStatus(
			'abusefilter-edit-protected-variable-not-protected',
			$this->getFilterValidator()->checkProtectedVariables( $filter )
		);
	}

	/**
	 * @dataProvider provideCheckCanViewProtectedVariables
	 */
	public function testCheckCanViewProtectedVariables( $data ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $data[ 'rights' ] );
		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'getForbiddenVariables' )->willReturn( [] );
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( $data[ 'rules' ] );
		$this->assertStatusGood( $this->getFilterValidator( $permManager )
			->checkCanViewProtectedVariables( $performer, $filter )
		);
	}

	/**
	 * @dataProvider provideCheckCanViewProtectedVariablesError
	 */
	public function testCheckCanViewProtectedVariablesError( $data ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $data[ 'rights' ] );
		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'getForbiddenVariables' )->willReturn( [ 'user_unnamed_ip' ] );
		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( $data[ 'rules' ] );
		$this->assertFilterValidatorStatus(
			'abusefilter-edit-protected-variable',
			$this->getFilterValidator( $permManager )->checkCanViewProtectedVariables( $performer, $filter )
		);
	}

	public static function provideCheckCanViewProtectedVariables(): array {
		return [
			'cannot view, no protected vars' => [
				[
					'rights' => [],
					'rules' => 'user_name'
				],
				0
			],
			'can view, protected vars' => [
				[
					'rights' => [ 'abusefilter-access-protected-vars' ],
					'rules' => 'user_unnamed_ip'
				],
				0
			],
			'can view, no protected vars' => [
				[
					'rights' => [ 'abusefilter-access-protected-vars' ],
					'rules' => 'user_name'
				],
				0
			]
		];
	}

	public static function provideCheckCanViewProtectedVariablesError(): array {
		return [
			'cannot view, protected vars' => [
				[
					'rights' => [],
					'rules' => 'user_unnamed_ip'
				]
			],
		];
	}

	public function testCheckAllTags_noTags() {
		$this->assertFilterValidatorStatus(
			'tags-create-no-name',
			$this->getFilterValidator()->checkAllTags( [] )
		);
	}

	/**
	 * @param array $params Throttle parameters
	 * @param string|null $expectedError The expected error message. Null if validations should pass
	 * @dataProvider provideThrottleParameters
	 */
	public function testCheckThrottleParameters( array $params, ?string $expectedError ) {
		$actual = $this->getFilterValidator()->checkThrottleParameters( $params );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	/**
	 * Data provider for testCheckThrottleParameters
	 * @return array
	 */
	public static function provideThrottleParameters() {
		return [
			[ [ '1', '5,23', 'user', 'ip', 'page,range', 'ip,user', 'range,ip' ], null ],
			[ [ '1', '5.3,23', 'user', 'ip' ], 'abusefilter-edit-invalid-throttlecount' ],
			[ [ '1', '-3,23', 'user', 'ip' ], 'abusefilter-edit-invalid-throttlecount' ],
			[ [ '1', '5,2.3', 'user', 'ip' ], 'abusefilter-edit-invalid-throttleperiod' ],
			[ [ '1', '4,-14', 'user', 'ip' ], 'abusefilter-edit-invalid-throttleperiod' ],
			[ [ '1', '3,33,44', 'user', 'ip' ], 'abusefilter-edit-invalid-throttleperiod' ],
			[ [ '1', '3,33' ], 'abusefilter-edit-empty-throttlegroups' ],
			[ [ '1', '3,33', 'user', 'ip,foo,user' ], 'abusefilter-edit-invalid-throttlegroups' ],
			[ [ '1', '3,33', 'foo', 'ip,user' ], 'abusefilter-edit-invalid-throttlegroups' ],
			[ [ '1', '3,33', 'foo', 'ip,user,bar' ], 'abusefilter-edit-invalid-throttlegroups' ],
			[ [ '1', '3,33', 'user', 'ip,page,user' ], null ],
			[
				[ '1', '3,33', 'ip', 'user', 'user,ip', 'ip,user', 'user,ip,user', 'user', 'ip,ip,user' ],
				'abusefilter-edit-duplicated-throttlegroups'
			],
			[ [ '1', '3,33', 'ip,ip,user' ], 'abusefilter-edit-duplicated-throttlegroups' ],
			[ [ '1', '3,33', 'user,ip', 'ip,user' ], 'abusefilter-edit-duplicated-throttlegroups' ],
		];
	}

	/**
	 * @param AbstractFilter $newFilter
	 * @param string|null $expectedError
	 * @param AbuseFilterPermissionManager|null $permissionManager
	 * @param FilterEvaluator|null $ruleChecker
	 * @param array $restrictions
	 * @param bool $isFatalError
	 * @dataProvider provideCheckAll
	 */
	public function testCheckAll(
		AbstractFilter $newFilter,
		?string $expectedError,
		?AbuseFilterPermissionManager $permissionManager = null,
		?FilterEvaluator $ruleChecker = null,
		array $restrictions = [],
		bool $isFatalError = false
	) {
		$validator = $this->getFilterValidator( $permissionManager, $ruleChecker, $restrictions );
		$origFilter = $this->createMock( AbstractFilter::class );

		$actual = $validator->checkAll( $newFilter, $origFilter, $this->createMock( Authority::class ) );
		if ( $expectedError && $isFatalError ) {
			$this->assertStatusError( $expectedError, $actual );
		} elseif ( $expectedError ) {
			$this->assertStatusWarning( $expectedError, $actual );
		} else {
			$this->assertStatusGood( $actual );
		}
	}

	public function provideCheckAll(): Generator {
		$noopFilter = $this->createMock( AbstractFilter::class );
		$noopFilter->method( 'getRules' )->willReturn( '1' );
		$noopFilter->method( 'getName' )->willReturn( 'Foo' );
		$noopFilter->method( 'isEnabled' )->willReturn( true );

		$ruleChecker = $this->createMock( FilterEvaluator::class );
		$syntaxStatus = new ParserStatus( $this->createMock( UserVisibleException::class ), [], 1 );
		$ruleChecker->method( 'checkSyntax' )->willReturn( $syntaxStatus );
		yield 'invalid syntax' => [ $noopFilter, 'abusefilter-edit-badsyntax', null, $ruleChecker ];

		$missingFieldsFilter = $this->createMock( AbstractFilter::class );
		$missingFieldsFilter->method( 'getRules' )->willReturn( '' );
		$missingFieldsFilter->method( 'getName' )->willReturn( '' );
		yield 'missing required fields' => [ $missingFieldsFilter, 'abusefilter-edit-missingfields' ];

		$conflictFieldsFilter = $this->createMock( AbstractFilter::class );
		$conflictFieldsFilter->method( 'getRules' )->willReturn( '1' );
		$conflictFieldsFilter->method( 'getName' )->willReturn( 'Foo' );
		$conflictFieldsFilter->method( 'isEnabled' )->willReturn( true );
		$conflictFieldsFilter->method( 'isDeleted' )->willReturn( true );
		yield 'conflicting fields' => [ $conflictFieldsFilter, 'abusefilter-edit-deleting-enabled' ];

		yield 'invalid tags' => [ $this->getFilterWithActions( [ 'tag' => [] ] ), 'tags-create-no-name' ];

		yield 'missing required messages' =>
			[ $this->getFilterWithActions( [ 'warn' => [ '' ] ] ), 'abusefilter-edit-invalid-warn-message' ];

		yield 'invalid throttle params' => [
			$this->getFilterWithActions( [ 'throttle' => [ '1', '5.3,23', 'user', 'ip' ] ] ),
			'abusefilter-edit-invalid-throttlecount'
		];

		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'canEditFilter' )->willReturn( false );
		yield 'global filter, no modify-global' => [ $noopFilter, 'abusefilter-edit-notallowed-global', $permManager,
			null, [], true ];

		$customWarnFilter = $this->getFilterWithActions( [ 'warn' => [ 'foo' ] ] );
		$customWarnFilter->method( 'isGlobal' )->willReturn( true );
		yield 'global filter, custom message' => [ $customWarnFilter, 'abusefilter-edit-notallowed-global-custom-msg' ];

		$permManager = $this->createMock( AbuseFilterPermissionManager::class );
		$permManager->method( 'canEditFilter' )->willReturn( true );
		$permManager->method( 'canEditFilterWithRestrictedActions' )->willReturn( false );
		$restrictedFilter = $this->getFilterWithActions( [ 'degroup' => [] ] );
		yield 'restricted actions' => [
			$restrictedFilter,
			'abusefilter-edit-restricted',
			$permManager,
			null,
			[ 'degroup' ]
		];

		$invalidGroupFilter = $this->createMock( AbstractFilter::class );
		$invalidGroupFilter->method( 'getRules' )->willReturn( 'true' );
		$invalidGroupFilter->method( 'getName' )->willReturn( 'Foo' );
		$invalidGroupFilter->expects( $this->atLeastOnce() )->method( 'getGroup' )->willReturn( 'xxx-invalid' );
		yield 'invalid group' => [ $invalidGroupFilter, 'abusefilter-edit-invalid-group' ];

		$filter = $this->createMock( AbstractFilter::class );
		$filter->method( 'getRules' )->willReturn( 'true' );
		$filter->method( 'getName' )->willReturn( 'Foo' );
		$filter->method( 'getGroup' )->willReturn( 'default' );
		yield 'valid' => [ $filter, null ];
	}

	/**
	 * @param string $group
	 * @param string[] $validGroups
	 * @param string|null $expectedError
	 * @dataProvider provideGroups
	 */
	public function testCheckGroup( string $group, array $validGroups, ?string $expectedError ) {
		$filter = $this->createMock( AbstractFilter::class );
		$filter->expects( $this->atLeastOnce() )->method( 'getGroup' )->willReturn( $group );
		$actual = $this->getFilterValidator( null, null, [], $validGroups )->checkGroup( $filter );
		$this->assertFilterValidatorStatus( $expectedError, $actual );
	}

	public static function provideGroups(): Generator {
		$allowed = [ 'default' ];
		yield 'Default, pass' => [ 'default', $allowed, null ];
		$extraGroup = 'foobar';
		$allowed[] = $extraGroup;
		yield 'Extra, pass' => [ $extraGroup, $allowed, null ];
		yield 'Unknown, fail' => [ 'some-unknown-group', $allowed, 'abusefilter-edit-invalid-group' ];
	}
}

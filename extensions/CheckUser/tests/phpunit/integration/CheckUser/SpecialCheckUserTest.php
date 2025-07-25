<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\FormOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserTest extends SpecialPageTestBase {

	use MockAuthorityTrait;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
	}

	/**
	 * Gets a test user with the checkuser group and also assigns that user as the user for the main request context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = $this->getTestUser( [ 'checkuser' ] )->getUser();
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		$this->getTestCheckUser();
		$object = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
		$testingWrapper = TestingAccessWrapper::newFromObject( $object );
		$testingWrapper->opts = new FormOptions();
		return $testingWrapper;
	}

	/** @dataProvider provideGetPager */
	public function testGetPager( $checkType, $userIdentity, $xfor = null ) {
		$object = $this->setUpObject();
		$object->opts->add( 'limit', 0 );
		$object->opts->add( 'reason', '' );
		$object->opts->add( 'period', 0 );
		if ( $checkType === SpecialCheckUser::SUBTYPE_GET_IPS ) {
			$this->assertInstanceOf( CheckUserGetIPsPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get IPs checktype should return the Get IPs pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_ACTIONS ) {
			$this->assertInstanceOf( CheckUserGetActionsPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get actions checktype should return the Get actions pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_USERS ) {
			$this->assertInstanceOf( CheckUserGetUsersPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get users checktype should return the Get users pager.'
			);
		} else {
			$this->assertNull(
				$object->getPager( $checkType, $userIdentity, 'untested' ),
				'An unrecognised check type should return no pager.'
			);
		}
	}

	public static function provideGetPager() {
		return [
			'Get IPs checktype' =>
				[ SpecialCheckUser::SUBTYPE_GET_IPS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a registered user' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get actions checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'Get users checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get users checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'An invalid checktype' => [ '', UserIdentityValue::newRegistered( 1, 'test' ) ],
		];
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->getServiceContainer()->getSpecialPageFactory()
			->getPage( 'CheckUser' );
		if ( $checkUserLog === null ) {
			$this->fail( 'CheckUser special page does not exist' );
		}
		$requiredRight = $checkUserLog->getRestriction();
		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}
		$rightsGivenInGroups = $this->getServiceContainer()->getGroupPermissionsLookup()
			->getGroupPermissions( $groups );
		if ( $allowed ) {
			$this->assertContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should allow it to access CheckUser.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to CheckUser.'
			);
		}
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( ( is_array( $groups ) && isset( $groups['checkuser-log'] ) ) || $groups === "checkuser-log" ) {
			$this->setGroupPermissions(
				[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser', true ],
		];
	}

	public function testLoadSpecialPageBeforeFormSubmission() {
		// Execute the special page. We need the full HTML to verify the subtitle links.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );
		// Assert that the "Try out Special:Investigate" link is present
		$this->assertStringContainsString( '(checkuser-link-investigate-label', $html );
		// Assert that the normal subtitle links are present (those without a specific target)
		$this->assertStringContainsString( '(checkuser-show-investigate', $html );
		$this->assertStringContainsString( '(checkuser-showlog', $html );
		// Verify that the summary text is present
		$this->assertStringContainsString( '(checkuser-summary', $html );
		// Verify that the form fields that are expected are present.
		$this->assertStringContainsString( '(checkuser-target', $html );
		$this->assertStringContainsString( '(checkuser-period', $html );
		$this->assertStringContainsString( '(checkuser-reason', $html );
		$this->assertStringContainsString( '(checkuser-ips', $html );
		$this->assertStringContainsString( '(checkuser-actions', $html );
		$this->assertStringContainsString( '(checkuser-users', $html );
		// Verify that the submit button is present
		$this->assertStringContainsString( '(checkuser-check', $html );
		// Verify that the CIDR calculator is present
		$this->assertStringContainsString( '(checkuser-cidr-label', $html );
	}
}

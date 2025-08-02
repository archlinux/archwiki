<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\FormOptions;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\TestingAccessWrapper;

abstract class CheckUserPagerTestBase extends MediaWikiIntegrationTestCase {

	/** @var string One of the SpecialCheckUser::SUBTYPE_... constants */
	protected $checkSubtype;

	/** @var UserIdentity the default UserIdentity to be used as the target in tests. */
	protected $defaultUserIdentity;

	/** @var string the default check type to be used in tests. */
	protected $defaultCheckType;

	/**
	 * Gets the default values for a row from the DB.
	 *
	 * @return array
	 */
	abstract protected function getDefaultRowFieldValues(): array;

	protected function commonTestGetQueryInfo( $target, $xfor, $table, $expectedQueryInfo ) {
		$object = $this->setUpObject();
		$object->target = $target;
		$object->xfor = $xfor;
		$actualQueryInfo = $object->getQueryInfo( $table );
		// Convert any IExpression objects to SQL so that they can be compared as strings.
		foreach ( $actualQueryInfo['conds'] as $key => $value ) {
			if ( $value instanceof IExpression ) {
				$actualQueryInfo['conds'][$key] = $value->toSql( $this->getDb() );
			}
		}
		$this->assertArraySubmapSame(
			$expectedQueryInfo,
			$actualQueryInfo,
			'::getQueryInfo did not return the expected result.'
		);
	}

	/**
	 * Set up the object for the pager that is being tested
	 * wrapped in a TestingAccessWrapper so that the tests
	 * can modify and access protected / private methods and
	 * properties.
	 *
	 * @param UserIdentity|null $userIdentity the target for the check
	 * @param string|null $checkType the check type (e.g. ipedits).
	 * @param string[] $groups the groups that the request context user should be in.
	 * @return TestingAccessWrapper
	 */
	protected function setUpObject(
		?UserIdentity $userIdentity = null, ?string $checkType = null, array $groups = [ 'checkuser' ]
	) {
		RequestContext::getMain()->setUser( $this->getTestUser( $groups )->getUser() );
		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'limit', '' );
		$opts->add( 'dir', '' );
		$opts->add( 'offset', '' );
		$specialCheckUser = TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' )
		);
		$specialCheckUser->opts = $opts;
		$object = $specialCheckUser->getPager(
			$this->checkSubtype,
			$userIdentity ?? $this->defaultUserIdentity,
			$checkType ?? $this->defaultCheckType
		);
		return TestingAccessWrapper::newFromObject( $object );
	}
}

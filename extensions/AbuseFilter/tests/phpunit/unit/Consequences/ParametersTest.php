<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Consequences;

use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Parameters
 */
class ParametersTest extends MediaWikiUnitTestCase {
	/**
	 * @covers ::__construct
	 * @covers ::getFilter
	 * @covers ::getIsGlobalFilter
	 * @covers ::getUser
	 * @covers ::getTarget
	 * @covers ::getAction
	 */
	public function testGetters() {
		$filter = $this->createMock( ExistingFilter::class );
		$global = true;
		$user = $this->createMock( UserIdentity::class );
		$target = $this->createMock( LinkTarget::class );
		$action = 'some-action';
		$params = new Parameters( $filter, $global, $user, $target, $action );

		$this->assertSame( $filter, $params->getFilter(), 'filter' );
		$this->assertSame( $global, $params->getIsGlobalFilter(), 'global' );
		$this->assertSame( $user, $params->getUser(), 'user' );
		$this->assertSame( $target, $params->getTarget(), 'target' );
		$this->assertSame( $action, $params->getAction(), 'action' );
	}
}

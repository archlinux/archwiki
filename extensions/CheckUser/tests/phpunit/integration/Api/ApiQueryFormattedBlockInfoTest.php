<?php
namespace MediaWiki\CheckUser\Tests\Integration\Api;

use MediaWiki\Block\Restriction\PageRestriction;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group Database
 * @covers \MediaWiki\CheckUser\Api\ApiQueryFormattedBlockInfo
 */
class ApiQueryFormattedBlockInfoTest extends ApiTestCase {
	public function testShouldRejectAnonymousPerformer(): void {
		$this->expectApiErrorCode( 'mustbeloggedin-generic' );

		$performer = $this->getServiceContainer()
			->getUserFactory()
			->newAnonymous( '127.0.0.1' );

		$this->doApiRequest( [
			'action' => 'query',
			'meta' => 'checkuserformattedblockinfo',
			'uselang' => 'qqx',
		], null, false, $performer );
	}

	public function testShouldReturnNullDetailsWhenPerformerNotBlocked(): void {
		$performer = $this->getTestUser()->getAuthority();

		[ $res ] = $this->doApiRequest( [
			'action' => 'query',
			'meta' => 'checkuserformattedblockinfo',
			'uselang' => 'qqx',
		], null, false, $performer );

		$this->assertNull( $res['query']['checkuserformattedblockinfo']['details'] );
	}

	public function testShouldReturnBlockDetailsWhenPerformerBlockedPartially(): void {
		$performer = $this->getTestUser()->getAuthority();

		$status = $this->getServiceContainer()
			->getBlockUserFactory()
			->newBlockUser(
				$performer,
				$this->getTestSysop()->getAuthority(),
				'infinity',
				'',
				[],
				[ new PageRestriction( 0, $this->getExistingTestPage()->getId() ) ]
			)
			->placeBlock();

		[ $res ] = $this->doApiRequest( [
			'action' => 'query',
			'meta' => 'checkuserformattedblockinfo',
			'uselang' => 'qqx',
		], null, false, $performer );

		$this->assertStatusGood( $status );
		$this->assertNull( $res['query']['checkuserformattedblockinfo']['details'] );
	}

	public function testShouldReturnBlockDetailsWhenPerformerBlockedSitewide(): void {
		$performer = $this->getTestUser()->getAuthority();

		$status = $this->getServiceContainer()
			->getBlockUserFactory()
			->newBlockUser( $performer, $this->getTestSysop()->getAuthority(), 'infinity' )
			->placeBlock();

		[ $res ] = $this->doApiRequest( [
			'action' => 'query',
			'meta' => 'checkuserformattedblockinfo',
			'uselang' => 'qqx',
		], null, false, $performer );

		$this->assertStatusGood( $status );
		$this->assertStringStartsWith( '(blockedtext:', $res['query']['checkuserformattedblockinfo']['details'] );
	}
}

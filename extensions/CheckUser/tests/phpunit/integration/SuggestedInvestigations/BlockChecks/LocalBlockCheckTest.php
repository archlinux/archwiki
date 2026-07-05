<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\BlockChecks;

use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\LocalBlockCheck;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\LocalBlockCheck
 * @group CheckUser
 * @group Database
 */
class LocalBlockCheckTest extends MediaWikiIntegrationTestCase {

	private DatabaseBlockStore $blockStore;
	private LocalBlockCheck $check;

	public function setUp(): void {
		parent::setUp();

		$this->blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$this->check = new LocalBlockCheck( $this->blockStore );
	}

	public function testGetIndefinitelyBlockedUserIds(): void {
		$indefBlockedUserId = $this->blockUserIndefinitely()->getId();
		$tempBlockedUserId = $this->blockUserTemporarily()->getId();
		$partBlockUserId = $this->blockUserPartially()->getId();

		$this->assertSame(
			[ $indefBlockedUserId ],
			$this->check->getIndefinitelyBlockedUserIds( [
				$indefBlockedUserId,
				$tempBlockedUserId,
				$partBlockUserId,
				99999,
			] )
		);
	}

	public function testGetBlockedUserIdsBlockChecks(): void {
		$indefBlockedUserId = $this->blockUserIndefinitely()->getId();
		$tempBlockedUserId = $this->blockUserTemporarily()->getId();
		$partBlockUserId = $this->blockUserPartially()->getId();

		$this->assertSame(
			[ $indefBlockedUserId, $tempBlockedUserId, $partBlockUserId ],
			$this->check->getBlockedUserIds( [ $indefBlockedUserId, $tempBlockedUserId, $partBlockUserId, 4 ] )
		);
	}

	private function blockUserIndefinitely(): UserIdentity {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$block = $this->blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => 'infinity',
		] );
		$this->blockStore->insertBlock( $block );

		return $user;
	}

	private function blockUserTemporarily(): UserIdentity {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$block = $this->blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => time() + 3600,
		] );
		$this->blockStore->insertBlock( $block );

		return $user;
	}

	private function blockUserPartially(): UserIdentity {
		$user = $this->getMutableTestUser()->getUserIdentity();
		$block = $this->blockStore->newUnsaved( [
			'targetUser' => $user,
			'by' => $this->getTestSysop()->getUserIdentity(),
			'expiry' => 'infinity',
			'sitewide' => false,
		] );
		$this->blockStore->insertBlock( $block );

		return $user;
	}
}

<?php

namespace MediaWiki\Extension\Notifications\Test\Formatters;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\Notifications\Formatters\EchoUserRightsPresentationModel;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Notifications\Formatters\EchoUserRightsPresentationModel
 * @group Database
 */
class EchoUserRightsPresentationModelTest extends MediaWikiIntegrationTestCase {

	/**
	 * Build an Event object without inserting into the Echo DB or triggering notifications.
	 */
	private function makeUserRightsEvent( User $agent, array $extra ): Event {
		return Event::newFromRow( (object)[
			'event_id' => 12,
			'event_type' => 'user-rights',
			'event_page_id' => 0,
			'event_deleted' => 0,
			'event_agent_id' => $agent->getId(),
			'event_extra' => $this->getServiceContainer()->getJsonCodec()->serialize( $extra ),
			'event_timestamp' => wfTimestampNow(),
		] );
	}

	private function createModel(
		Event $event,
		User $viewingUser,
		string $distributionType = 'web'
	): EchoUserRightsPresentationModel {
		$model = EchoEventPresentationModel::factory(
			$event,
			$this->getServiceContainer()
				->getLanguageFactory()
				->getLanguage( 'qqx' ),
			$viewingUser,
			$distributionType
		);

		$this->assertInstanceOf( EchoUserRightsPresentationModel::class, $model );
		return $model;
	}

	private function overrideServerConfig(): void {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://test.example.org',
			MainConfigNames::CanonicalServer => 'https://test.example.org',
			MainConfigNames::ArticlePath => '/wiki/$1',
		] );
	}

	/**
	 * Automatic expirations should use a dedicated header that does not
	 * reference an agent, since the change was system-initiated.
	 */
	public function testAutomaticExpirationHeaderMessageUsesDedicatedMessage(): void {
		$user = $this->getTestUser()->getUser();
		$agent = $this->getTestSysop()->getUser();

		$event = $this->makeUserRightsEvent( $agent, [
			'user' => $user->getId(),
			'remove' => [ 'bot' ],
			'automatic' => true,
		] );

		$model = $this->createModel( $event, $user );
		$text = $model->getHeaderMessage()->text();

		$this->assertStringStartsWith(
			'(notification-header-user-rights-expiry',
			$text,
			'Automatic expiration should use the expiry-specific header message'
		);
	}

	/**
	 * Automatic expirations have no agent, so secondary links
	 * should only contain the log link (no agent link) and the log URL
	 * should not filter by performer.
	 */
	public function testAutomaticExpirationSecondaryLinksOmitAgentAndPerformerFilter(): void {
		$this->overrideServerConfig();

		$user = $this->getTestUser()->getUser();
		$agent = $this->getTestSysop()->getUser();

		$event = $this->makeUserRightsEvent( $agent, [
			'user' => $user->getId(),
			'remove' => [ 'bot' ],
			'automatic' => true,
		] );

		$model = $this->createModel( $event, $user );
		$links = $model->getSecondaryLinks();

		$this->assertCount( 1, $links, 'Only the log link should be present (no agent link)' );
		$this->assertSame( '(echo-log)', $links[0]['label'] );

		$parts = parse_url( $links[0]['url'] );
		parse_str( $parts['query'] ?? '', $query );

		$this->assertSame( 'rights', $query['type'] ?? null, 'Log link should filter to rights log' );
		$this->assertArrayHasKey( 'page', $query, 'Log link should filter by affected user page' );
		$this->assertArrayNotHasKey( 'user', $query, 'Log link should not filter by performer for automatic changes' );
	}

	/**
	 * The email subject for automatic expirations should differ from the
	 * one used for manual rights changes.
	 */
	public function testAutomaticExpirationUsesDedicatedEmailSubject(): void {
		$user = $this->getTestUser()->getUser();
		$agent = $this->getTestSysop()->getUser();

		$event = $this->makeUserRightsEvent( $agent, [
			'user' => $user->getId(),
			'remove' => [ 'bot' ],
			'automatic' => true,
		] );

		$model = $this->createModel( $event, $user, 'email' );
		$subject = $model->getSubjectMessage()->text();

		$this->assertStringStartsWith(
			'(notification-user-rights-expiry-email-subject',
			$subject,
			'Automatic expiration should use the expiry-specific email subject'
		);
	}

	/**
	 * Manual rights changes (with a real performer) should include an agent
	 * link in secondary links and filter the log by performer.
	 */
	public function testManualRightsChangeIncludesAgentLinkAndPerformerFilterInLog(): void {
		$this->overrideServerConfig();

		$user = $this->getTestUser()->getUser();
		$agent = $this->getTestSysop()->getUser();

		$event = $this->makeUserRightsEvent( $agent, [
			'user' => $user->getId(),
			'remove' => [ 'bot' ],
		] );

		$model = $this->createModel( $event, $user );
		$header = $model->getHeaderMessage()->text();
		$this->assertStringStartsWith(
			'(notification-header-user-rights-remove-only',
			$header,
			'Manual removal should use the standard remove-only header'
		);

		$links = $model->getSecondaryLinks();
		$this->assertCount( 2, $links, 'Manual change should have both agent and log links' );

		$logLinks = array_values( array_filter( $links, static function ( array $link ) {
			return ( $link['label'] ?? null ) === '(echo-log)';
		} ) );
		$this->assertCount( 1, $logLinks );

		$parts = parse_url( $logLinks[0]['url'] );
		parse_str( $parts['query'] ?? '', $query );

		$this->assertSame( 'rights', $query['type'] ?? null );
		$this->assertSame(
			$agent->getName(),
			$query['user'] ?? null,
			'Log link should filter by the performer for manual changes'
		);

		$emailModel = $this->createModel( $event, $user, 'email' );
		$subject = $emailModel->getSubjectMessage()->text();
		$this->assertStringStartsWith(
			'(notification-user-rights-email-subject',
			$subject,
			'Manual change should use the standard email subject'
		);
	}
}

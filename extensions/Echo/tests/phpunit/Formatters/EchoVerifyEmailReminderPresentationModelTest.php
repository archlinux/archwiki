<?php
namespace MediaWiki\Extension\Notifications\Test\Formatters;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\Extension\Notifications\Formatters\EchoVerifyEmailReminderPresentationModel;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MainConfigNames;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Notifications\Formatters\EchoVerifyEmailReminderPresentationModel
 * @group Database
 */
class EchoVerifyEmailReminderPresentationModelTest extends MediaWikiIntegrationTestCase {

	private function createModel(
		User $user,
		string $distributionType = 'web'
	): EchoVerifyEmailReminderPresentationModel {
		$model = EchoEventPresentationModel::factory(
			Event::create( [ 'type' => 'verify-email-reminder' ] ),
			$this->getServiceContainer()
				->getLanguageFactory()
				->getLanguage( 'qqx' ),
			$user,
			$distributionType
		);

		$this->assertInstanceOf( EchoVerifyEmailReminderPresentationModel::class, $model );

		return $model;
	}

	/**
	 * @dataProvider provideCanRender
	 */
	public function testCanRender( bool $isEmailConfirmed ) {
		$user = $this->getMutableTestUser()->getUser();
		if ( $isEmailConfirmed ) {
			$user->confirmEmail();
			$user->saveSettings();
		}

		$model = $this->createModel( $user );

		$this->assertSame( !$isEmailConfirmed, $model->canRender() );
	}

	public static function provideCanRender(): iterable {
		yield 'Email confirmed' => [ true ];
		yield 'Email not confirmed' => [ false ];
	}

	/**
	 * @dataProvider provideDistributionTypes
	 */
	public function testPrimaryLink( string $distributionType ): void {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://test.example.org',
			MainConfigNames::CanonicalServer => 'https://test.example.org',
			MainConfigNames::ArticlePath => '/wiki/$1',
		] );

		$user = $this->getMutableTestUser()->getUser();

		$model = $this->createModel( $user, $distributionType );

		$primaryLink = $model->getPrimaryLink();

		$emailConfirmationToken = $this->getDb()
			->newSelectQueryBuilder()
			->select( 'user_email_token' )
			->from( 'user' )
			->where( [ 'user_id' => $user->getId() ] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $distributionType === 'email' ) {
			$this->assertMatchesRegularExpression( '/[a-f0-9]{32}/', $emailConfirmationToken );
			$this->assertStringStartsWith(
				"https://test.example.org/wiki/Special:ConfirmEmail/",
				$primaryLink['url']
			);
			$tokenPart = substr( $primaryLink['url'], -32 );
			$this->assertSame( $emailConfirmationToken, md5( $tokenPart ) );
		} else {
			$this->assertNull( $emailConfirmationToken );
			$this->assertSame(
				'https://test.example.org/wiki/Special:ConfirmEmail',
				$primaryLink['url']
			);
		}

		$this->assertSame( '(notification-verify-email-reminder-link-label)', $primaryLink['label'] );
	}

	/**
	 * @dataProvider provideDistributionTypes
	 */
	public function testOtherOptions( string $distributionType ): void {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'https://test.example.org',
			MainConfigNames::ArticlePath => '/wiki/$1',
		] );

		$user = $this->getMutableTestUser()->getUser();

		$model = $this->createModel( $user, $distributionType );

		$this->assertSame( 'alert', $model->getIconType() );

		$this->assertSame(
			"(notification-header-verify-email-reminder: {$user->getName()})",
			$model->getHeaderMessage()->text()
		);
		$this->assertSame(
			"(notification-subject-email-verify-email-reminder: {$user->getName()})",
			$model->getSubjectMessage()->text()
		);

		$expectedSettingsLink = [
			'icon' => 'settings',
			'url' => 'https://test.example.org/wiki/Special:Preferences#mw-prefsection-personal-email',
			'label' => "(notification-link-text-verify-email-reminder: {$user->getName()})",
		];

		if ( $distributionType === 'email' ) {
			$this->assertSame( [ $expectedSettingsLink ], $model->getSecondaryLinks() );
		} else {
			$expectedConfirmLink = [
				'icon' => 'lock',
				'prioritized' => true,
				'url' => 'https://test.example.org/wiki/Special:ConfirmEmail',
				'label' => "(notification-verify-email-reminder-link-label: {$user->getName()})",
			];
			$this->assertSame( [ $expectedConfirmLink, $expectedSettingsLink ], $model->getSecondaryLinks() );
		}
	}

	public static function provideDistributionTypes(): iterable {
		yield 'web' => [ 'web' ];
		yield 'email' => [ 'email' ];
	}
}

<?php
namespace MediaWiki\Extension\Notifications\Test\Structure;

use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 */
class EchoNotificationPreferencesTest extends MediaWikiIntegrationTestCase {
	public function testAllPreferenceTooltipMessagesShouldExist(): void {
		$notificationCategories = $this->getConfVar( 'EchoNotificationCategories' );

		foreach ( $notificationCategories as $category => $config ) {
			if ( array_key_exists( 'tooltip', $config ) ) {
				$tooltipMessage = $config['tooltip'];
				$this->assertFalse(
					wfMessage( $tooltipMessage )->isBlank(),
					"Tooltip message '$tooltipMessage' for notification category '$category' does not exist."
				);
			}

			$titleMsg = array_key_exists( 'title', $config ) ? $config['title'] : "echo-category-title-$category";

			$this->assertFalse(
				wfMessage( $titleMsg )->isBlank(),
				"Title message '$titleMsg' for notification category '$category' does not exist."
			);
		}
	}
}

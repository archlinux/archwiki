<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\ResourceLoader\Context;

/**
 * Used by ResourceLoader to generate virtual files containing translated durations for the
 * IP auto-reveal feature. This allows translations that must be done using PHP to be accessed
 * by client-side modules.
 */
class DurationMessages {
	/**
	 * Get translations of options for the select in IPAutoRevealOnDialog.vue.
	 *
	 * @param Context $context
	 * @param Config $config
	 * @param int[] $durations Durations in seconds
	 *
	 * @return array[] Array of objects specifying durations in seconds and their
	 *  associated translations:
	 *  - seconds: (int) the duration in seconds
	 *  - translation: (string) the translated duration
	 */
	public static function getTranslatedDurations(
		Context $context,
		Config $config,
		array $durations
	): array {
		$translations = [];
		foreach ( $durations as $duration ) {
			$translations[] = [
				'seconds' => $duration,
				'translation' => $context->msg( 'checkuser-ip-auto-reveal-on-dialog-select-duration' )
					->durationParams( $duration )
					->text(),
			];
		}
		return $translations;
	}

	/**
	 * Get a translation of the maximum duration for IP auto-reveal.
	 *
	 * @param Context $context
	 * @param Config $config
	 *
	 * @return string[]
	 */
	public static function getTranslatedMaxDuration(
		Context $context,
		Config $config
	): array {
		return [
			'translation' => $context->msg( 'checkuser-ip-auto-reveal-off-dialog-error-extend-limit' )
				->durationParams( $config->get( 'CheckUserAutoRevealMaximumExpiry' ) )
				->text(),
		];
	}

	/**
	 * Returns the maximum expiry time of the IP auto-reveal feature as a duration.
	 * Used by the IP reveal step in the temporary accounts onboarding dialog.
	 */
	public static function getAutoRevealMaximumExpiry(
		Context $context,
		Config $config
	): string {
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$msgKey = 'checkuser-temporary-accounts-onboarding-dialog-ip-reveal-postscript-text-with-global-preferences-with-autoreveal';
		$maxExpiry = $config->get( 'CheckUserAutoRevealMaximumExpiry' );
		return $context->msg( $msgKey )
			->durationParams( $maxExpiry )
			->parse();
	}
}

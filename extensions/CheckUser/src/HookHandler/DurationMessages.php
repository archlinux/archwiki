<?php

namespace MediaWiki\CheckUser\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\ResourceLoader\Context;

/**
 * Used by ResourceLoader to generate a virtual file containing translated durations for
 * the IP auto-reveal feature.
 */
class DurationMessages {
	/**
	 * Generate duration objects containing core translations of durations, used as the contents of
	 * a virtual ResourceLoader package file. This allows translations that must be done using PHP
	 * to be accessed by client-side modules. These objects are used for building a select in
	 * IPAutoRevealOnDialog.vue.
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
	) {
		$translations = [];
		foreach ( $durations as $duration ) {
			$translations[] = [
				'seconds' => $duration,
				'translation' => $context->msg( 'checkuser-ip-auto-reveal-on-dialog-select-duration' )
					->durationParams( $duration )
					->text()
			];
		}
		return $translations;
	}
}

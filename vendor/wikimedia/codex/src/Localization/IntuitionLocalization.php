<?php
/**
 * IntuitionLocalization.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It defines the `IntuitionLocalization` class, which
 * provides localized messages using the Intuition library for non-MediaWiki environments.
 *
 * The `IntuitionLocalization` class allows Codex components to retrieve translated messages
 * in non-MediaWiki environments, ensuring consistency and flexibility in localization
 * within the Codex system, regardless of the platform.
 *
 * @category Localization
 * @package  Codex\Localization
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Localization;

use Krinkle\Intuition\Intuition;
use Wikimedia\Codex\Contract\ILocalizer;

/**
 * Localization class for non-MediaWiki environments using Intuition.
 *
 * The `IntuitionLocalization` class uses the Intuition library to retrieve localized
 * messages for Codex components in non-MediaWiki environments. By implementing
 * the `ILocalizer` interface, this class ensures Codex components
 * have consistent access to localized messages across different environments.
 *
 * @category Localization
 * @package  Codex\Localization
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class IntuitionLocalization implements ILocalizer {

	/**
	 * Instance of the Intuition library used for retrieving localized messages.
	 *
	 * This instance provides access to the Intuition message localization system,
	 * which enables localized messages to be fetched based on a message key and
	 * optional parameters.
	 */
	private Intuition $localizer;

	/**
	 * Constructor for the IntuitionLocalization class.
	 *
	 * @param Intuition $localizer The Intuition instance.
	 */
	public function __construct( Intuition $localizer ) {
		$this->localizer = $localizer;
	}

	/**
	 * Retrieve a localized message using Intuition.
	 *
	 * This method fetches a translated message from the Intuition library based on
	 * the provided message key and optional parameters. The message key identifies
	 * the message, while the parameters allow dynamic values to be inserted into the message.
	 *
	 * @param string $key The message key.
	 * @param string ...$params Parameters for message replacements.
	 * @return string The localized message.
	 */
	public function msg( string $key, ...$params ): string {
		return $this->localizer->msg( $key, [ 'variables' => $params ] );
	}
}

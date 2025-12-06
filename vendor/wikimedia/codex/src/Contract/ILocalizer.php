<?php
/**
 * ILocalizer.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It defines the `ILocalizer` interface, which
 * standardizes the localization method for retrieving translated messages
 * within the Codex design system. Implementations of this interface provide
 * localized messages from various environments, such as MediaWiki and Intuition.
 *
 * By defining this interface, Codex components gain flexibility in message
 * localization, allowing the system to select the appropriate localization
 * service at runtime.
 *
 * @category Contract
 * @package  Codex\Contract
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Contract;

/**
 * Interface for localization services in Codex.
 *
 * The `ILocalizer` interface defines the method required for retrieving localized
 * messages. Implementations of this interface enable message localization in
 * different environments, ensuring flexibility and consistency in translations
 * across Codex components.
 *
 * @category Contract
 * @package  Codex\Contract
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
interface ILocalizer {

	/**
	 * Retrieve a localized message.
	 *
	 * This method fetches a translated message based on a message key.
	 * Optional parameters can be included for placeholder replacements in the message.
	 *
	 * @since 0.1.0
	 * @param string $key The message key.
	 * @param string ...$params Optional parameters for placeholders in the message.
	 * @return string The localized message.
	 */
	public function msg( string $key, ...$params ): string;
}

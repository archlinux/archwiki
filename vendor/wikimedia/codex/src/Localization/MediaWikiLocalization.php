<?php
/**
 * MediaWikiLocalization.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It defines the `MediaWikiLocalization` class, which
 * provides localized messages using the MediaWiki `RequestContext` message system.
 *
 * The `MediaWikiLocalization` class allows Codex components to retrieve
 * translated messages in environments where MediaWiki is available. This ensures
 * that the localization method is consistent and flexible within the Codex system.
 *
 * @category Localization
 * @package  Codex\Localization
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Localization;

use MediaWiki\Context\RequestContext;
use Wikimedia\Codex\Contract\ILocalizer;

/**
 * Localization class for MediaWiki environment.
 *
 * The `MediaWikiLocalization` class uses the MediaWiki `RequestContext` to retrieve
 * localized messages based on a message key and optional parameters. By implementing
 * the `ILocalizer` interface, this class ensures consistent access to localized messages
 * for Codex components within a MediaWiki environment.
 *
 * @category Localization
 * @package  Codex\Localization
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class MediaWikiLocalization implements ILocalizer {

	/**
	 * The request context from which localized messages are retrieved.
	 */
	private RequestContext $context;

	/**
	 * Constructor for the MediaWikiLocalization class.
	 *
	 * @param RequestContext $context The MediaWiki request context.
	 */
	public function __construct( RequestContext $context ) {
		$this->context = $context;
	}

	/**
	 * Retrieve a localized message using MediaWiki’s RequestContext.
	 *
	 * This method fetches a translated message from MediaWiki using the provided message key
	 * and optional parameters. The message key identifies the message, while the parameters
	 * allow dynamic values to be inserted into the message.
	 *
	 * @param string $key The message key.
	 * @param string ...$params Parameters for message replacements.
	 * @return string The localized message.
	 */
	public function msg( string $key, ...$params ): string {
		return $this->context->msg( $key, ...$params )->text();
	}
}

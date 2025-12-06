<?php
/**
 * CodexServices.php
 *
 * This class is part of the Codex design system and manages the retrieval and instantiation
 * of various renderers and services. It follows dependency injection principles, ensuring services are
 * injected and reused throughout the system. Services include renderers for
 * various UI components and utilities like Mustache and Localization.
 *
 * @category Infrastructure
 * @package  Codex\Infrastructure
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Infrastructure;

use Wikimedia\Services\ServiceContainer;

/**
 * CodexServices
 *
 * Service locator for Codex services.
 *
 * Refer to src/Infrastructure/ServiceWiring.php for the default implementations.
 *
 * @category Infrastructure
 * @package  Codex\Infrastructure
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class CodexServices extends ServiceContainer {

	/**
	 * Instance of CodexServices (singleton)
	 */
	private static ?CodexServices $instance = null;

	/**
	 * Private constructor to initialize the DI container, and load service wiring.
	 */
	private function __construct() {
		parent::__construct();
		$this->loadServiceWiring();
	}

	/**
	 * Load service wiring from the ServiceWiring.php file.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private function loadServiceWiring(): void {
		$wiring = require __DIR__ . '/ServiceWiring.php';
		parent::applyWiring( $wiring );
	}

	/**
	 * Retrieves the global default instance of the Codex service locator.
	 *
	 * @since 0.1.0
	 * @return self The default instance of the CodexServices.
	 */
	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

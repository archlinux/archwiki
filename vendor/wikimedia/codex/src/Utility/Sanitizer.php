<?php
/**
 * Sanitizer.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Sanitizer` class, which is responsible
 * for sanitizing data before rendering. The Sanitizer ensures that all output is safe
 * and helps prevent XSS and other security vulnerabilities.
 *
 * The Sanitizer class includes methods for sanitizing text, HTML content, and HTML attributes.
 * By centralizing the sanitization logic, it adheres to the Single Responsibility Principle
 * and enhances the maintainability and security of the codebase.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Utility;

/**
 * Sanitizer is a class responsible for sanitizing data before rendering.
 *
 * This class provides methods to sanitize text, HTML content, and attributes.
 * It ensures that all data outputted to the user is properly sanitized, preventing XSS
 * and other injection attacks.
 *
 * @category Utility
 * @package  Codex\Utility
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Sanitizer {

	/**
	 * Sanitize a plain text string.
	 *
	 * This method escapes special HTML characters in a string to prevent XSS attacks.
	 * It should be used when the content does not contain any HTML markup and needs
	 * to be treated strictly as text.
	 *
	 * @since 0.1.0
	 * @param string|null $text The plain text to sanitize.
	 * @return string The sanitized text.
	 */
	public function sanitizeText( ?string $text ): string {
		return htmlspecialchars( $text ?? '', ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Sanitize an array of HTML attributes.
	 *
	 * This method escapes both the keys and values of an associative array of attributes
	 * to prevent XSS attacks. It should be used for any attributes that will be rendered
	 * in HTML elements.
	 *
	 * @since 0.1.0
	 * @param array $attributes The associative array of attributes to sanitize.
	 * @return array The sanitized attributes array.
	 */
	public function sanitizeAttributes( array $attributes ): array {
		$sanitized = [];
		foreach ( $attributes as $key => $value ) {
			$sanitizedKey = htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' );
			$sanitizedValue = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
			$sanitized[$sanitizedKey] = $sanitizedValue;
		}

		return $sanitized;
	}

	/**
	 * Sanitize a URL.
	 *
	 * This method ensures the URL is safe by validating it, removing illegal characters,
	 * ensuring it uses an allowed scheme, and properly escaping it for HTML output.
	 *
	 * @since 0.1.0
	 * @param string|null $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	public function sanitizeUrl( ?string $url ): string {
		if ( $url === null || $url === '' ) {
			return '';
		}

		$sanitizedUrl = filter_var( $url, FILTER_SANITIZE_URL );

		if ( !filter_var( $sanitizedUrl, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$parsedUrl = parse_url( $sanitizedUrl );

		$allowedSchemes = [ 'http', 'https' ];
		if (
			!isset( $parsedUrl['scheme'] ) ||
			!in_array( strtolower( $parsedUrl['scheme'] ), $allowedSchemes, true )
		) {
			return '';
		}

		$reconstructedUrl = $this->unparseUrl( $parsedUrl );

		return htmlspecialchars( $reconstructedUrl, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Helper function to rebuild a URL from its parsed components.
	 *
	 * @since 0.1.0
	 * @param array $parsedUrl The parsed URL components.
	 * @return string The reconstructed URL.
	 */
	private function unparseUrl( array $parsedUrl ): string {
		$scheme   = isset( $parsedUrl['scheme'] ) ? $parsedUrl['scheme'] . '://' : '';
		$host     = $parsedUrl['host'] ?? '';
		$port     = isset( $parsedUrl['port'] ) ? ':' . $parsedUrl['port'] : '';
		$user     = $parsedUrl['user'] ?? '';
		$pass     = isset( $parsedUrl['pass'] ) ? ':' . $parsedUrl['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = $parsedUrl['path'] ?? '';
		$query    = isset( $parsedUrl['query'] ) ? '?' . $parsedUrl['query'] : '';
		$fragment = isset( $parsedUrl['fragment'] ) ? '#' . $parsedUrl['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}

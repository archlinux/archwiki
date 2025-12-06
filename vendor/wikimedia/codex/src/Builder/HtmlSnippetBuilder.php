<?php
/**
 * HtmlSnippetBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `HtmlSnippet` class for handling safe HTML content
 * within the Codex design system.
 *
 * This class ensures that only trusted HTML content is passed and rendered directly,
 * without any escaping, ensuring safe handling of HTML snippets.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use Wikimedia\Codex\Component\HtmlSnippet;

/**
 * HtmlSnippetBuilder
 *
 * This class implements the builder pattern to construct instances of HtmlSnippet.
 * It provides a fluent interface for setting various properties and building the
 * final immutable object with predefined configurations and immutability.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class HtmlSnippetBuilder {

	/**
	 * The safe HTML content to be rendered.
	 */
	protected string $content;

	/**
	 * Additional HTML attributes for the container element.
	 */
	protected array $attributes = [];

	/**
	 * Set the HTML content.
	 *
	 * This method allows updating the HTML content of the snippet after the object has been instantiated.
	 *
	 * @since 0.1.0
	 * @param string $content The new HTML content to set.
	 * @param-taint $content exec_html Callers are responsible for escaping.
	 * @return $this
	 */
	public function setContent( string $content ): self {
		$this->content = $content;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the container element.
	 *
	 * This method allows custom HTML attributes to be added to the container element,
	 * such as `class`, `id`, `data-*`, or any other valid attributes.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the HtmlSnippet component object.
	 * This method constructs the immutable HtmlSnippet object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return HtmlSnippet The constructed HtmlSnippet.
	 */
	public function build(): HtmlSnippet {
		return new HtmlSnippet( $this->content, $this->attributes );
	}
}

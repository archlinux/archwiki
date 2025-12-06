<?php
/**
 * HtmlSnippet.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `HtmlSnippet` class, responsible for managing
 * the behavior and properties of the corresponding component.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Component;

/**
 * HtmlSnippet
 *
 * This class is part of the Codex PHP library and is responsible for
 * representing an immutable object. It is primarily intended for use
 * with a builder class to construct its instances.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class HtmlSnippet {

	/**
	 * The safe HTML content to be rendered.
	 */
	private string $content;

	/**
	 * Additional HTML attributes for the container element.
	 */
	private array $attributes;

	/**
	 * Constructor for the HtmlSnippet component.
	 *
	 * Initializes a HtmlSnippet instance with the specified content and attributes.
	 *
	 * @param string $content The safe HTML content to be rendered.
	 * @param-taint $content exec_html Callers are responsible for escaping.
	 * @param array $attributes Additional HTML attributes for the container element.
	 */
	public function __construct( string $content, array $attributes ) {
		$this->content = $content;
		$this->attributes = $attributes;
	}

	/**
	 * Get the raw HTML content.
	 *
	 * This method returns the raw HTML content for rendering.
	 *
	 * @since 0.1.0
	 * @return string The raw HTML content.
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Retrieve additional HTML attributes for the the html snippet element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the html snippet element. These attributes can be used to enhance the appearance, accessibility,
	 *  or functionality of the html snippet element.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the component's HTML representation.
	 *
	 * This method generates the HTML markup for the component, incorporating relevant properties
	 * and any additional attributes. The component is structured using appropriate HTML elements
	 * as defined by the implementation.
	 *
	 * @since 0.1.0
	 * @return string The generated HTML string for the component.
	 */
	public function __toString(): string {
		return $this->content;
	}
}

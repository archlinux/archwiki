<?php
/**
 * Tabs.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Tabs` class, responsible for managing
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

use Wikimedia\Codex\Renderer\TabsRenderer;

/**
 * Tabs
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
class Tabs {

	/**
	 * The ID for the tabs component.
	 */
	private string $id;

	/**
	 * An array of Tab component objects representing each tab in the component.
	 */
	private array $tabs;

	/**
	 * Additional HTML attributes for the `<form>` element.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the tabs.
	 */
	private TabsRenderer $renderer;

	/**
	 * Constructor for the Tabs component.
	 *
	 * Initializes a Tabs instance with the specified properties.
	 *
	 * @param string $id The ID for the tabs component.
	 * @param array $tabs An array of Tab component objects.
	 * @param array $attributes Additional HTML attributes for the tabs component.
	 * @param TabsRenderer $renderer The renderer to use for rendering the tabs.
	 */
	public function __construct(
		string $id,
		array $tabs,
		array $attributes,
		TabsRenderer $renderer
	) {
		$this->id = $id;
		$this->tabs = $tabs;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the HTML ID for the tabs.
	 *
	 * This method returns the HTML `id` attribute value for the tabs element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the tabs.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the array of tabs in the component.
	 *
	 * This method returns an array of `Tab` objects that represent each tab in the component.
	 * Each tab contains properties such as label, content, selected state, and disabled state.
	 *
	 * @since 0.1.0
	 * @return array The array of `Tab` objects representing the tabs in the component.
	 */
	public function getTabs(): array {
		return $this->tabs;
	}

	/**
	 * Get the additional HTML attributes for the `<form>` element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the `<form>` element
	 * that wraps the tabs. These attributes can be used to enhance accessibility or integrate with JavaScript.
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
	public function getHtml(): string {
		return $this->renderer->render( $this );
	}
}

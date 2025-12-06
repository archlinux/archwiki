<?php
/**
 * Accordion.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Accordion` class, responsible for managing
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

use Wikimedia\Codex\Renderer\AccordionRenderer;

/**
 * Accordion
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
class Accordion {

	/**
	 * The ID for the accordion.
	 */
	private string $id;

	/**
	 * The accordion's header title.
	 */
	private string $title;

	/**
	 * Additional text under the title.
	 */
	private string $description;

	/**
	 * The content shown when the accordion is expanded.
	 */
	private string $content;

	/**
	 * Determines if the accordion is expanded by default.
	 */
	private bool $isOpen;

	/**
	 * Additional HTML attributes for the <details> element.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the accordion.
	 */
	private AccordionRenderer $renderer;

	/**
	 * Constructor for the Accordion component.
	 *
	 * Initializes an Accordion instance with the specified properties.
	 *
	 * @param string $id The ID for the accordion.
	 * @param string $title The accordion's header title.
	 * @param string $description Additional text under the title.
	 * @param string $content The content shown when the accordion is expanded.
	 * @param bool $isOpen Determines if the accordion is expanded by default.
	 * @param array $attributes Additional HTML attributes for the <details> element.
	 * @param AccordionRenderer $renderer The renderer to use for rendering the accordion.
	 */
	public function __construct(
		string $id,
		string $title,
		string $description,
		string $content,
		bool $isOpen,
		array $attributes,
		AccordionRenderer $renderer
	) {
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;
		$this->content = $content;
		$this->isOpen = $isOpen;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the accordion's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the accordion element.
	 * The ID is useful for targeting the accordion with JavaScript, CSS, or accessibility features.
	 *
	 * @since 0.1.0
	 * @return string The ID of the accordion element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the accordion's title.
	 *
	 * This method returns the title that is displayed in the header of the accordion.
	 * The title is the main clickable element that users interact with to expand or collapse
	 * the accordion's content.
	 *
	 * @since 0.1.0
	 * @return string The title of the accordion.
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the accordion's description.
	 *
	 * This method returns the description text that appears below the title in the accordion's header.
	 * The description provides additional context or details about the accordion's content.
	 *
	 * @since 0.1.0
	 * @return string The description of the accordion.
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get the accordion's content.
	 *
	 * This method returns the content that is displayed when the accordion is expanded.
	 * The content can include various HTML elements such as text, images, and more.
	 *
	 * @since 0.1.0
	 * @return string The content of the accordion.
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Check if the accordion is open by default.
	 *
	 * This method indicates whether the accordion is set to be expanded by default when the page loads.
	 * If true, the accordion is displayed in an expanded state.
	 *
	 * @since 0.1.0
	 * @return bool True if the accordion is open by default, false otherwise.
	 */
	public function isOpen(): bool {
		return $this->isOpen;
	}

	/**
	 * Retrieve additional HTML attributes for the <details> element.
	 *
	 * This method returns an array of additional HTML attributes that will be applied
	 * to the `<details>` element of the accordion. The attributes are properly escaped
	 * to ensure security and prevent XSS vulnerabilities.
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

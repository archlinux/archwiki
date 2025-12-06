<?php
/**
 * Message.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Message` class, responsible for managing
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

use Wikimedia\Codex\Renderer\MessageRenderer;

/**
 * Message
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
class Message {

	/**
	 * The ID for the Message.
	 */
	private string $id;

	/**
	 * The content displayed inside the message box.
	 */
	private string $content;

	/**
	 * The type of the message (e.g., 'notice', 'warning', 'error', 'success').
	 */
	private string $type;

	/**
	 * Whether the message box should be displayed inline.
	 */
	private bool $inline;

	/**
	 * The heading displayed at the top of the message content.
	 */
	private string $heading;

	/**
	 * The CSS class name for the icon.
	 */
	private string $iconClass;

	/**
	 * Additional HTML attributes for the message box.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the message.
	 */
	private MessageRenderer $renderer;

	/**
	 * Constructor for the Message component.
	 *
	 * Initializes a Message instance with the specified properties.
	 *
	 * @param string $id The ID for the Message.
	 * @param string $content The content displayed inside the message box.
	 * @param string $type The type of the message.
	 * @param bool $inline Whether the message box should be displayed inline.
	 * @param string $heading The heading displayed at the top of the message content.
	 * @param string $iconClass The CSS class name for the icon.
	 * @param array $attributes Additional HTML attributes for the message box.
	 * @param MessageRenderer $renderer The renderer to use for rendering the message.
	 */
	public function __construct(
		string $id,
		string $content,
		string $type,
		bool $inline,
		string $heading,
		string $iconClass,
		array $attributes,
		MessageRenderer $renderer
	) {
		$this->id = $id;
		$this->content = $content;
		$this->type = $type;
		$this->inline = $inline;
		$this->heading = $heading;
		$this->iconClass = $iconClass;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the Message's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the Message element.
	 * The ID can be used for targeting the message with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Message element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the content of the message box.
	 *
	 * This method returns the text or HTML content that is displayed inside the message box.
	 * The content provides the primary feedback or information that the message conveys to the user.
	 *
	 * @since 0.1.0
	 * @return string The content of the message box.
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Get the type of the message box.
	 *
	 * This method returns the type of the message, which determines its visual style.
	 * The type can be one of the following: 'notice', 'warning', 'error', 'success'.
	 *
	 * @since 0.1.0
	 * @return string The type of the message box.
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Check if the message box is displayed inline.
	 *
	 * This method returns a boolean indicating whether the message box is displayed inline,
	 * without additional padding, background color, or border.
	 *
	 * @since 0.1.0
	 * @return bool True if the message box is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the heading of the message box.
	 *
	 * This method returns the heading text that is prominently displayed at the top of the message content.
	 * The heading helps to quickly convey the primary purpose or topic of the message.
	 *
	 * @since 0.1.0
	 * @return string The heading text of the message box.
	 */
	public function getHeading(): string {
		return $this->heading;
	}

	/**
	 * Get the CSS class name for the icon.
	 *
	 * This method returns the CSS class name for the icon displayed in the message box,
	 * enhancing the visual representation of the message.
	 *
	 * @since 0.1.0
	 * @return string The CSS class name for the icon.
	 */
	public function getIconClass(): string {
		return $this->iconClass;
	}

	/**
	 * Get the additional HTML attributes for the message box.
	 *
	 * This method returns an associative array of additional HTML attributes that are applied
	 * to the outer `<div>` element of the message box. These attributes can be used to enhance
	 * accessibility or integrate with JavaScript.
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

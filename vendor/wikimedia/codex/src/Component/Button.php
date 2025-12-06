<?php
/**
 * Button.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Button` class, responsible for managing
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

use Wikimedia\Codex\Renderer\ButtonRenderer;

/**
 * Button
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
class Button {

	/**
	 * The ID for the button.
	 */
	private string $id;

	/**
	 * The text label displayed on the button.
	 */
	private string $label;

	/**
	 * The visual action style of the button (e.g., default, progressive, destructive).
	 */
	private string $action;

	/**
	 * The size of the button (e.g., medium, large).
	 */
	private string $size;

	/**
	 * The type of the button (e.g., button, submit, reset).
	 */
	private string $type;

	/**
	 * The visual prominence of the button (e.g., normal, primary, quiet).
	 */
	private string $weight;

	/**
	 * The CSS class for an icon, if the button includes one.
	 */
	private ?string $iconClass;

	/**
	 * Indicates if the button is icon-only (no text).
	 */
	private bool $iconOnly;

	/**
	 * Indicates if the button is disabled.
	 */
	private bool $disabled;

	/**
	 * Additional HTML attributes for the button element.
	 */
	private array $attributes;

	/**
	 * The renderer instance used to render the button.
	 */
	private ButtonRenderer $renderer;

	/**
	 * Constructor for the Button component.
	 *
	 * Initializes a Button instance with the specified properties.
	 *
	 * @param string $id The ID for the button.
	 * @param string $label The text label displayed on the button.
	 * @param string $action The visual action style of the button.
	 * @param string $size The size of the button.
	 * @param string $type The type of the button.
	 * @param string $weight The visual prominence of the button.
	 * @param string|null $iconClass The CSS class for an icon, if any.
	 * @param bool $iconOnly Indicates if the button is icon-only.
	 * @param bool $disabled Indicates if the button is disabled.
	 * @param array $attributes Additional HTML attributes for the button element.
	 * @param ButtonRenderer $renderer The renderer to use for rendering the button.
	 */
	public function __construct(
		string $id,
		string $label,
		string $action,
		string $size,
		string $type,
		string $weight,
		?string $iconClass,
		bool $iconOnly,
		bool $disabled,
		array $attributes,
		ButtonRenderer $renderer
	) {
		$this->id = $id;
		$this->label = $label;
		$this->action = $action;
		$this->size = $size;
		$this->type = $type;
		$this->weight = $weight;
		$this->iconClass = $iconClass;
		$this->iconOnly = $iconOnly;
		$this->disabled = $disabled;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the button's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the button element.
	 * The ID is useful for targeting the button with JavaScript, CSS, or accessibility features.
	 *
	 * @since 0.1.0
	 * @return string The ID of the button element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the label displayed on the button.
	 *
	 * This method returns the text label that is displayed on the button. The label provides context
	 * to users about the button's action, ensuring that it is understandable and accessible.
	 *
	 * @since 0.1.0
	 * @return string The label of the button.
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get the action style of the button.
	 *
	 * This method returns the action style of the button, indicating the visual style
	 * that reflects the nature of the action it represents (e.g., 'default', 'progressive', 'destructive').
	 *
	 * @since 0.1.0
	 * @return string The action style of the button.
	 */
	public function getAction(): string {
		return $this->action;
	}

	/**
	 * Get the weight style of the button.
	 *
	 * This method returns the weight style of the button, which indicates its visual prominence
	 * (e.g., 'normal', 'primary', 'quiet').
	 *
	 * @since 0.1.0
	 * @return string The weight style of the button.
	 */
	public function getWeight(): string {
		return $this->weight;
	}

	/**
	 * Get the size of the button.
	 *
	 * This method returns the size of the button, determining whether it is 'medium' or 'large'.
	 *
	 * @since 0.1.0
	 * @return string The size of the button.
	 */
	public function getSize(): string {
		return $this->size;
	}

	/**
	 * Get the type of the button.
	 *
	 * This method returns the type of the button, determining whether it is 'button', 'submit', or 'reset'.
	 *
	 * @since 0.1.0
	 * @return string The type of the button.
	 */
	public function getType(): string {
		return $this->type ?: 'button';
	}

	/**
	 * Get the icon class for the button.
	 *
	 * This method returns the CSS class used for the icon displayed inside the button.
	 * The icon is an additional visual element that can be included in the button to enhance usability.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Check if the button is icon-only.
	 *
	 * This method returns a boolean value indicating whether the button is icon-only (i.e., displays only an icon
	 * without any text). This is useful in scenarios where space is limited.
	 *
	 * @since 0.1.0
	 * @return bool True if the button is icon-only, false otherwise.
	 */
	public function isIconOnly(): bool {
		return $this->iconOnly;
	}

	/**
	 * Check if the button is disabled.
	 *
	 * This method returns a boolean value indicating whether the button is disabled.
	 *
	 * @since 0.1.0
	 * @return bool True if the button is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Retrieve additional HTML attributes for the button element.
	 *
	 * This method returns an associative array of additional HTML attributes that will be applied
	 * to the <button> element. These attributes can be used to enhance customization, improve accessibility,
	 * and facilitate JavaScript integration.
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

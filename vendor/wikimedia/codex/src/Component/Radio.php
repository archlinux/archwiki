<?php
/**
 * Radio.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Radio` class, responsible for managing
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

use Wikimedia\Codex\Renderer\RadioRenderer;

/**
 * Radio
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
class Radio {

	/**
	 * The ID for the Radio input.
	 */
	protected string $inputId;

	/**
	 * The name attribute for the radio input.
	 */
	protected string $name;

	/**
	 * The label object for the radio.
	 */
	protected Label $label;

	/**
	 * The value associated with the radio input.
	 */
	protected string $value;

	/**
	 * Indicates if the radio is selected by default.
	 */
	protected bool $checked;

	/**
	 * Indicates if the radio is disabled.
	 */
	protected bool $disabled;

	/**
	 * Indicates if the radio should be displayed inline.
	 */
	protected bool $inline;

	/**
	 * Additional HTML attributes for the input.
	 */
	private array $inputAttributes;

	/**
	 * Additional attributes for the wrapper element.
	 */
	private array $wrapperAttributes;

	/**
	 * The renderer instance used to render the radio.
	 */
	protected RadioRenderer $renderer;

	/**
	 * Constructor for the Radio component.
	 *
	 * Initializes a Radio instance with the specified properties.
	 *
	 * @param string $id The ID for the radio input.
	 * @param string $name The name attribute for the radio input.
	 * @param Label $label The Label object associated with the radio.
	 * @param string $value The value associated with the radio input.
	 * @param bool $checked Indicates if the radio is selected by default.
	 * @param bool $disabled Indicates if the radio is disabled.
	 * @param bool $inline Indicates if the radio should be displayed inline.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param RadioRenderer $renderer The renderer to use for rendering the radio.
	 */
	public function __construct(
		string $id,
		string $name,
		Label $label,
		string $value,
		bool $checked,
		bool $disabled,
		bool $inline,
		array $inputAttributes,
		array $wrapperAttributes,
		RadioRenderer $renderer
	) {
		$this->inputId = $id;
		$this->name = $name;
		$this->label = $label;
		$this->value = $value;
		$this->checked = $checked;
		$this->disabled = $disabled;
		$this->inline = $inline;
		$this->inputAttributes = $inputAttributes;
		$this->wrapperAttributes = $wrapperAttributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the ID for the radio input.
	 *
	 * This method returns the unique identifier for the radio input element. The ID is used to associate the input
	 * with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @return string The ID for the radio input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute for the radio input.
	 *
	 * This method returns the name attribute used to identify form data after the form is submitted.
	 * It is crucial when handling groups of radio buttons where only one option can be selected at a time.
	 *
	 * @since 0.1.0
	 * @return string The name attribute for the radio input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the label object for the radio input.
	 *
	 * This method returns the label object that provides a descriptive label for the radio button.
	 * The label is crucial for accessibility and usability.
	 *
	 * @since 0.1.0
	 * @return Label The label object for the radio button.
	 */
	public function getLabel(): Label {
		return $this->label;
	}

	/**
	 * Get the value associated with the radio input.
	 *
	 * This method returns the value that is submitted when the radio button is selected and the form is submitted.
	 * This is particularly important when dealing with groups of radio buttons where each needs a distinct value.
	 *
	 * @since 0.1.0
	 * @return string The value for the radio input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Check if the radio is selected by default.
	 *
	 * This method returns a boolean value indicating whether the radio button is selected by default.
	 * If true, the radio button is rendered in a checked state.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the radio is disabled.
	 *
	 * This method returns a boolean value indicating whether the radio button is disabled,
	 * preventing user interaction. A disabled radio button cannot be selected or deselected by the user.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the radio button should be displayed inline.
	 *
	 * This method returns a boolean value indicating whether the radio button and its label are displayed
	 * inline with other elements. Inline radio buttons are typically used when multiple radio buttons need
	 * to appear on the same line.
	 *
	 * @since 0.1.0
	 * @return bool True if the radio button is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the additional HTML attributes for the radio input.
	 *
	 * This method returns an associative array of custom HTML attributes for the radio input element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getInputAttributes(): array {
		return $this->inputAttributes;
	}

	/**
	 * Get additional HTML attributes for the outer wrapper element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * @since 0.1.0
	 * @return array The associative array of HTML attributes for the wrapper element.
	 */
	public function getWrapperAttributes(): array {
		return $this->wrapperAttributes;
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

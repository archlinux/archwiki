<?php
/**
 * Checkbox.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Checkbox` class, responsible for managing
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

use Wikimedia\Codex\Renderer\CheckboxRenderer;

/**
 * Checkbox
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
class Checkbox {

	/**
	 * The ID for the checkbox input.
	 */
	protected string $inputId;

	/**
	 * The name attribute for the checkbox input.
	 */
	protected string $name;

	/**
	 * The label object for the checkbox.
	 */
	protected Label $label;

	/**
	 * The value associated with the checkbox input.
	 */
	protected string $value;

	/**
	 * Indicates if the checkbox is selected by default.
	 */
	protected bool $checked;

	/**
	 * Indicates if the checkbox is disabled.
	 */
	protected bool $disabled;

	/**
	 * Indicates if the checkbox should be displayed inline.
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
	 * The renderer instance used to render the checkbox.
	 */
	protected CheckboxRenderer $renderer;

	/**
	 * Constructor for the Checkbox component.
	 *
	 * Initializes a Checkbox instance with the specified properties.
	 *
	 * @param string $id The ID for the checkbox input.
	 * @param string $name The name attribute for the checkbox input.
	 * @param Label $label The Label object associated with the checkbox.
	 * @param string $value The value associated with the checkbox input.
	 * @param bool $checked Indicates if the checkbox is selected by default.
	 * @param bool $disabled Indicates if the checkbox is disabled.
	 * @param bool $inline Indicates if the checkbox should be displayed inline.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param CheckboxRenderer $renderer The renderer to use for rendering the checkbox.
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
		CheckboxRenderer $renderer
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
	 * Get the ID of the checkbox input.
	 *
	 * This method returns the unique identifier used for the checkbox input element.
	 * The ID is essential for associating the checkbox with its label and for targeting with JavaScript or CSS.
	 *
	 * @since 0.1.0
	 * @return string The ID of the checkbox input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute of the checkbox input.
	 *
	 * This method returns the name attribute, which is used to identify the checkbox when the form is submitted.
	 * The name is especially important when handling multiple checkboxes as part of a group.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the checkbox input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the label of the checkbox input.
	 *
	 * This method returns the label text associated with the checkbox. The label provides a descriptive
	 * name that helps users understand the purpose of the checkbox.
	 *
	 * @since 0.1.0
	 * @return Label The label of the checkbox input.
	 */
	public function getLabel(): Label {
		return $this->label;
	}

	/**
	 * Get the value of the checkbox input.
	 *
	 * This method returns the value that is submitted when the checkbox is checked and the form is submitted.
	 * The value is important for differentiating between various checkboxes in a group.
	 *
	 * @since 0.1.0
	 * @return string The value of the checkbox input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Check if the checkbox is selected.
	 *
	 * This method returns a boolean indicating whether the checkbox is checked by default.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the checkbox is disabled.
	 *
	 * This method returns a boolean indicating whether the checkbox is disabled, which prevents user interaction.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the checkbox is displayed inline.
	 *
	 * This method returns a boolean indicating whether the checkbox
	 * and its label are displayed inline with other elements.
	 *
	 * @since 0.1.0
	 * @return bool True if the checkbox is displayed inline, false otherwise.
	 */
	public function isInline(): bool {
		return $this->inline;
	}

	/**
	 * Get the additional HTML attributes for the checkbox input.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied
	 * to the checkbox element. These attributes can be used to customize the checkboxes behavior
	 * and appearance or to enhance its integration with JavaScript.
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

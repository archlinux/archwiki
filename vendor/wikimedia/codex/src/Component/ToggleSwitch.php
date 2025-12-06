<?php
/**
 * ToggleSwitch.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `ToggleSwitch` class, responsible for managing
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

use Wikimedia\Codex\Renderer\ToggleSwitchRenderer;

/**
 * ToggleSwitch
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
class ToggleSwitch {

	/**
	 * The ID for the toggle switch input.
	 */
	protected string $inputId;

	/**
	 * The name attribute for the toggle switch input.
	 */
	protected string $name;

	/**
	 * The value associated with the toggle switch input.
	 */
	protected string $value;

	/**
	 * The label object for the toggle switch.
	 */
	protected Label $label;

	/**
	 * Whether the toggle is checked by default.
	 */
	protected bool $checked;

	/**
	 * Whether the toggle is disabled.
	 */
	protected bool $disabled;

	/**
	 * Additional HTML attributes for the input element.
	 */
	protected array $inputAttributes;

	/**
	 * Additional HTML attributes for the wrapper element.
	 */
	protected array $wrapperAttributes;

	/**
	 * The renderer instance used to render the toggle switch.
	 */
	protected ToggleSwitchRenderer $renderer;

	/**
	 * Constructor for the ToggleSwitch component.
	 *
	 * @param string $inputId The ID for the toggle switch input.
	 * @param string $name The name attribute for the toggle switch input.
	 * @param string $value The value associated with the toggle switch input.
	 * @param Label $label The label object for the toggle switch.
	 * @param bool $checked Whether the toggle switch is checked by default.
	 * @param bool $disabled Whether the toggle switch is disabled.
	 * @param array $inputAttributes Additional HTML attributes for the input element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param ToggleSwitchRenderer $renderer The renderer to use for rendering the toggle switch.
	 */
	public function __construct(
		string $inputId,
		string $name,
		string $value,
		Label $label,
		bool $checked,
		bool $disabled,
		array $inputAttributes,
		array $wrapperAttributes,
		ToggleSwitchRenderer $renderer
	) {
		$this->inputId = $inputId;
		$this->name = $name;
		$this->value = $value;
		$this->label = $label;
		$this->checked = $checked;
		$this->disabled = $disabled;
		$this->inputAttributes = $inputAttributes;
		$this->wrapperAttributes = $wrapperAttributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the ID for the toggle switch input.
	 *
	 * This method returns the unique identifier for the toggle switch input element.
	 * The ID is used to associate the input with its corresponding label and for any JavaScript or CSS targeting.
	 *
	 * @since 0.1.0
	 * @return string The ID for the toggle switch input.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Get the name attribute of the toggle switch input.
	 *
	 * This method returns the name attribute, which is used to identify the toggle switch when the form is submitted.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the toggle switch input.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the value associated with the toggle switch input.
	 *
	 * This method returns the value that is submitted when the toggle switch is checked and the form is submitted.
	 *
	 * @since 0.1.0
	 * @return string The value of the toggle switch input.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the label object for the toggle switch.
	 *
	 * This method returns the label object that provides a descriptive label for the toggle switch.
	 * The label is crucial for accessibility and usability.
	 *
	 * @since 0.1.0
	 * @return Label The label object for the toggle switch.
	 */
	public function getLabel(): Label {
		return $this->label;
	}

	/**
	 * Check if the toggle switch is checked by default.
	 *
	 * This method returns a boolean value indicating whether the toggle switch is checked by default.
	 * If true, the toggle switch is rendered in a checked state.
	 *
	 * @since 0.1.0
	 * @return bool True if the toggle switch is checked, false otherwise.
	 */
	public function isChecked(): bool {
		return $this->checked;
	}

	/**
	 * Check if the toggle switch is disabled.
	 *
	 * This method returns a boolean value indicating whether the toggle switch is disabled,
	 * preventing user interaction. A disabled toggle switch cannot be checked or unchecked by the user.
	 *
	 * @since 0.1.0
	 * @return bool True if the toggle switch is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the additional HTML attributes for the toggle switch input.
	 *
	 * This method returns an associative array of custom HTML attributes for the toggle switch input element,
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

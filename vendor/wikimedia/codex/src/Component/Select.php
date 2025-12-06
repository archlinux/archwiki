<?php
/**
 * Select.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Select` class, responsible for managing
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

use Wikimedia\Codex\Renderer\SelectRenderer;

/**
 * Select
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
class Select {

	/**
	 * The ID for the select element.
	 */
	private string $id;

	/**
	 * The options available in the select dropdown.
	 */
	private array $options;

	/**
	 * The optGroups that group options under labels in the select dropdown.
	 */
	private array $optGroups;

	/**
	 * The selected option value.
	 */
	private ?string $selectedOption;

	/**
	 * Additional HTML attributes for the `<select>` element.
	 */
	private array $attributes;

	/**
	 * Indicates if the select element is disabled.
	 */
	private bool $disabled;

	/**
	 * The renderer instance used to render the select.
	 */
	private SelectRenderer $renderer;

	/**
	 * Constructor for the Select component.
	 *
	 * @param string $id The ID for the select element.
	 * @param array $options An array of options for the select element.
	 * @param array $optGroups An array of optGroups for grouping options.
	 * @param string|null $selectedOption The value of the selected option, if any.
	 * @param array $attributes Additional HTML attributes for the select element.
	 * @param bool $disabled Indicates whether the select element is disabled.
	 * @param SelectRenderer $renderer The renderer to use for rendering the select element.
	 */
	public function __construct(
		string $id,
		array $options,
		array $optGroups,
		?string $selectedOption,
		array $attributes,
		bool $disabled,
		SelectRenderer $renderer
	) {
		$this->id = $id;
		$this->options = $options;
		$this->optGroups = $optGroups;
		$this->selectedOption = $selectedOption;
		$this->attributes = $attributes;
		$this->disabled = $disabled;
		$this->renderer = $renderer;
	}

	/**
	 * Get the Select's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the select element, which is used
	 * for identifying the select component in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Select element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the options for the select element.
	 *
	 * This method returns an associative array where the keys are the option values
	 * and the values are the display text shown to the user.
	 *
	 * @since 0.1.0
	 * @return array The associative array of options for the select element.
	 */
	public function getOptions(): array {
		return $this->options;
	}

	/**
	 * Get the optGroups for the select element.
	 *
	 * This method returns an associative array of optGroups, where each key is a label for a group
	 * and the value is an array of options within that group.
	 *
	 * @since 0.1.0
	 * @return array The associative array of optGroups for the select element.
	 */
	public function getOptGroups(): array {
		return $this->optGroups;
	}

	/**
	 * Get the additional HTML attributes for the `<select>` element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the `<select>` element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the currently selected option value.
	 *
	 * This method returns the value of the currently selected option in the select element.
	 *
	 * @since 0.1.0
	 * @return string|null The value of the currently selected option, or null if no option is selected.
	 */
	public function getSelectedOption(): ?string {
		return $this->selectedOption;
	}

	/**
	 * Check if the select element is disabled.
	 *
	 * This method returns a boolean value indicating whether the select element is disabled.
	 * If true, the `disabled` attribute is present on the `<select>` element.
	 *
	 * @since 0.1.0
	 * @return bool True if the select element is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
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

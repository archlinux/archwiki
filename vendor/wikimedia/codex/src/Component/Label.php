<?php
/**
 * Label.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Label` class, responsible for managing
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

use Wikimedia\Codex\Renderer\LabelRenderer;

/**
 * Label
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
class Label {

	/**
	 * The text displayed inside the label.
	 */
	protected string $labelText;

	/**
	 * The ID of the input/control this label is associated with.
	 */
	protected string $inputId;

	/**
	 * Whether the associated input field is optional.
	 */
	protected bool $optional;

	/**
	 * Whether the label should be visually hidden but accessible to screen readers.
	 */
	protected bool $visuallyHidden;

	/**
	 * Whether the label should be rendered as a `<legend>` element, typically used within a `<fieldset>`.
	 */
	protected bool $isLegend;

	/**
	 * The description text that provides additional information about the input field.
	 */
	protected string $description;

	/**
	 * The ID of the description element, useful for the `aria-describedby` attribute.
	 */
	protected ?string $descriptionId;

	/**
	 * Whether the label is for a disabled field or input.
	 */
	protected bool $disabled;

	/**
	 * The CSS class for an icon displayed before the label text.
	 */
	protected ?string $iconClass;

	/**
	 * Additional HTML attributes for the label element.
	 */
	protected array $attributes;

	/**
	 * The ID attribute for the label.
	 */
	protected ?string $id;

	/**
	 * The renderer instance used to render the label.
	 */
	protected LabelRenderer $renderer;

	/**
	 * Constructor for the Label component.
	 *
	 * Initializes a Label instance with the specified properties.
	 *
	 * @param string $labelText The text displayed inside the label.
	 * @param-taint $labelText exec_html Callers are responsible for escaping
	 * @param string $inputId The ID of the input/control this label is associated with.
	 * @param bool $optional Indicates whether the associated input field is optional.
	 * @param bool $visuallyHidden Indicates whether the label should be visually hidden.
	 * @param bool $isLegend Indicates if the label should be rendered as a `<legend>` element.
	 * @param string $description The description text for the label.
	 * @param string|null $descriptionId The ID of the description element.
	 * @param bool $disabled Indicates whether the label is for a disabled input.
	 * @param string|null $iconClass The CSS class for an icon before the label text.
	 * @param array $attributes Additional HTML attributes for the label element.
	 * @param string|null $id The ID attribute for the label.
	 * @param LabelRenderer $renderer The renderer to use for rendering the label.
	 */
	public function __construct(
		string $labelText,
		string $inputId,
		bool $optional,
		bool $visuallyHidden,
		bool $isLegend,
		string $description,
		?string $descriptionId,
		bool $disabled,
		?string $iconClass,
		array $attributes,
		?string $id,
		LabelRenderer $renderer
	) {
		$this->labelText = $labelText;
		$this->inputId = $inputId;
		$this->optional = $optional;
		$this->visuallyHidden = $visuallyHidden;
		$this->isLegend = $isLegend;
		$this->description = $description;
		$this->descriptionId = $descriptionId;
		$this->disabled = $disabled;
		$this->iconClass = $iconClass;
		$this->attributes = $attributes;
		$this->id = $id;
		$this->renderer = $renderer;
	}

	/**
	 * Get the text displayed inside the label.
	 *
	 * This method returns the text that is displayed inside the label. The label text provides
	 * a descriptive title for the associated input field.
	 *
	 * @since 0.1.0
	 * @return string The text of the label.
	 */
	public function getLabelText(): string {
		return $this->labelText;
	}

	/**
	 * Get the ID of the input/control this label is associated with.
	 *
	 * This method returns the ID of the input element that this label is associated with. The ID
	 * is crucial for linking the label to its corresponding input, ensuring accessibility.
	 *
	 * @since 0.1.0
	 * @return string The ID of the input element.
	 */
	public function getInputId(): string {
		return $this->inputId;
	}

	/**
	 * Check if the associated input field is optional.
	 *
	 * This method returns a boolean indicating whether the associated input field is optional.
	 * If true, an "(optional)" flag is typically displayed next to the label text.
	 *
	 * @since 0.1.0
	 * @return bool True if the input field is optional, false otherwise.
	 */
	public function isOptional(): bool {
		return $this->optional;
	}

	/**
	 * Check if the label is visually hidden but accessible to screen readers.
	 *
	 * This method returns a boolean indicating whether the label is visually hidden
	 * while still being accessible to screen readers. This is useful for forms where
	 * labels need to be accessible but not displayed.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is visually hidden, false otherwise.
	 */
	public function isVisuallyHidden(): bool {
		return $this->visuallyHidden;
	}

	/**
	 * Check if the label is rendered as a `<legend>` element.
	 *
	 * This method returns a boolean indicating whether the label is rendered as a `<legend>`
	 * element, typically used within a `<fieldset>` for grouping related inputs.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is rendered as a `<legend>`, false otherwise.
	 */
	public function isLegend(): bool {
		return $this->isLegend;
	}

	/**
	 * Get the description text associated with the label.
	 *
	 * This method returns the description text that provides additional information about the
	 * input field. The description is linked to the input via the `aria-describedby` attribute.
	 *
	 * @since 0.1.0
	 * @return string The description text for the label.
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Get the ID of the description element.
	 *
	 * This method returns the ID of the description element, which is useful for associating
	 * the description with an input via the `aria-describedby` attribute.
	 *
	 * @since 0.1.0
	 * @return string|null The ID for the description element, or null if not set.
	 */
	public function getDescriptionId(): ?string {
		return $this->descriptionId;
	}

	/**
	 * Check if the label is for a disabled field or input.
	 *
	 * This method returns a boolean indicating whether the label is associated with a disabled
	 * input field, applying the appropriate styles.
	 *
	 * @since 0.1.0
	 * @return bool True if the label is for a disabled input, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Get the icon class used before the label text.
	 *
	 * This method returns the CSS class for the icon displayed before the label text, if applicable.
	 * The icon enhances the visual appearance of the label.
	 *
	 * @since 0.1.0
	 * @return string|null The CSS class for the icon, or null if no icon is set.
	 */
	public function getIconClass(): ?string {
		return $this->iconClass;
	}

	/**
	 * Get the additional HTML attributes for the label element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied
	 * to the label element. These attributes can be used for customization or accessibility.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Get the ID of the label element.
	 *
	 * @since 0.1.0
	 * @return string|null The ID of the label, or null if not set.
	 */
	public function getId(): ?string {
		return $this->id;
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

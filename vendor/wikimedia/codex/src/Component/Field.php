<?php
/**
 * Field.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Field` class, responsible for managing
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

use Wikimedia\Codex\Renderer\FieldRenderer;

/**
 * Field
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
class Field {

	/**
	 * The ID for the fieldset.
	 */
	protected string $id;

	/**
	 * The label for the fieldset.
	 */
	protected Label $label;

	/**
	 * Indicates if the fields should be wrapped in a fieldset with a legend.
	 */
	protected bool $isFieldset;

	/**
	 * An array of fields (as HTML strings) included within the fieldset or div.
	 */
	protected array $fields;

	/**
	 * Additional HTML attributes for the fieldset or div element.
	 */
	protected array $attributes;

	/**
	 * The renderer instance used to render the field.
	 */
	protected FieldRenderer $renderer;

	/**
	 * Constructor for the Field component.
	 *
	 * Initializes a Field instance with the specified properties.
	 *
	 * @param string $id The ID for the fieldset or div.
	 * @param Label $label The label for the fieldset.
	 * @param bool $isFieldset Indicates if fields are wrapped in a fieldset.
	 * @param array $fields An array of fields (HTML strings).
	 * @param array $attributes Additional HTML attributes for the fieldset or div.
	 * @param FieldRenderer $renderer The renderer to use for rendering the field.
	 */
	public function __construct(
		string $id,
		Label $label,
		bool $isFieldset,
		array $fields,
		array $attributes,
		FieldRenderer $renderer
	) {
		$this->id = $id;
		$this->label = $label;
		$this->isFieldset = $isFieldset;
		$this->fields = $fields;
		$this->attributes = $attributes;
		$this->renderer = $renderer;
	}

	/**
	 * Get the fieldset or div's HTML ID attribute.
	 *
	 * This method returns the ID that is assigned to the fieldset or div element. The ID is useful for targeting
	 * the field with JavaScript, CSS, or for accessibility purposes.
	 *
	 * @since 0.1.0
	 * @return string The ID of the fieldset or div element.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the label of the field.
	 *
	 * This method returns the label object associated with the field. The label provides a descriptive
	 * name that helps users understand the purpose of the field.
	 *
	 * @since 0.1.0
	 * @return Label The label of the field.
	 */
	public function getLabel(): Label {
		return $this->label;
	}

	/**
	 * Check if the fields are wrapped in a fieldset with a legend.
	 *
	 * This method returns a boolean indicating whether the fields are wrapped in a `<fieldset>`
	 * element with a `<legend>`. If false, the fields are wrapped in a `<div>` with a `<label>`.
	 *
	 * @since 0.1.0
	 * @return bool True if the fields are wrapped in a fieldset, false otherwise.
	 */
	public function isFieldset(): bool {
		return $this->isFieldset;
	}

	/**
	 * Get the fields included within the fieldset or div.
	 *
	 * This method returns an array of fields (as HTML strings) that are included
	 * within the fieldset or div.
	 *
	 * @since 0.1.0
	 * @return array The fields included in the fieldset or div.
	 */
	public function getFields(): array {
		return $this->fields;
	}

	/**
	 * Get the additional HTML attributes for the fieldset or div element.
	 *
	 * This method returns an associative array of additional HTML attributes
	 * that are applied to the fieldset or div element.
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

<?php
/**
 * FieldBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Field` class, a builder for constructing
 * form fields with labels, inputs, or controls using the Codex design system.
 *
 * A form field includes a label, an input or control, and an optional validation message.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use InvalidArgumentException;
use Wikimedia\Codex\Component\Field;
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Renderer\FieldRenderer;

/**
 * FieldBuilder
 *
 * This class implements the builder pattern to construct instances of Field.
 * It provides a fluent interface for setting various properties and building the
 * final immutable object with predefined configurations and immutability.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class FieldBuilder {

	/**
	 * The ID for the fieldset.
	 */
	protected string $id = '';

	/**
	 * The label object for the field.
	 */
	protected ?Label $label = null;

	/**
	 * Indicates if the fields should be wrapped in a fieldset with a legend.
	 */
	protected bool $isFieldset = false;

	/**
	 * An array of fields (as HTML strings) included within the fieldset or div.
	 */
	protected array $fields = [];

	/**
	 * Additional HTML attributes for the fieldset or div element.
	 */
	protected array $attributes = [];

	/**
	 * The ID of the input or control element that the label is associated with.
	 */
	protected string $inputId = '';

	/**
	 * The renderer instance used to render the field.
	 */
	protected FieldRenderer $renderer;

	/**
	 * Constructor for the FieldBuilder class.
	 *
	 * @param FieldRenderer $renderer The renderer to use for rendering the field.
	 */
	public function __construct( FieldRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the label's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the field element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the label for the field.
	 *
	 * This method accepts a Label object which provides a descriptive label for the field.
	 *
	 * @since 0.1.0
	 * @param Label $label The Label object for the field.
	 * @return $this Returns the Checkbox instance for method chaining.
	 */
	public function setLabel( Label $label ): self {
		$this->label = $label;

		return $this;
	}

	/**
	 * Set whether the fields should be wrapped in a fieldset with a legend.
	 *
	 * When set to `true`, this method wraps the fields in a `<fieldset>` element with a `<legend>`.
	 * If set to `false`, the fields are wrapped in a `<div>` with a `<label>` instead.
	 *
	 * @since 0.1.0
	 * @param bool $isFieldset Whether to wrap fields in a fieldset.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setIsFieldset( bool $isFieldset ): self {
		$this->isFieldset = $isFieldset;

		return $this;
	}

	/**
	 * Set the fields within the fieldset.
	 *
	 * This method accepts an array of fields (as HTML strings) to be included within the fieldset or a `<div>`.
	 * It allows grouping of related fields together under a common legend or label for better organization.
	 *
	 * @since 0.1.0
	 * @param array $fields The array of fields to include in the fieldset.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setFields( array $fields ): self {
		$this->fields = $fields;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the fieldset or div element.
	 *
	 * This method allows custom HTML attributes to be added to the fieldset or div element, such as `id`, `data-*`,
	 * `aria-*`, or any other valid attributes. These attributes can be used to further customize the fieldset or div,
	 * enhance accessibility, or provide additional metadata.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $field->setAttributes([
	 *         'id' => 'user-info-fieldset',
	 *         'data-category' => 'user-data',
	 *         'aria-labelledby' => 'legend-user-info'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Field instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Build and return the Field component object.
	 * This method constructs the immutable Field object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Field The constructed Field.
	 */
	public function build(): Field {
		if ( !$this->label ) {
			throw new InvalidArgumentException( "The 'label' is required for Field." );
		}

		return new Field(
			$this->id,
			$this->label,
			$this->isFieldset,
			$this->fields,
			$this->attributes,
			$this->renderer,
		);
	}
}

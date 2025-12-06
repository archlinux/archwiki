<?php
/**
 * LabelBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Label` class, a builder for constructing
 * label components using the Codex design system.
 *
 * A Label provides a descriptive title for a form input. Having labels is essential when filling out
 * a form, since each field is associated with its label.
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
use Wikimedia\Codex\Component\Label;
use Wikimedia\Codex\Renderer\LabelRenderer;

/**
 * LabelBuilder
 *
 * This class implements the builder pattern to construct instances of Label.
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
class LabelBuilder {

	/**
	 * The text displayed inside the label.
	 */
	protected string $labelText = '';

	/**
	 * The ID of the input/control this label is associated with.
	 */
	protected string $inputId = '';

	/**
	 * Whether the associated input field is optional.
	 */
	protected bool $optional = false;

	/**
	 * Whether the label should be visually hidden but accessible to screen readers.
	 */
	protected bool $visuallyHidden = false;

	/**
	 * Whether the label should be rendered as a `<legend>` element, typically used within a `<fieldset>`.
	 */
	protected bool $isLegend = false;

	/**
	 * The description text that provides additional information about the input field.
	 */
	protected string $description = '';

	/**
	 * The ID of the description element, useful for the `aria-describedby` attribute.
	 */
	protected ?string $descriptionId = null;

	/**
	 * Whether the label is for a disabled field or input.
	 */
	protected bool $disabled = false;

	/**
	 * The CSS class for an icon displayed before the label text.
	 */
	protected ?string $iconClass = null;

	/**
	 * Additional HTML attributes for the label element.
	 */
	protected array $attributes = [];

	/**
	 * The ID attribute for the label.
	 */
	protected ?string $id = null;

	/**
	 * The renderer instance used to render the label.
	 */
	protected LabelRenderer $renderer;

	/**
	 * Constructor for the LabelBuilder class.
	 *
	 * @param LabelRenderer $renderer The renderer to use for rendering the label.
	 */
	public function __construct( LabelRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the label text.
	 *
	 * This method specifies the text that will be displayed inside the label.
	 * The label text provides a descriptive title for the associated input field.
	 *
	 * @since 0.1.0
	 * @param string $labelText The text of the label.
	 * @param-taint $labelText exec_html Callers are responsible for escaping.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setLabelText( string $labelText ): self {
		if ( trim( $labelText ) === '' ) {
			throw new InvalidArgumentException( "Label text cannot be empty." );
		}
		$this->labelText = $labelText;

		return $this;
	}

	/**
	 * Set the ID of the input/control this label is associated with.
	 *
	 * This method sets the 'for' attribute of the label, linking it to an input element.
	 * This connection is important for accessibility and ensures that clicking the label focuses the input.
	 *
	 * Example usage:
	 *
	 *     $label->setInputId('username');
	 *
	 * @since 0.1.0
	 * @param string $inputId The ID of the input element.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setInputId( string $inputId ): self {
		$this->inputId = $inputId;

		return $this;
	}

	/**
	 * Set the optional flag.
	 *
	 * This method indicates whether the associated input field is optional.
	 * If true, an "(optional)" flag will be displayed next to the label text.
	 *
	 * Example usage:
	 *
	 *     $label->setOptionalFlag(true);
	 *
	 * @since 0.1.0
	 * @param bool $optional Whether the label is for an optional input.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setOptional( bool $optional ): self {
		$this->optional = $optional;

		return $this;
	}

	/**
	 * Set whether the label should be visually hidden.
	 *
	 * This method determines whether the label should be visually hidden while still being accessible to screen
	 * readers. Useful for forms where labels need to be read by assistive technologies but not displayed.
	 *
	 * Example usage:
	 *
	 *     $label->setVisuallyHidden(true);
	 *
	 * @since 0.1.0
	 * @param bool $visuallyHidden Whether the label should be visually hidden.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setVisuallyHidden( bool $visuallyHidden ): self {
		$this->visuallyHidden = $visuallyHidden;

		return $this;
	}

	/**
	 * Set whether this component should output a `<legend>` element.
	 *
	 * This method determines whether the label should be rendered as a `<legend>` element,
	 * typically used within a `<fieldset>` for grouping related inputs.
	 *
	 * Example usage:
	 *
	 *     $label->setIsLegend(true);
	 *
	 * @since 0.1.0
	 * @param bool $isLegend Whether to render the label as a `<legend>`.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setIsLegend( bool $isLegend ): self {
		$this->isLegend = $isLegend;

		return $this;
	}

	/**
	 * Set the description text for the label.
	 *
	 * This method adds a short description below the label, providing additional information about the input field.
	 * The description is linked to the input via the `aria-describedby` attribute for accessibility.
	 *
	 * Example usage:
	 *
	 *     $label->setDescriptionText('Please enter a valid email.');
	 *
	 * @since 0.1.0
	 * @param string $description The description text for the label.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDescription( string $description ): self {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set the ID of the description element.
	 *
	 * This method sets the ID attribute for the description element, which is useful for associating
	 * the description with an input via the `aria-describedby` attribute.
	 *
	 * Example usage:
	 *
	 *     $label->setDescriptionId('username-desc');
	 *
	 * @since 0.1.0
	 * @param string|null $descriptionId The ID for the description element.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDescriptionId( ?string $descriptionId ): self {
		$this->descriptionId = $descriptionId ?: null;

		return $this;
	}

	/**
	 * Set whether the label is for a disabled field or input.
	 *
	 * This method marks the label as associated with a disabled input, applying the appropriate styles.
	 *
	 * Example usage:
	 *
	 *     $label->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Whether the label is for a disabled input.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set an icon before the label text.
	 *
	 * This method allows for an icon to be displayed before the label text, specified by a CSS class.
	 * The icon enhances the visual appearance of the label.
	 *
	 * Example usage:
	 *
	 *     $label->setIcon('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string|null $iconClass The CSS class for the icon.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setIconClass( ?string $iconClass ): self {
		$this->iconClass = $iconClass;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the label element.
	 *
	 * This method allows custom HTML attributes to be added to the label element, such as `id`, `class`, or `data-*`
	 * attributes. These attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $label->setAttributes(['class' => 'custom-label-class', 'data-info' => 'additional-info']);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Label instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Set the label's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the label element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Build and return the Label component object.
	 * This method constructs the immutable Label object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Label The constructed Label.
	 */
	public function build(): Label {
		if ( !$this->labelText ) {
			throw new InvalidArgumentException( "The 'labelText' is required for Label." );
		}

		return new Label(
			$this->labelText,
			$this->inputId,
			$this->optional,
			$this->visuallyHidden,
			$this->isLegend,
			$this->description,
			$this->descriptionId,
			$this->disabled,
			$this->iconClass,
			$this->attributes,
			$this->id,
			$this->renderer,
		);
	}
}

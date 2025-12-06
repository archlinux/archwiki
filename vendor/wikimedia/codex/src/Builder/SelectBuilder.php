<?php
/**
 * SelectBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Select` class, a builder for constructing
 * select elements using the Codex design system.
 *
 * A Select is an input with a dropdown menu of predefined, selectable items.
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
use Wikimedia\Codex\Component\Select;
use Wikimedia\Codex\Renderer\SelectRenderer;

/**
 * SelectBuilder
 *
 * This class implements the builder pattern to construct instances of Select.
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
class SelectBuilder {

	/**
	 * The ID for the select.
	 */
	protected string $id = '';

	/**
	 * The options available in the select dropdown.
	 */
	protected array $options = [];

	/**
	 * The optGroups that group options under labels in the select dropdown.
	 */
	protected array $optGroups = [];

	/**
	 * The selected option value.
	 */
	protected ?string $selectedOption = null;

	/**
	 * Additional HTML attributes for the `<select>` element.
	 */
	protected array $attributes = [];

	/**
	 * Indicates if the select element is disabled.
	 */
	protected bool $disabled = false;

	/**
	 * The renderer instance used to render the select.
	 */
	protected SelectRenderer $renderer;

	/**
	 * Constructor for the SelectBuilder class.
	 *
	 * @param SelectRenderer $renderer The renderer to use for rendering the select.
	 */
	public function __construct( SelectRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Selects HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Select element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set one or more options for the select element.
	 *
	 * This method allows one or more options to be added to the select dropdown.
	 * Each option can be provided as a simple key-value pair, or as an array with `value`, `text`,
	 * and `selected` keys for more complex options.
	 *
	 * Example usage:
	 *
	 *     // Using key-value pairs:
	 *     $select->setOptions([
	 *         'value1' => 'Label 1',
	 *         'value2' => 'Label 2'
	 *     ]);
	 *
	 *     // Using an array for more complex options:
	 *     $select->setOptions([
	 *         ['value' => 'value1', 'text' => 'Label 1', 'selected' => true],
	 *         ['value' => 'value2', 'text' => 'Label 2']
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $options An array of options, either as key-value pairs or
	 *                       arrays with `value`, `text`, and `selected` keys.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setOptions( array $options ): self {
		if ( !$options ) {
			throw new InvalidArgumentException( 'At least one option is required for the select element.' );
		}

		foreach ( $options as $key => $option ) {
			if ( is_string( $key ) ) {
				// Handle key-value pairs for simple options
				$this->options[] = ( new OptionBuilder )
					->setValue( $key )
					->setText( $option )->build();
			} elseif ( is_array( $option ) ) {
				// Handle more complex array structure for options
				$this->options[] = ( new OptionBuilder )
					->setValue( $option['value'] )
					->setText( $option['text'] )
					->setSelected( $option['selected'] ?? false )->build();
			}
		}

		return $this;
	}

	/**
	 * Set the optGroups for the select element.
	 *
	 * This method allows options to be grouped under labels in the select dropdown.
	 * Each optGroup can contain options that are either key-value pairs or arrays with `value`,
	 * `text`, and `selected` keys for more complex options.
	 *
	 * Example usage:
	 *
	 *     $select->setOptGroups([
	 *         'Group 1' => [
	 *             'value1' => 'Option 1',
	 *             ['value' => 'value2', 'text' => 'Option 2', 'selected' => true]
	 *         ],
	 *         'Group 2' => [
	 *             'value3' => 'Option 3',
	 *             'value4' => 'Option 4'
	 *         ]
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $optGroups An associative array of optGroups where keys are labels and values are arrays of options.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setOptGroups( array $optGroups ): self {
		foreach ( $optGroups as $label => $groupOptions ) {
			$group = [];
			foreach ( $groupOptions as $key => $option ) {
				if ( is_string( $key ) ) {
					// Handle key-value pairs for options in the group
					$group[] = ( new OptionBuilder )
						->setValue( $key )
						->setText( $option )->build();
				} elseif ( is_array( $option ) ) {
					// Handle more complex array structure for group options
					$group[] = ( new OptionBuilder )
						->setValue( $option['value'] )
						->setText( $option['text'] )
						->setSelected( $option['selected'] ?? false )->build();
				}
			}
			$this->optGroups[$label] = $group;
		}

		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<select>` element.
	 *
	 * This method allows custom HTML attributes to be added to the `<select>` element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used
	 * to enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $select->setAttributes([
	 *         'id' => 'select-example',
	 *         'data-category' => 'selection',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set whether the select element should be disabled.
	 *
	 * This method disables the select element, preventing user interaction.
	 * When called with `true`, the `disabled` attribute is added to the `<select>` element.
	 *
	 * Example usage:
	 *
	 *     $select->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the select element should be disabled.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the selected option for the select element.
	 *
	 * This method specifies which option should be selected by default when the select element is rendered.
	 *
	 * @since 0.1.0
	 * @param string|null $value The value of the option to be selected, or null to unset the selection.
	 * @return $this Returns the Select instance for method chaining.
	 */
	public function setSelectedOption( ?string $value ): self {
		$this->selectedOption = $value;

		return $this;
	}

	/**
	 * Build and return the Select component object.
	 * This method constructs the immutable Select object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Select The constructed Select.
	 */
	public function build(): Select {
		return new Select(
			$this->id,
			$this->options,
			$this->optGroups,
			$this->selectedOption,
			$this->attributes,
			$this->disabled,
			$this->renderer
		);
	}
}

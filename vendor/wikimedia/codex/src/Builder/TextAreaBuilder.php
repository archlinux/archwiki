<?php
/**
 * TextAreaBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `TextArea` class, a builder for constructing
 * textarea components using the Codex design system.
 *
 * A TextArea is a multi-line text input that allows manual resizing if needed.
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
use Wikimedia\Codex\Component\TextArea;
use Wikimedia\Codex\Renderer\TextAreaRenderer;

/**
 * TextAreaBuilder
 *
 * This class implements the builder pattern to construct instances of TextArea.
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
class TextAreaBuilder {

	/**
	 * Allowed validation statuses for the TextArea.
	 */
	private const ALLOWED_STATUSES = [
		'default',
		'error',
		'warning',
		'success'
	];

	/**
	 * The ID for the textarea.
	 */
	protected string $id = '';

	/**
	 * The name attribute of the textarea element.
	 */
	private string $name = '';

	/**
	 * The default value of the textarea.
	 */
	private string $value = '';

	/**
	 * Additional HTML attributes for the TextArea.
	 */
	private array $textAreaAttributes = [];

	/**
	 * Additional attributes for the wrapper element.
	 */
	private array $wrapperAttributes = [];

	/**
	 * Indicates whether the textarea is disabled. If true, the textarea is not editable.
	 */
	private bool $disabled = false;

	/**
	 * Indicates whether the textarea is read-only. If true, the content cannot be modified but can be selected.
	 */
	private bool $readonly = false;

	/**
	 * Indicates if a start icon should be displayed in the textarea. If true, a start icon is included.
	 */
	private bool $hasStartIcon = false;

	/**
	 * Indicates if an end icon should be displayed in the textarea. If true, an end icon is included.
	 */
	private bool $hasEndIcon = false;

	/**
	 * CSS class for the start icon. Used for styling the start icon.
	 */
	private string $startIconClass = '';

	/**
	 * CSS class for the end icon. Used for styling the end icon.
	 */
	private string $endIconClass = '';

	/**
	 * Placeholder text displayed in the textarea when it is empty.
	 */
	private string $placeholder = '';

	/**
	 * Validation status for the textarea.
	 */
	private string $status = 'default';

	/**
	 * The renderer instance used to render the textarea.
	 */
	protected TextAreaRenderer $renderer;

	/**
	 * Constructor for the TextAreaBuilder class.
	 *
	 * @param TextAreaRenderer $renderer The renderer to use for rendering the textarea.
	 */
	public function __construct( TextAreaRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the TextArea HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the TextArea element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the name attribute for the textarea element.
	 *
	 * This method sets the name attribute for the textarea element, which is used to identify
	 * the textarea form control when submitting the form data.
	 *
	 * Example usage:
	 *
	 *     $textArea->setName('description');
	 *
	 * @since 0.1.0
	 * @param string $name The name attribute for the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setName( string $name ): self {
		$this->name = $name;

		return $this;
	}

	/**
	 * Set the default content inside the textarea.
	 *
	 * This method sets the initial content that will be displayed inside the textarea.
	 * The content can be prefilled with a default value if necessary.
	 *
	 * Example usage:
	 *
	 *     $textArea->setValue('Default content...');
	 *
	 * @since 0.1.0
	 * @param mixed $value The content to be displayed inside the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setValue( $value ): self {
		$this->value = $value;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the textarea element.
	 *
	 * This method allows custom HTML attributes to be added to the textarea element,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes that enhance functionality or accessibility.
	 *
	 * Example usage:
	 *
	 *     $textArea->setTextAreaAttributes([
	 *         'id' => 'text-area-id',
	 *         'data-category' => 'input',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $textAreaAttributes An associative array of HTML attributes for the textarea element.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setTextAreaAttributes( array $textAreaAttributes ): self {
		foreach ( $textAreaAttributes as $key => $value ) {
			$this->textAreaAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer wrapper element.
	 *
	 * This method allows custom HTML attributes to be added to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * Example usage:
	 *
	 *        $textArea->setWrapperAttributes(['id' => 'custom-wrapper']);
	 *
	 * @since 0.1.0
	 * @param array $wrapperAttributes An associative array of HTML attributes.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setWrapperAttributes( array $wrapperAttributes ): self {
		foreach ( $wrapperAttributes as $key => $value ) {
			$this->wrapperAttributes[$key] = $value;
		}

		return $this;
	}

	/**
	 * Set the disabled state for the textarea.
	 *
	 * This method disables the textarea, making it uneditable and visually distinct.
	 * The disabled attribute is useful for read-only forms or when the input is temporarily inactive.
	 *
	 * Example usage:
	 *
	 *     $textArea->setDisabled(true);
	 *
	 * @since 0.1.0
	 * @param bool $disabled Indicates whether the textarea should be disabled.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setDisabled( bool $disabled ): self {
		$this->disabled = $disabled;

		return $this;
	}

	/**
	 * Set the readonly state for the textarea.
	 *
	 * This method makes the textarea read-only, meaning users can view the content
	 * but cannot modify it. The readonly attribute is useful when displaying static content.
	 *
	 * Example usage:
	 *
	 *     $textArea->setReadonly(true);
	 *
	 * @since 0.1.0
	 * @param bool $readonly Indicates whether the textarea should be read-only.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setReadonly( bool $readonly ): self {
		$this->readonly = $readonly;

		return $this;
	}

	/**
	 * Set whether the textarea has a start icon.
	 *
	 * This method specifies whether the textarea should have an icon at the start.
	 * The icon can be used to visually indicate the type of input expected in the textarea.
	 *
	 * Example usage:
	 *
	 *     $textArea->setHasStartIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasStartIcon Indicates whether the textarea has a start icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setHasStartIcon( bool $hasStartIcon ): self {
		$this->hasStartIcon = $hasStartIcon;

		return $this;
	}

	/**
	 * Set whether the textarea has an end icon.
	 *
	 * This method specifies whether the textarea should have an icon at the end.
	 * The icon can be used to visually indicate additional functionality or context related to the input.
	 *
	 * Example usage:
	 *
	 *     $textArea->setHasEndIcon(true);
	 *
	 * @since 0.1.0
	 * @param bool $hasEndIcon Indicates whether the textarea has an end icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setHasEndIcon( bool $hasEndIcon ): self {
		$this->hasEndIcon = $hasEndIcon;

		return $this;
	}

	/**
	 * Set the CSS class for the start icon.
	 *
	 * This method specifies the CSS class that will be applied to the start icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textArea->setStartIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $startIconClass The CSS class for the start icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setStartIconClass( string $startIconClass ): self {
		$this->startIconClass = $startIconClass;

		return $this;
	}

	/**
	 * Set the CSS class for the end icon.
	 *
	 * This method specifies the CSS class that will be applied to the end icon.
	 * The class can be used to style the icon or apply a background image.
	 *
	 * Example usage:
	 *
	 *     $textArea->setEndIconClass('icon-class-name');
	 *
	 * @since 0.1.0
	 * @param string $endIconClass The CSS class for the end icon.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setEndIconClass( string $endIconClass ): self {
		$this->endIconClass = $endIconClass;

		return $this;
	}

	/**
	 * Set the placeholder text for the textarea element.
	 *
	 * This method specifies the placeholder text that will be displayed inside the textarea
	 * when it is empty. The placeholder provides a hint to the user about the expected input.
	 *
	 * Example usage:
	 *
	 *     $textArea->setPlaceholder('Rationale...');
	 *
	 * @since 0.1.0
	 * @param string $placeholder The placeholder text for the textarea.
	 * @return $this Returns the TextArea instance for method chaining.
	 */
	public function setPlaceholder( string $placeholder ): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	/**
	 * Set the validation status for the textarea.
	 *
	 * @since 0.1.0
	 * @param string $status Current validation status.
	 * @return $this
	 */
	public function setStatus( string $status ): self {
		if ( !in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			throw new InvalidArgumentException( "Invalid status: $status" );
		}
		$this->status = $status;

		return $this;
	}

	/**
	 * Build and return the TextArea component object.
	 * This method constructs the immutable TextArea object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return TextArea The constructed TextArea.
	 */
	public function build(): TextArea {
		return new TextArea(
			$this->id,
			$this->name,
			$this->value,
			$this->textAreaAttributes,
			$this->wrapperAttributes,
			$this->disabled,
			$this->readonly,
			$this->hasStartIcon,
			$this->hasEndIcon,
			$this->startIconClass,
			$this->endIconClass,
			$this->placeholder,
			$this->status,
			$this->renderer
		);
	}
}

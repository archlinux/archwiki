<?php
/**
 * TextArea.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `TextArea` class, responsible for managing
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

use Wikimedia\Codex\Renderer\TextAreaRenderer;

/**
 * TextArea
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
class TextArea {

	/**
	 * The ID for the textarea.
	 */
	protected string $id;

	/**
	 * The name attribute of the textarea element.
	 */
	private string $name;

	/**
	 * The default value of the textarea.
	 */
	private string $value;

	/**
	 * Additional HTML attributes for the TextArea.
	 */
	private array $textAreaAttributes;

	/**
	 * Additional attributes for the wrapper element.
	 */
	private array $wrapperAttributes;

	/**
	 * Indicates whether the textarea is disabled. If true, the textarea is not editable.
	 */
	private bool $disabled;

	/**
	 * Indicates whether the textarea is read-only. If true, the content cannot be modified but can be selected.
	 */
	private bool $readonly;

	/**
	 * Indicates if a start icon should be displayed in the textarea. If true, a start icon is included.
	 */
	private bool $hasStartIcon;

	/**
	 * Indicates if an end icon should be displayed in the textarea. If true, an end icon is included.
	 */
	private bool $hasEndIcon;

	/**
	 * CSS class for the start icon. Used for styling the start icon.
	 */
	private string $startIconClass;

	/**
	 * CSS class for the end icon. Used for styling the end icon.
	 */
	private string $endIconClass;

	/**
	 * Placeholder text displayed in the textarea when it is empty.
	 */
	private string $placeholder;

	/**
	 * Validation status of the textarea (default, error, warning, or success).
	 */
	private string $status;

	/**
	 * The renderer instance used to render the textarea.
	 */
	protected TextAreaRenderer $renderer;

	/**
	 * Constructor for the TextArea component.
	 *
	 * Initializes a TextArea instance with the specified properties.
	 *
	 * @param string $id The ID for the textarea element.
	 * @param string $name The name attribute for the textarea.
	 * @param string $value The default value of the textarea.
	 * @param array $textAreaAttributes Additional HTML attributes for the textarea element.
	 * @param array $wrapperAttributes Additional HTML attributes for the wrapper element.
	 * @param bool $disabled Indicates whether the textarea is disabled.
	 * @param bool $readonly Indicates whether the textarea is read-only.
	 * @param bool $hasStartIcon Indicates if a start icon should be displayed.
	 * @param bool $hasEndIcon Indicates if an end icon should be displayed.
	 * @param string $startIconClass CSS class for the start icon.
	 * @param string $endIconClass CSS class for the end icon.
	 * @param string $placeholder Placeholder text for the textarea.
	 * @param string $status Validation status.
	 * @param TextAreaRenderer $renderer The renderer to use for rendering the textarea.
	 */
	public function __construct(
		string $id,
		string $name,
		string $value,
		array $textAreaAttributes,
		array $wrapperAttributes,
		bool $disabled,
		bool $readonly,
		bool $hasStartIcon,
		bool $hasEndIcon,
		string $startIconClass,
		string $endIconClass,
		string $placeholder,
		string $status,
		TextAreaRenderer $renderer
	) {
		$this->id = $id;
		$this->name = $name;
		$this->value = $value;
		$this->textAreaAttributes = $textAreaAttributes;
		$this->wrapperAttributes = $wrapperAttributes;
		$this->disabled = $disabled;
		$this->readonly = $readonly;
		$this->hasStartIcon = $hasStartIcon;
		$this->hasEndIcon = $hasEndIcon;
		$this->startIconClass = $startIconClass;
		$this->endIconClass = $endIconClass;
		$this->placeholder = $placeholder;
		$this->status = $status;
		$this->renderer = $renderer;
	}

	/**
	 * Get the HTML ID for the textarea.
	 *
	 * This method returns the HTML `id` attribute value for the textarea element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the textarea.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the name attribute of the textarea element.
	 *
	 * This method returns the name attribute of the textarea, which is used to identify
	 * the textarea form control when submitting the form data.
	 *
	 * @since 0.1.0
	 * @return string The name attribute of the textarea.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get the value of the textarea element.
	 *
	 * This method returns the current content inside the textarea, which could be
	 * the default value or any content that was previously set.
	 *
	 * @since 0.1.0
	 * @return string The value of the textarea.
	 */
	public function getValue(): string {
		return $this->value;
	}

	/**
	 * Get the additional HTML attributes for the textarea element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the textarea.
	 * These attributes can be used to enhance accessibility or integrate with JavaScript.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getTextareaAttributes(): array {
		return $this->textAreaAttributes;
	}

	/**
	 * Get additional HTML attributes for the outer wrapper element.
	 *
	 * This method returns an associative array of custom HTML attributes that are applied to the outer wrapper element,
	 * enhancing its behavior or styling.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getWrapperAttributes(): array {
		return $this->wrapperAttributes;
	}

	/**
	 * Check if the textarea is disabled.
	 *
	 * This method returns a boolean indicating whether the textarea is disabled.
	 * A disabled textarea is not editable and has a distinct visual appearance.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea is disabled, false otherwise.
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the textarea is read-only.
	 *
	 * This method returns a boolean indicating whether the textarea is read-only.
	 * A read-only textarea cannot be modified by the user but can be selected and copied.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea is read-only, false otherwise.
	 */
	public function isReadonly(): bool {
		return $this->readonly;
	}

	/**
	 * Check if the textarea has a start icon.
	 *
	 * This method returns a boolean indicating whether the textarea includes an icon at the start.
	 * The start icon is typically used to visually indicate the type of input expected.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea has a start icon, false otherwise.
	 */
	public function hasStartIcon(): bool {
		return $this->hasStartIcon;
	}

	/**
	 * Check if the textarea has an end icon.
	 *
	 * This method returns a boolean indicating whether the textarea includes an icon at the end.
	 * The end icon is typically used to visually indicate additional functionality or context.
	 *
	 * @since 0.1.0
	 * @return bool True if the textarea has an end icon, false otherwise.
	 */
	public function hasEndIcon(): bool {
		return $this->hasEndIcon;
	}

	/**
	 * Get the CSS class for the start icon.
	 *
	 * This method returns the CSS class applied to the start icon. This class can be used
	 * to style the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return string The CSS class for the start icon.
	 */
	public function getStartIconClass(): string {
		return $this->startIconClass;
	}

	/**
	 * Get the CSS class for the end icon.
	 *
	 * This method returns the CSS class applied to the end icon. This class can be used
	 * to style the icon or apply a background image.
	 *
	 * @since 0.1.0
	 * @return string The CSS class for the end icon.
	 */
	public function getEndIconClass(): string {
		return $this->endIconClass;
	}

	/**
	 * Get the placeholder text of the textarea element.
	 *
	 * This method returns the placeholder text displayed inside the textarea when it is empty.
	 * The placeholder provides a hint to the user about the expected input.
	 *
	 * @since 0.1.0
	 * @return string The placeholder text of the textarea.
	 */
	public function getPlaceholder(): string {
		return $this->placeholder;
	}

	/**
	 * Get the validation status of the textarea.
	 *
	 * This method returns a string value indicating the current validation status, which is used to
	 * add a CSS class that can be used for special styles per status.
	 *
	 * @since 0.1.0
	 * @return string Validation status, e.g. 'default' or 'error'.
	 */
	public function getStatus(): string {
		return $this->status;
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

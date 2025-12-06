<?php
/**
 * InfoChipBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `InfoChip` class, a builder for constructing
 * non-interactive information chip components using the Codex design system.
 *
 * An InfoChip is a non-interactive item that provides information.
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
use Wikimedia\Codex\Component\InfoChip;
use Wikimedia\Codex\Renderer\InfoChipRenderer;

/**
 * InfoChipBuilder
 *
 * This class implements the builder pattern to construct instances of InfoChip.
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
class InfoChipBuilder {

	/**
	 * Allowed status types for the InfoChip.
	 */
	private const ALLOWED_STATUS_TYPES = [
		'notice',
		'warning',
		'error',
		'success'
	];

	/**
	 * The ID for the InfoChip.
	 */
	protected string $id = '';

	/**
	 * The text displayed inside the InfoChip.
	 */
	protected string $text = '';

	/**
	 * The status type, determines chip's visual style.
	 */
	protected string $status = 'notice';

	/**
	 * The CSS class for a custom icon used in the InfoChip, applicable only for the 'notice' status.
	 */
	protected ?string $icon = null;

	/**
	 * Additional HTML attributes for the outer `<div>` element of the InfoChip.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the infoChip.
	 */
	protected InfoChipRenderer $renderer;

	/**
	 * Constructor for the InfoChipBuilder class.
	 *
	 * @param InfoChipRenderer $renderer The renderer to use for rendering the info chip.
	 */
	public function __construct( InfoChipRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the InfoChip's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the InfoChip element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the text content for the info chip.
	 *
	 * This method specifies the text that will be displayed inside the info chip.
	 * The text provides the primary information that the chip conveys.
	 *
	 * @since 0.1.0
	 * @param string $text The text to be displayed inside the info chip.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setText( string $text ): self {
		if ( trim( $text ) === '' ) {
			throw new InvalidArgumentException( 'InfoChip text cannot be empty.' );
		}
		$this->text = $text;

		return $this;
	}

	/**
	 * Set the status type for the info chip.
	 *
	 * This method sets the visual style of the info chip based on its status.
	 * The status can be one of the following:
	 * - 'notice': For general information.
	 * - 'warning': For cautionary information.
	 * - 'error': For error messages.
	 * - 'success': For success messages.
	 *
	 * The status type is applied as a CSS class (`cdx-info-chip--{status}`) to the chip element.
	 *
	 * @since 0.1.0
	 * @param string $status The status type (e.g., 'notice', 'warning', 'error', 'success').
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setStatus( string $status ): self {
		if ( !in_array( $status, self::ALLOWED_STATUS_TYPES, true ) ) {
			throw new InvalidArgumentException( "Invalid status: $status" );
		}
		$this->status = $status;

		return $this;
	}

	/**
	 * Set a custom icon for the "notice" status chip.
	 *
	 * This method specifies a CSS class for a custom icon to be displayed inside the chip.
	 * This option is applicable only for chips with the "notice" status.
	 * Chips with other status types (warning, error, success) do not support custom icons and will ignore this setting.
	 *
	 * @since 0.1.0
	 * @param string|null $icon The CSS class for the custom icon, or null to remove the icon.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setIcon( ?string $icon ): self {
		if ( $this->status === 'notice' && ( $icon !== null && trim( $icon ) === '' ) ) {
			throw new InvalidArgumentException( 'Custom icons are only allowed for "notice" status.' );
		}
		$this->icon = $icon;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the outer `<div>` element.
	 *
	 * This method allows custom HTML attributes to be added to the outer `<div>` element of the info chip,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used to
	 * enhance accessibility or integrate with JavaScript.
	 *
	 * The values of these attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $infoChip->setAttributes([
	 *         'id' => 'info-chip-example',
	 *         'data-category' => 'info',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the InfoChip instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the InfoChip component object.
	 * This method constructs the immutable InfoChip object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return InfoChip The constructed InfoChip.
	 */
	public function build(): InfoChip {
		return new InfoChip(
			$this->id,
			$this->text,
			$this->status,
			$this->icon,
			$this->attributes,
			$this->renderer
		);
	}
}

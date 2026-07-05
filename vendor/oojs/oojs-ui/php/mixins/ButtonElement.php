<?php

namespace OOUI;

use RuntimeException;

/**
 * Element with a button.
 *
 * Buttons are used for controls which can be clicked. They can be configured to use tab indexing
 * and access keys for accessibility purposes.
 *
 * @abstract
 */
trait ButtonElement {

	/**
	 * Button is framed.
	 *
	 * @var bool
	 */
	protected $framed = false;

	/**
	 * Button size.
	 *
	 * @var string
	 */
	protected $size = 'medium';

	/**
	 * @var Tag
	 */
	protected $button;

	/**
	 * @param array $config Configuration options
	 *      - bool $config['framed'] Render button with a frame (default: true)
	 *      - string $config['size'] The size of the button, either 'small', 'medium' or 'large' (default: 'medium')
	 */
	public function initializeButtonElement( array $config = [] ) {
		// Properties
		if ( !$this instanceof Element ) {
			throw new RuntimeException( "ButtonElement trait can only be used on Element instances" );
		}
		$target = $config['button'] ?? new Tag( 'a' );
		$this->button = $target;

		// Initialization
		$this->addClasses( [ 'oo-ui-buttonElement' ] );
		$this->button->addClasses( [ 'oo-ui-buttonElement-button' ] );
		$this->toggleFramed( $config['framed'] ?? true );
		$this->setSize( $config['size'] ?? 'medium' );

		// Add `role="button"` on `<a>` elements, where it's needed
		if ( strtolower( $this->button->getTag() ) === 'a' ) {
			$this->button->setAttributes( [
				'role' => 'button',
			] );
		}

		$this->registerConfigCallback( function ( &$config ) {
			if ( $this->framed !== true ) {
				$config['framed'] = $this->framed;
			}
			if ( $this->size !== 'medium' ) {
				$config['size'] = $this->size;
			}
		} );
	}

	/**
	 * Toggle frame.
	 *
	 * @param bool|null $framed Make button framed, omit to toggle
	 * @return $this
	 */
	public function toggleFramed( $framed = null ) {
		$this->framed = $framed !== null ? (bool)$framed : !$this->framed;
		$this->toggleClasses( [ 'oo-ui-buttonElement-framed' ], $this->framed );
		$this->toggleClasses( [ 'oo-ui-buttonElement-frameless' ], !$this->framed );
		// Changing framed changes the available sizes
		$this->setSize( $this->size );
		return $this;
	}

	/**
	 * Check if button has a frame.
	 *
	 * @return bool Button is framed
	 */
	public function isFramed() {
		return $this->framed;
	}

	/**
	 * Get the button's size.
	 *
	 * @return string The button's size, either 'small', 'medium' or 'large'
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * Set the button's size
	 *
	 * @param string $size The size of the button, either 'small', 'medium' or 'large'
	 * @return $this
	 */
	public function setSize( string $size ) {
		if ( !$this->isFramed() ) {
			// Frameless buttons only support medium size
			$size = 'medium';
		}
		$this->size = $size;
		$this->toggleClasses( [ "oo-ui-buttonElement-size-small" ], $size === 'small' );
		$this->toggleClasses( [ "oo-ui-buttonElement-size-medium" ], $size === 'medium' );
		$this->toggleClasses( [ "oo-ui-buttonElement-size-large" ], $size === 'large' );
		return $this;
	}

	/**
	 * Toggle CSS classes.
	 *
	 * @param array $classes List of classes to add
	 * @param bool|null $toggle Add classes
	 * @return $this
	 */
	abstract public function toggleClasses( array $classes, $toggle = null );

	/**
	 * @param callable $func
	 */
	abstract public function registerConfigCallback( callable $func );
}

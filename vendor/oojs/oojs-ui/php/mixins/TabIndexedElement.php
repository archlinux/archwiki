<?php

namespace OOUI;

/**
 * Element supporting "sequential focus navigation" using the 'tabindex' attribute.
 *
 * @abstract
 */
trait TabIndexedElement {
	/**
	 * Tab index value.
	 *
	 * @var number|null
	 */
	protected $tabIndex = null;

	/**
	 * @var Element
	 */
	protected $tabIndexed;

	/**
	 * @param array $config Configuration options
	 * @param number|null $config['tabIndex'] Tab index value. Use 0 to use default ordering, use -1 to
	 *   prevent tab focusing, use null to suppress the `tabindex` attribute. (default: 0)
	 */
	public function initializeTabIndexedElement( array $config = [] ) {
		$this->tabIndexed = isset( $config['tabIndexed'] ) ? $config['tabIndexed'] : $this;

		// Initialization
		$this->setTabIndex( isset( $config['tabIndex'] ) ? $config['tabIndex'] : 0 );

		$this->registerConfigCallback( function( &$config ) {
			if ( $this->tabIndex !== 0 ) {
				$config['tabIndex'] = $this->tabIndex;
			}
		} );

	}

	/**
	 * Set tab index value.
	 *
	 * @param number|null $tabIndex Tab index value or null for no tab index
	 * @return $this
	 */
	public function setTabIndex( $tabIndex ) {
		$tabIndex = is_numeric( $tabIndex ) ? $tabIndex : null;

		if ( $this->tabIndex !== $tabIndex ) {
			$this->tabIndex = $tabIndex;
			$this->updateTabIndex();
		}

		return $this;
	}

	/**
	 * Update the tabIndex attribute, in case of changes to tabIndex or disabled
	 * state.
	 *
	 * @return $this
	 */
	public function updateTabIndex() {
		$disabled = $this->isDisabled();
		if ( $this->tabIndex !== null ) {
			$this->tabIndexed->setAttributes( [
				// Do not index over disabled elements
				'tabindex' => $disabled ? -1 : $this->tabIndex,
				// ChromeVox and NVDA do not seem to inherit this from parent elements
				'aria-disabled' => ( $disabled ? 'true' : 'false' )
			] );
		} else {
			$this->tabIndexed->removeAttributes( [ 'tabindex', 'aria-disabled' ] );
		}
		return $this;
	}

	/**
	 * Get tab index value.
	 *
	 * @return number|null Tab index value
	 */
	public function getTabIndex() {
		return $this->tabIndex;
	}
}

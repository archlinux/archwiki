<?php

namespace MediaWiki\User\TempUser;

use BadMethodCallException;

/**
 * The real TempUserConfig including internal methods used by TempUserCreator.
 *
 * @since 1.39
 */
class RealTempUserConfig implements TempUserConfig {
	/** @var bool */
	private $enabled = false;

	/** @var array */
	private $serialProviderConfig = [];

	/** @var array */
	private $serialMappingConfig = [];

	/** @var string[] */
	private $autoCreateActions;

	/** @var Pattern|null */
	private $genPattern;

	/** @var Pattern|null */
	private $matchPattern;

	/**
	 * @param array $config See the documentation of $wgAutoCreateTempUser.
	 *   - enabled: bool
	 *   - actions: array
	 *   - genPattern: string
	 *   - matchPattern string, optional
	 *   - serialProvider: array
	 *   - serialMapping: array
	 */
	public function __construct( $config ) {
		if ( $config['enabled'] ?? false ) {
			$this->enabled = true;
			$this->autoCreateActions = $config['actions'];
			$this->genPattern = new Pattern( 'genPattern', $config['genPattern'] );
			if ( isset( $config['matchPattern'] ) ) {
				$this->matchPattern = new Pattern( 'matchPattern', $config['matchPattern'] );
			} else {
				$this->matchPattern = $this->genPattern;
			}
			$this->serialProviderConfig = $config['serialProvider'];
			$this->serialMappingConfig = $config['serialMapping'];
		}
	}

	public function isEnabled() {
		return $this->enabled;
	}

	public function isAutoCreateAction( string $action ) {
		if ( $action === 'create' ) {
			$action = 'edit';
		}
		return $this->enabled
			&& in_array( $action, $this->autoCreateActions, true );
	}

	public function isReservedName( string $name ) {
		return $this->enabled
			&& $this->matchPattern->isMatch( $name );
	}

	public function getPlaceholderName(): string {
		if ( $this->enabled ) {
			return $this->genPattern->generate( '*' );
		} else {
			throw new BadMethodCallException( __METHOD__ . ' is disabled' );
		}
	}

	/**
	 * @internal For TempUserCreator only
	 * @return Pattern
	 */
	public function getGeneratorPattern(): Pattern {
		if ( $this->enabled ) {
			return $this->genPattern;
		} else {
			throw new BadMethodCallException( __METHOD__ . ' is disabled' );
		}
	}

	/**
	 * @internal For TempUserCreator only
	 * @return array
	 */
	public function getSerialProviderConfig(): array {
		return $this->serialProviderConfig;
	}

	/**
	 * @internal For TempUserCreator only
	 * @return array
	 */
	public function getSerialMappingConfig(): array {
		return $this->serialMappingConfig;
	}
}

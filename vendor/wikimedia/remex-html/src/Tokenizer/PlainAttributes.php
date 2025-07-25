<?php

namespace Wikimedia\RemexHtml\Tokenizer;

/**
 * An Attributes implementation which is a simple array proxy.
 */
class PlainAttributes implements Attributes {
	/** @var array */
	protected $data;
	/** @var array<string,Attribute> */
	protected $attrObjects;

	/**
	 * @param array $data
	 */
	public function __construct( $data = [] ) {
		$this->data = $data;
	}

	/** @inheritDoc */
	public function merge( Attributes $other ) {
		foreach ( $other as $name => $value ) {
			if ( !isset( $this[$name] ) ) {
				$this[$name] = $value;
			}
		}
	}

	/** @inheritDoc */
	public function offsetExists( $key ): bool {
		return isset( $this->data[$key] );
	}

	/** @inheritDoc */
	public function &offsetGet( $key ): string {
		return $this->data[$key];
	}

	/** @inheritDoc */
	public function offsetSet( $key, $value ): void {
		$this->data[$key] = $value;
		if ( $this->attrObjects !== null ) {
			$this->attrObjects[$key] = new Attribute( $key, null, null, $key, $value );
		}
	}

	/** @inheritDoc */
	public function offsetUnset( $key ): void {
		unset( $this->data[$key] );
		unset( $this->attrObjects[$key] );
	}

	public function getIterator(): \ArrayIterator {
		return new \ArrayIterator( $this->data );
	}

	/** @inheritDoc */
	public function getValues() {
		return $this->data;
	}

	/** @inheritDoc */
	public function getObjects() {
		if ( $this->attrObjects === null ) {
			$result = [];
			foreach ( $this->data as $name => $value ) {
				$result[$name] = new Attribute( $name, null, null, $name, $value );
			}
			$this->attrObjects = $result;
		}
		return $this->attrObjects;
	}

	/** @inheritDoc */
	public function count() {
		return count( $this->data );
	}

	/** @inheritDoc */
	public function clone() {
		return $this;
	}
}

<?php

declare( strict_types = 1 );

namespace Wikimedia\Dodo;

use Wikimedia\Dodo\Internal\Util;

/******************************************************************************
 * CharacterData.php
 * -----------------
 */
abstract class CharacterData extends Leaf implements \Wikimedia\IDLeDOM\CharacterData, \Countable {
	// DOM mixins
	use ChildNode;
	use NonDocumentTypeChildNode;

	// Stub out methods not yet implemented.
	use \Wikimedia\IDLeDOM\Stub\CharacterData;

	// Helper functions from IDLeDOM
	use \Wikimedia\IDLeDOM\Helper\CharacterData;

	/** @var string */
	protected $_data;

	// Keep track of our internal encoding.
	/** @var bool */
	protected $_isUtf16;

	/**
	 * HACK! For compatibilty with W3C test suite, which assumes that an
	 * access to 'attributes' will return null.
	 * @param string $name
	 * @return mixed
	 */
	protected function _getMissingProp( string $name ) {
		if ( $name === 'attributes' ) {
			return null;
		}
		return parent::_getMissingProp( $name );
	}

	/**
	 * Create a CharacterData with data (in UTF-8 encoding).
	 * @param Document $nodeDocument the owner document
	 * @param string $data contents, in UTF-8 encoding
	 */
	protected function __construct( Document $nodeDocument, string $data ) {
		parent::__construct( $nodeDocument );
		$this->_data = $data;
		$this->_isUtf16 = false;
	}

	/**
	 * @inheritDoc
	 */
	final public function getNodeValue(): ?string {
		return $this->getData();
	}

	/**
	 * @inheritDoc
	 */
	final public function setNodeValue( ?string $value ): void {
		$this->setData( $value ?? '' );
	}

	/**
	 * @inheritDoc
	 */
	final public function getTextContent(): ?string {
		return $this->getData();
	}

	/**
	 * @inheritDoc
	 */
	final public function setTextContent( ?string $value ): void {
		$this->setData( $value ?? '' );
	}

	/**
	 * DOMString substringData(unsigned long offset,
	 *               unsigned long count);
	 * The substringData(offset, count) method must run these steps:
	 *
	 *     If offset is greater than the context object's
	 *     length, throw an INDEX_SIZE_ERR exception and
	 *     terminate these steps.
	 *
	 *     If offset+count is greater than the context
	 *     object's length, return a DOMString whose value is
	 *     the UTF-16 code units from the offsetth UTF-16 code
	 *     unit to the end of data.
	 *
	 *     Return a DOMString whose value is the UTF-16 code
	 *     units from the offsetth UTF-16 code unit to the
	 *     offset+countth UTF-16 code unit in data.
	 *
	 * PORT NOTES:
	 * In Domino.js, checking was done to ensure $offset and $count
	 * were integers and not-undefined. Here we just use type hints.
	 *
	 * The DOM spec requires that the offset and count be in *UTF-16 units*
	 * (and the PHP \DOMText etc classes do tihs as well).  Be careful!
	 *
	 * @param int $offset
	 * @param int $count
	 * @return string
	 */
	public function substringData( int $offset, int $count ): string {
		$data = $this->_getDataUTF16();
		// Silly conversion to an int32 to make our test suites happy
		$offset &= 0xFFFFFFFF;
		$count &= 0xFFFFFFFF;

		if ( $offset * 2 > strlen( $data ) || $offset < 0 || $count < 0 ) {
			Util::error( "IndexSizeError" );
		}

		$result = substr( $data, $offset * 2, $count * 2 );
		return mb_convert_encoding( $result, "utf8", "utf16" );
	}

	/**
	 * void appendData(DOMString data);
	 * The appendData(data) method must append data to the context
	 * object's data.
	 *
	 * PORT NOTES: Again, for the number of arguments, we can just
	 * use the function prototype to check.
	 *
	 * @param string $data
	 */
	public function appendData( string $data ): void {
		$oldData = $this->getData(); // convert to UTF8 representation
		$this->_data = $oldData . $data;
		if ( $this->getIsConnected() ) {
			$this->_nodeDocument->_mutateValue( $this );
		}
	}

	/**
	 * void insertData(unsigned long offset, DOMString data);
	 * The insertData(offset, data) method must run these steps:
	 *
	 *     If offset is greater than the context object's
	 *     length, throw an INDEX_SIZE_ERR exception and
	 *     terminate these steps.
	 *
	 *     Insert data into the context object's data after
	 *     offset UTF-16 code units.
	 *
	 * @param int $offset
	 * @param string $data
	 */
	public function insertData( int $offset, string $data ): void {
		$this->replaceData( $offset, 0, $data );
	}

	/**
	 * void deleteData(unsigned long offset, unsigned long count);
	 * The deleteData(offset, count) method must run these steps:
	 *
	 *     If offset is greater than the context object's
	 *     length, throw an INDEX_SIZE_ERR exception and
	 *     terminate these steps.
	 *
	 *     If offset+count is greater than the context
	 *     object's length var count be length-offset.
	 *
	 *     Starting from offset UTF-16 code units remove count
	 *     UTF-16 code units from the context object's data.
	 *
	 * @param int $offset
	 * @param int $count
	 */
	public function deleteData( int $offset, int $count ): void {
		$this->replaceData( $offset, $count, '' );
	}

	/**
	 * void replaceData(unsigned long offset, unsigned long count,
	 *          DOMString data);
	 *
	 * The replaceData(offset, count, data) method must act as
	 * if the deleteData() method is invoked with offset and
	 * count as arguments followed by the insertData() method
	 * with offset and data as arguments and re-throw any
	 * exceptions these methods might have thrown.
	 *
	 * @param int $offset
	 * @param int $count
	 * @param string $data
	 */
	public function replaceData( int $offset, int $count, string $data ): void {
		$curtext = $this->_getDataUTF16();
		$len = strlen( $curtext ) / 2;
		// convert next text to UTF16 as well
		$data = mb_convert_encoding( $data, "utf16", "utf8" );
		// Silly conversion to an int32 to make our test suites happy
		$offset &= 0xFFFFFFFF;
		$count &= 0xFFFFFFFF;

		if ( $offset > $len || $offset < 0 ) {
			Util::error( "IndexSizeError" );
		}

		if ( $offset + $count > $len ) {
			$count = $len - $offset;
		}

		// Fast path
		if ( $offset === 0 && $count === $len ) {
			$this->_data = $data;
		} else {
			$prefix = substr( $curtext, 0, $offset * 2 );
			$suffix = substr( $curtext, ( $offset + $count ) * 2 );

			$this->_data = $prefix . $data . $suffix;
		}
		if ( $this->getIsConnected() ) {
			$this->_nodeDocument->_mutateValue( $this );
		}
	}

	/** @inheritDoc */
	public function getLength(): int {
		// UTF-16 units
		return strlen( $this->_getDataUTF16() ) / 2;
	}

	/** @inheritDoc */
	public function getData(): string {
		if ( $this->_isUtf16 ) {
			$this->_data = mb_convert_encoding(
				$this->_data, "utf8", "utf16"
			);
			$this->_isUtf16 = false;
		}
		return $this->_data;
	}

	/**
	 * Internal method to convert our internal representation to UTF16 and
	 * return that.
	 * @return string a UTF-16 representation of `data`
	 */
	public function _getDataUTF16(): string {
		if ( !$this->_isUtf16 ) {
			$this->_data = mb_convert_encoding(
				$this->_data, "utf16", "utf8"
			);
			$this->_isUtf16 = true;
		}
		return $this->_data;
	}

	/** @inheritDoc */
	public function setData( ?string $value ): void {
		$this->_data = $value ?? '';
		$this->_isUtf16 = false;
		if ( $this->getIsConnected() ) {
			$this->_nodeDocument->_mutateValue( $this );
		}
	}

	/** @inheritDoc */
	public function _length(): int {
		return $this->getLength();
	}

	/** @inheritDoc */
	public function _empty(): bool {
		return strlen( $this->_data ) === 0; // utf8 or utf16, doesn't matter
	}

	/**
	 * This is a non-standard extension, but it allows count() to be used
	 * on CharacterData nodes in the same way that .length is used on them
	 * in JavaScript -- and makes some of our WPT test cases pass.
	 * @return int
	 */
	public function count(): int {
		return $this->_length();
	}
}

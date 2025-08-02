<?php

// AUTOMATICALLY GENERATED.  DO NOT EDIT.
// Use `composer build` to regenerate.

namespace Wikimedia\IDLeDOM\Helper;

trait HTMLSelectElement {

	// Underscore is used to avoid conflicts with DOM-reserved names
	// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

	/**
	 * Handle an attempt to get a non-existing property on this
	 * object.  The default implementation raises an exception
	 * but the implementor can choose a different behavior:
	 * return null (like JavaScript), dynamically create the
	 * property, etc.
	 * @param string $prop the name of the property requested
	 * @return mixed
	 */
	abstract protected function _getMissingProp( string $prop );

	/**
	 * Handle an attempt to set a non-existing property on this
	 * object.  The default implementation raises an exception
	 * but the implementor can choose a different behavior:
	 * ignore the operation (like JavaScript), dynamically create
	 * the property, etc.
	 * @param string $prop the name of the property requested
	 * @param mixed $value the value to set
	 */
	abstract protected function _setMissingProp( string $prop, $value ): void;

	// phpcs:enable

	/**
	 * @param string $name
	 * @return mixed
	 */
	public function __get( string $name ) {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		switch ( $name ) {
			case "nodeType":
				return $this->getNodeType();
			case "nodeName":
				return $this->getNodeName();
			case "baseURI":
				return $this->getBaseURI();
			case "isConnected":
				return $this->getIsConnected();
			case "ownerDocument":
				return $this->getOwnerDocument();
			case "parentNode":
				return $this->getParentNode();
			case "parentElement":
				return $this->getParentElement();
			case "childNodes":
				return $this->getChildNodes();
			case "firstChild":
				return $this->getFirstChild();
			case "lastChild":
				return $this->getLastChild();
			case "previousSibling":
				return $this->getPreviousSibling();
			case "nextSibling":
				return $this->getNextSibling();
			case "nodeValue":
				return $this->getNodeValue();
			case "textContent":
				return $this->getTextContent();
			case "innerHTML":
				return $this->getInnerHTML();
			case "previousElementSibling":
				return $this->getPreviousElementSibling();
			case "nextElementSibling":
				return $this->getNextElementSibling();
			case "children":
				return $this->getChildren();
			case "firstElementChild":
				return $this->getFirstElementChild();
			case "lastElementChild":
				return $this->getLastElementChild();
			case "childElementCount":
				return $this->getChildElementCount();
			case "assignedSlot":
				return $this->getAssignedSlot();
			case "namespaceURI":
				return $this->getNamespaceURI();
			case "prefix":
				return $this->getPrefix();
			case "localName":
				return $this->getLocalName();
			case "tagName":
				return $this->getTagName();
			case "id":
				return $this->getId();
			case "className":
				return $this->getClassName();
			case "classList":
				return $this->getClassList();
			case "slot":
				return $this->getSlot();
			case "attributes":
				return $this->getAttributes();
			case "shadowRoot":
				return $this->getShadowRoot();
			case "outerHTML":
				return $this->getOuterHTML();
			case "style":
				return $this->getStyle();
			case "contentEditable":
				return $this->getContentEditable();
			case "enterKeyHint":
				return $this->getEnterKeyHint();
			case "isContentEditable":
				return $this->getIsContentEditable();
			case "inputMode":
				return $this->getInputMode();
			case "onload":
				return $this->getOnload();
			case "dataset":
				return $this->getDataset();
			case "nonce":
				return $this->getNonce();
			case "tabIndex":
				return $this->getTabIndex();
			case "title":
				return $this->getTitle();
			case "lang":
				return $this->getLang();
			case "translate":
				return $this->getTranslate();
			case "dir":
				return $this->getDir();
			case "hidden":
				return $this->getHidden();
			case "accessKey":
				return $this->getAccessKey();
			case "accessKeyLabel":
				return $this->getAccessKeyLabel();
			case "draggable":
				return $this->getDraggable();
			case "spellcheck":
				return $this->getSpellcheck();
			case "autocapitalize":
				return $this->getAutocapitalize();
			case "innerText":
				return $this->getInnerText();
			case "offsetParent":
				return $this->getOffsetParent();
			case "offsetTop":
				return $this->getOffsetTop();
			case "offsetLeft":
				return $this->getOffsetLeft();
			case "offsetWidth":
				return $this->getOffsetWidth();
			case "offsetHeight":
				return $this->getOffsetHeight();
			case "autocomplete":
				return $this->getAutocomplete();
			case "autofocus":
				return $this->getAutofocus();
			case "disabled":
				return $this->getDisabled();
			case "form":
				return $this->getForm();
			case "multiple":
				return $this->getMultiple();
			case "name":
				return $this->getName();
			case "required":
				return $this->getRequired();
			case "size":
				return $this->getSize();
			case "type":
				return $this->getType();
			case "options":
				return $this->getOptions();
			case "length":
				return $this->getLength();
			case "selectedOptions":
				return $this->getSelectedOptions();
			case "selectedIndex":
				return $this->getSelectedIndex();
			case "value":
				return $this->getValue();
			case "willValidate":
				return $this->getWillValidate();
			case "validity":
				return $this->getValidity();
			case "validationMessage":
				return $this->getValidationMessage();
			case "labels":
				return $this->getLabels();
			default:
				break;
		}
		'@phan-var \Wikimedia\IDLeDOM\Helper\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\Helper\HTMLSelectElement $this
		return $this->_getMissingProp( $name );
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset( string $name ): bool {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		switch ( $name ) {
			case "nodeType":
				return true;
			case "nodeName":
				return true;
			case "baseURI":
				return true;
			case "isConnected":
				return true;
			case "ownerDocument":
				return $this->getOwnerDocument() !== null;
			case "parentNode":
				return $this->getParentNode() !== null;
			case "parentElement":
				return $this->getParentElement() !== null;
			case "childNodes":
				return true;
			case "firstChild":
				return $this->getFirstChild() !== null;
			case "lastChild":
				return $this->getLastChild() !== null;
			case "previousSibling":
				return $this->getPreviousSibling() !== null;
			case "nextSibling":
				return $this->getNextSibling() !== null;
			case "nodeValue":
				return $this->getNodeValue() !== null;
			case "textContent":
				return $this->getTextContent() !== null;
			case "innerHTML":
				return true;
			case "previousElementSibling":
				return $this->getPreviousElementSibling() !== null;
			case "nextElementSibling":
				return $this->getNextElementSibling() !== null;
			case "children":
				return true;
			case "firstElementChild":
				return $this->getFirstElementChild() !== null;
			case "lastElementChild":
				return $this->getLastElementChild() !== null;
			case "childElementCount":
				return true;
			case "assignedSlot":
				return $this->getAssignedSlot() !== null;
			case "namespaceURI":
				return $this->getNamespaceURI() !== null;
			case "prefix":
				return $this->getPrefix() !== null;
			case "localName":
				return true;
			case "tagName":
				return true;
			case "id":
				return true;
			case "className":
				return true;
			case "classList":
				return true;
			case "slot":
				return true;
			case "attributes":
				return true;
			case "shadowRoot":
				return $this->getShadowRoot() !== null;
			case "outerHTML":
				return true;
			case "style":
				return true;
			case "contentEditable":
				return true;
			case "enterKeyHint":
				return true;
			case "isContentEditable":
				return true;
			case "inputMode":
				return true;
			case "onload":
				return true;
			case "dataset":
				return true;
			case "nonce":
				return true;
			case "tabIndex":
				return true;
			case "title":
				return true;
			case "lang":
				return true;
			case "translate":
				return true;
			case "dir":
				return true;
			case "hidden":
				return true;
			case "accessKey":
				return true;
			case "accessKeyLabel":
				return true;
			case "draggable":
				return true;
			case "spellcheck":
				return true;
			case "autocapitalize":
				return true;
			case "innerText":
				return true;
			case "offsetParent":
				return $this->getOffsetParent() !== null;
			case "offsetTop":
				return true;
			case "offsetLeft":
				return true;
			case "offsetWidth":
				return true;
			case "offsetHeight":
				return true;
			case "autocomplete":
				return true;
			case "autofocus":
				return true;
			case "disabled":
				return true;
			case "form":
				return $this->getForm() !== null;
			case "multiple":
				return true;
			case "name":
				return true;
			case "required":
				return true;
			case "size":
				return true;
			case "type":
				return true;
			case "options":
				return true;
			case "length":
				return true;
			case "selectedOptions":
				return true;
			case "selectedIndex":
				return true;
			case "value":
				return true;
			case "willValidate":
				return true;
			case "validity":
				return true;
			case "validationMessage":
				return true;
			case "labels":
				return true;
			default:
				break;
		}
		return false;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set( string $name, $value ): void {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		switch ( $name ) {
			case "nodeValue":
				$this->setNodeValue( $value );
				return;
			case "textContent":
				$this->setTextContent( $value );
				return;
			case "innerHTML":
				$this->setInnerHTML( $value );
				return;
			case "id":
				$this->setId( $value );
				return;
			case "className":
				$this->setClassName( $value );
				return;
			case "classList":
				$this->setClassList( $value );
				return;
			case "slot":
				$this->setSlot( $value );
				return;
			case "outerHTML":
				$this->setOuterHTML( $value );
				return;
			case "style":
				$this->setStyle( $value );
				return;
			case "contentEditable":
				$this->setContentEditable( $value );
				return;
			case "enterKeyHint":
				$this->setEnterKeyHint( $value );
				return;
			case "inputMode":
				$this->setInputMode( $value );
				return;
			case "onload":
				$this->setOnload( $value );
				return;
			case "nonce":
				$this->setNonce( $value );
				return;
			case "tabIndex":
				$this->setTabIndex( $value );
				return;
			case "title":
				$this->setTitle( $value );
				return;
			case "lang":
				$this->setLang( $value );
				return;
			case "translate":
				$this->setTranslate( $value );
				return;
			case "dir":
				$this->setDir( $value );
				return;
			case "hidden":
				$this->setHidden( $value );
				return;
			case "accessKey":
				$this->setAccessKey( $value );
				return;
			case "draggable":
				$this->setDraggable( $value );
				return;
			case "spellcheck":
				$this->setSpellcheck( $value );
				return;
			case "autocapitalize":
				$this->setAutocapitalize( $value );
				return;
			case "innerText":
				$this->setInnerText( $value );
				return;
			case "autocomplete":
				$this->setAutocomplete( $value );
				return;
			case "autofocus":
				$this->setAutofocus( $value );
				return;
			case "disabled":
				$this->setDisabled( $value );
				return;
			case "multiple":
				$this->setMultiple( $value );
				return;
			case "name":
				$this->setName( $value );
				return;
			case "required":
				$this->setRequired( $value );
				return;
			case "size":
				$this->setSize( $value );
				return;
			case "length":
				$this->setLength( $value );
				return;
			case "selectedIndex":
				$this->setSelectedIndex( $value );
				return;
			case "value":
				$this->setValue( $value );
				return;
			default:
				break;
		}
		'@phan-var \Wikimedia\IDLeDOM\Helper\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\Helper\HTMLSelectElement $this
		$this->_setMissingProp( $name, $value );
	}

	/**
	 * @param string $name
	 */
	public function __unset( string $name ): void {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		switch ( $name ) {
			case "nodeType":
				break;
			case "nodeName":
				break;
			case "baseURI":
				break;
			case "isConnected":
				break;
			case "ownerDocument":
				break;
			case "parentNode":
				break;
			case "parentElement":
				break;
			case "childNodes":
				break;
			case "firstChild":
				break;
			case "lastChild":
				break;
			case "previousSibling":
				break;
			case "nextSibling":
				break;
			case "nodeValue":
				$this->setNodeValue( null );
				return;
			case "textContent":
				$this->setTextContent( null );
				return;
			case "innerHTML":
				break;
			case "previousElementSibling":
				break;
			case "nextElementSibling":
				break;
			case "children":
				break;
			case "firstElementChild":
				break;
			case "lastElementChild":
				break;
			case "childElementCount":
				break;
			case "assignedSlot":
				break;
			case "namespaceURI":
				break;
			case "prefix":
				break;
			case "localName":
				break;
			case "tagName":
				break;
			case "id":
				break;
			case "className":
				break;
			case "classList":
				break;
			case "slot":
				break;
			case "attributes":
				break;
			case "shadowRoot":
				break;
			case "outerHTML":
				break;
			case "style":
				break;
			case "contentEditable":
				break;
			case "enterKeyHint":
				break;
			case "isContentEditable":
				break;
			case "inputMode":
				break;
			case "onload":
				break;
			case "dataset":
				break;
			case "nonce":
				break;
			case "tabIndex":
				break;
			case "title":
				break;
			case "lang":
				break;
			case "translate":
				break;
			case "dir":
				break;
			case "hidden":
				break;
			case "accessKey":
				break;
			case "accessKeyLabel":
				break;
			case "draggable":
				break;
			case "spellcheck":
				break;
			case "autocapitalize":
				break;
			case "innerText":
				break;
			case "offsetParent":
				break;
			case "offsetTop":
				break;
			case "offsetLeft":
				break;
			case "offsetWidth":
				break;
			case "offsetHeight":
				break;
			case "autocomplete":
				break;
			case "autofocus":
				break;
			case "disabled":
				break;
			case "form":
				break;
			case "multiple":
				break;
			case "name":
				break;
			case "required":
				break;
			case "size":
				break;
			case "type":
				break;
			case "options":
				break;
			case "length":
				break;
			case "selectedOptions":
				break;
			case "selectedIndex":
				break;
			case "value":
				break;
			case "willValidate":
				break;
			case "validity":
				break;
			case "validationMessage":
				break;
			case "labels":
				break;
			default:
				return;
		}
		$trace = debug_backtrace();
		while (
			count( $trace ) > 0 &&
			$trace[0]['function'] !== "__unset"
		) {
			array_shift( $trace );
		}
		trigger_error(
			'Undefined property' .
			' via ' . ( $trace[0]['function'] ?? '' ) . '(): ' . $name .
			' in ' . ( $trace[0]['file'] ?? '' ) .
			' on line ' . ( $trace[0]['line'] ?? '' ),
			E_USER_NOTICE
		);
	}

	/**
	 * @return string
	 */
	public function getAutocomplete(): string {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		$val = $this->getAttribute( 'autocomplete' );
		if ( $val !== null ) {
			$val = strtr( $val, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' );
			switch ( $val ) {
				case 'on':
				case 'off':
					return $val;
				default:
					return 'on';
			}
		}
		return 'on';
	}

	/**
	 * @param string $val
	 */
	public function setAutocomplete( string $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		$this->setAttribute( 'autocomplete', $val );
	}

	/**
	 * @return bool
	 */
	public function getAutofocus(): bool {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		return $this->hasAttribute( 'autofocus' );
	}

	/**
	 * @param bool $val
	 */
	public function setAutofocus( bool $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		if ( $val ) {
			$this->setAttribute( 'autofocus', '' );
		} else {
			$this->removeAttribute( 'autofocus' );
		}
	}

	/**
	 * @return bool
	 */
	public function getDisabled(): bool {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		return $this->hasAttribute( 'disabled' );
	}

	/**
	 * @param bool $val
	 */
	public function setDisabled( bool $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		if ( $val ) {
			$this->setAttribute( 'disabled', '' );
		} else {
			$this->removeAttribute( 'disabled' );
		}
	}

	/**
	 * @return bool
	 */
	public function getMultiple(): bool {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		return $this->hasAttribute( 'multiple' );
	}

	/**
	 * @param bool $val
	 */
	public function setMultiple( bool $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		if ( $val ) {
			$this->setAttribute( 'multiple', '' );
		} else {
			$this->removeAttribute( 'multiple' );
		}
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		return $this->getAttribute( 'name' ) ?? '';
	}

	/**
	 * @param string $val
	 */
	public function setName( string $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		$this->setAttribute( 'name', $val );
	}

	/**
	 * @return bool
	 */
	public function getRequired(): bool {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		return $this->hasAttribute( 'required' );
	}

	/**
	 * @param bool $val
	 */
	public function setRequired( bool $val ): void {
		'@phan-var \Wikimedia\IDLeDOM\Element $this';
		// @var \Wikimedia\IDLeDOM\Element $this
		if ( $val ) {
			$this->setAttribute( 'required', '' );
		} else {
			$this->removeAttribute( 'required' );
		}
	}

	/**
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		return $this->offsetGet( $offset ) !== null;
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		if ( is_numeric( $offset ) ) {
			return $this->item( $offset );
		} elseif ( is_string( $offset ) ) {
			/* Fall through */
		}
		$trace = debug_backtrace();
		while (
			count( $trace ) > 0 &&
			$trace[0]['function'] !== "offsetGet" &&
			$trace[0]['function'] !== "offsetExists"
		) {
			array_shift( $trace );
		}
		while (
			count( $trace ) > 1 && (
			$trace[1]['function'] === "offsetGet" ||
			$trace[1]['function'] === "offsetExists"
		) ) {
			array_shift( $trace );
		}
		trigger_error(
			'Undefined property' .
			' via ' . ( $trace[0]['function'] ?? '' ) . '(): ' . $offset .
			' in ' . ( $trace[0]['file'] ?? '' ) .
			' on line ' . ( $trace[0]['line'] ?? '' ),
			E_USER_NOTICE
		);
		return null;
	}

	/**
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet( $offset, $value ): void {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		if ( is_numeric( $offset ) ) {
			$this->setItem( $offset, $value );
		} elseif ( is_string( $offset ) ) {
			/* Fall through */
		}
		$trace = debug_backtrace();
		while (
			count( $trace ) > 0 &&
			$trace[0]['function'] !== "offsetSet"
		) {
			array_shift( $trace );
		}
		trigger_error(
			'Undefined property' .
			' via ' . ( $trace[0]['function'] ?? '' ) . '(): ' . $offset .
			' in ' . ( $trace[0]['file'] ?? '' ) .
			' on line ' . ( $trace[0]['line'] ?? '' ),
			E_USER_NOTICE
		);
	}

	/**
	 * @param mixed $offset
	 */
	public function offsetUnset( $offset ): void {
		'@phan-var \Wikimedia\IDLeDOM\HTMLSelectElement $this';
		// @var \Wikimedia\IDLeDOM\HTMLSelectElement $this
		if ( is_numeric( $offset ) ) {
			/* Fall through */
		} elseif ( is_string( $offset ) ) {
			/* Fall through */
		}
		$trace = debug_backtrace();
		while (
			count( $trace ) > 0 &&
			$trace[0]['function'] !== "offsetUnset"
		) {
			array_shift( $trace );
		}
		trigger_error(
			'Undefined property' .
			' via ' . ( $trace[0]['function'] ?? '' ) . '(): ' . $offset .
			' in ' . ( $trace[0]['file'] ?? '' ) .
			' on line ' . ( $trace[0]['line'] ?? '' ),
			E_USER_NOTICE
		);
	}

}

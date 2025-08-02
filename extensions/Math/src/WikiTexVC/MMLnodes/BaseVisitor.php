<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

abstract class BaseVisitor {
	/**
	 * Visit an MMLbase node and process it
	 * @param MMLbase $node Node to visit
	 */
	abstract public function visit( MMLbase $node );
}

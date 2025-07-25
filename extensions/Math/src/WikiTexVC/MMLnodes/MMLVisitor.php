<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

interface MMLVisitor {
	/**
	 * Visit an MMLbase node and process it
	 * @param MMLbase $node Node to visit
	 */
	public function visit( MMLbase $node );
}

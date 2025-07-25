<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLnodes;

class VisitorFactory {
	public function createVisitor(): MMLDomVisitor {
		return new MMLDomVisitor();
	}
}

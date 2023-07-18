<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

class Fun2nb extends Fun2 {

	public function inCurlies() {
		return '{' . $this->render() . '}';
	}

	public function render() {
		return $this->fname . ' ' . $this->arg1->inCurlies() . $this->arg2->inCurlies();
	}
}

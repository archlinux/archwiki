<?php

namespace Wikimedia\WikiPEG;

interface Tracer {
	public function trace( array $event ): void;
}

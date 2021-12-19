<?php

namespace Wikimedia\RemexHtml\Serializer;

class SerializerError extends \Exception {
}

// Retain the old namespace for backwards compatibility.
class_alias( SerializerError::class, 'RemexHtml\Serializer\SerializerError' );

<?php

namespace MediaWiki\SyntaxHighlight\Tests;

use Psr\Http\Client\ClientExceptionInterface;

class TestPSRClientException extends \Exception implements ClientExceptionInterface {

}

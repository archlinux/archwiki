<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations;

use RuntimeException;

/**
 * Exception thrown when an operation is attempted on a case ID that does not exist.
 */
class CaseNotFoundException extends RuntimeException {
}

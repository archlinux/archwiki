<?php

namespace MediaWiki\Extension\Nuke;

use DateTime;
use Exception;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use Wikimedia\IPUtils;

/**
 * Groups all Nuke-related filters and request data into a single object.
 * This reduces the work involved in keeping track of every single filter
 * that gets added into Nuke by keeping it all in a central place.
 */
class NukeContext {

	/**
	 * The active "action" for the special page. This determines which stage in the Nuke
	 * form we're in. This is one of the `ACTION_*` constants on {@link SpecialNuke}:
	 *  - {@link SpecialNuke::ACTION_PROMPT}
	 *  - {@link SpecialNuke::ACTION_LIST}
	 *  - {@link SpecialNuke::ACTION_CONFIRM}
	 *  - {@link SpecialNuke::ACTION_DELETE}
	 *
	 * @var string
	 */
	private string $action = SpecialNuke::ACTION_PROMPT;

	/**
	 * The target actor. Can be a username (normal or temporary account) or
	 * IP address. When not provided, this is an empty string.
	 *
	 * @var string
	 */
	private string $target = '';

	/**
	 * The listed target actor. Used when the action is `delete` or `confirm`, replacing
	 * {@link $target} to ensure that the target from which the pages belong is what's shown
	 * instead of what's on the input box at request time (T380297). When not provided, this
	 * is an empty string. When this is an empty string, it implies the value of $target
	 * should be used.
	 *
	 * @var string
	 */
	private string $listedTarget = '';

	/**
	 * The title matching pattern. As of 1.44, this is an SQL LIKE pattern, which uses
	 * `%` as the wildcard character. When not provided, this is an empty string.
	 *
	 * @var string
	 */
	private string $pattern = '';

	/**
	 * An array of namespace IDs where the query will run. When not provided, this is `null`.
	 * When `null`, this implicitly means all namespaces should be included.
	 *
	 * @var int[]|null
	 */
	private ?array $namespaces = null;

	/**
	 * The maximum number of pages to get. This limit also applies after hooks run; the
	 * final list of pages must never be larger than this value.
	 *
	 * @var int
	 */
	private int $limit = 500;

	/**
	 * The date from which the Nuke search should be performed. Only page creations after this
	 * value should be returned. When not provided, this is an empty string.
	 *
	 * @var string
	 */
	private string $dateFrom = '';

	/**
	 * The date to which the Nuke search should be performed. Only page creations before this
	 * value should be returned. When not provided, this is an empty string.
	 *
	 * @var string
	 */
	private string $dateTo = '';

	/**
	 * Whether to include talk pages in the search. When not provided, this is `false`.
	 *
	 * @var bool
	 */
	private bool $includeTalkPages = false;

	/**
	 * Whether to include redirects in the search. When not provided, this is `false`.
	 *
	 * @var bool
	 */
	private bool $includeRedirects = false;

	/**
	 * The list of pages to delete. Only applicable for the `confirm` and `delete` actions.
	 * When not provided, this is an empty array.
	 *
	 * @var string[]
	 */
	private array $pages = [];

	/**
	 * The list of pages associated with the target to delete. Only applicable for the `confirm`
	 * and `delete` actions. When not provided, this is an empty array.
	 *
	 * @var string[]
	 */
	private array $associatedPages = [];

	/**
	 * The original list of pages provided to the user. When on the `confirm` and `delete`
	 * actions, this is required to show pages that were deselected by the user during the
	 * `list` action, allowing users to follow up on found but deselected pages. When not
	 * provided, this is an empty array.
	 *
	 * @var string[]
	 */
	private array $originalPages = [];

	/**
	 * Whether support for temporary accounts is enabled.
	 *
	 * @var bool
	 */
	private bool $useTemporaryAccounts = false;

	/**
	 * The current status of the user's access to Nuke.
	 *
	 * @var int
	 */
	private int $nukeAccessStatus = self::NUKE_ACCESS_INTERNAL_ERROR;

	/**
	 * Constants for nuke access status.
	 */
	public const NUKE_ACCESS_INTERNAL_ERROR = 0;
	public const NUKE_ACCESS_GRANTED = 1;
	public const NUKE_ACCESS_NO_PERMISSION = 2;
	public const NUKE_ACCESS_BLOCKED = 3;

	/**
	 * The minimum size of pages to list, in bytes. This is used to limit the size of the
	 * pages shown to the user. When not provided, this is by default 0 (no limit).
	 *
	 * @var int
	 */
	private int $minPageSize = 0;

	/**
	 * The maximum size of pages to list, in bytes. This is used to limit the size of the
	 * pages shown to the user. When not provided, this is by default null.
	 *
	 * Negatives are treated as no-ops, so this is what we default to.
	 *
	 * @var int
	 */
	private int $maxPageSize = -1;

	/**
	 * Originating request context of the query.
	 *
	 * @var IContextSource
	 */
	private IContextSource $requestContext;

	/**
	 * Create a new NukeContext without transforming most parameters.
	 *
	 * @param array $params
	 */
	public function __construct( array $params ) {
		$this->requestContext = $params['requestContext'];
		$this->useTemporaryAccounts = $params['useTemporaryAccounts'] ?? $this->useTemporaryAccounts;

		$this->action = $params['action'] ?? $this->action;
		$this->target = $params['target'] ?? $this->target;
		$this->listedTarget = $params['listedTarget'] ?? $this->listedTarget;
		$this->pattern = $params['pattern'] ?? $this->pattern;
		$this->namespaces = $params['namespaces'] ?? $this->namespaces;
		$this->limit = $params['limit'] ?? $this->limit;

		if ( isset( $params['dateFrom'] ) && $params['dateFrom'] ) {
			$this->dateFrom = $params['dateFrom'];
		}
		if ( isset( $params['dateTo'] ) && $params['dateTo'] ) {
			$this->dateTo = $params['dateTo'];
		}

		$this->includeTalkPages = $params['includeTalkPages'] ?? $this->includeTalkPages;
		$this->includeRedirects = $params['includeRedirects'] ?? $this->includeRedirects;

		$this->pages = $params['pages'] ?? $this->pages;
		$this->associatedPages = $params['associatedPages'] ?? $this->associatedPages;

		if ( $this->action == 'delete' || $this->action == 'confirm' ) {
			if ( $params['listedTarget'] ) {
				$this->listedTarget = $params['listedTarget'];
			}
			$this->originalPages = $params['originalPages'] ?? $this->originalPages;
		}

		$this->nukeAccessStatus = $params['nukeAccessStatus'] ?? $this->nukeAccessStatus;

		$this->minPageSize = $params['minPageSize'];
		$this->maxPageSize = $params['maxPageSize'];
	}

	/**
	 * Returns {@link $action}.
	 * @return string
	 */
	public function getAction(): string {
		return $this->action;
	}

	/**
	 * Returns the target of the request: {@link $target} if {@link $action} is "delete" or
	 * "confirm", {@link listedTarget} otherwise.
	 *
	 * @param string|null $action The action to use. Uses {@link $action} by default.
	 * @return string
	 */
	public function getTarget( ?string $action = null ): string {
		$action ??= $this->action;

		if ( $action == 'delete' || $action == 'confirm' ) {
			if ( $this->listedTarget ) {
				// "target" might be different, if the user typed in a different name before
				// hitting "Continue". We still want to show the pages from the user currently
				// shown on the form.
				return $this->listedTarget;
			} else {
				// No provided target. This may be an incomplete request or a test.
				// Fall back to using $target.
				return $this->target;
			}
		} else {
			return $this->target;
		}
	}

	/**
	 * Returns {@link $pattern}.
	 * @return string
	 */
	public function getPattern(): string {
		return $this->pattern;
	}

	/**
	 * Returns {@link $namespaces}.
	 * @return int[]|null
	 */
	public function getNamespaces(): ?array {
		return $this->namespaces;
	}

	/**
	 * Returns {@link $limit}.
	 * @return int
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * Returns {@link $dateFrom} in DateTime format. The value of `$dateFrom` should first be
	 * validated with {@link validateDate}.
	 *
	 * FIXME: Doc should be changed to throw DateMalformedStringException in PHP 8.3+.
	 *
	 * @return DateTime|null
	 * @throws Exception
	 */
	public function getDateFrom(): ?DateTime {
		if ( !$this->dateFrom ) {
			return null;
		}
		return new DateTime( "{$this->dateFrom}T00:00:00Z" );
	}

	/**
	 * Returns {@link $dateTo} in DateTime format.The value of `$dateFrom` should first be
	 *  validated with {@link validateDate}.
	 *
	 * FIXME: Doc should be changed to throw DateMalformedStringException in PHP 8.3+.
	 *
	 * @return DateTime|null
	 * @throws Exception
	 */
	public function getDateTo(): ?DateTime {
		if ( !$this->dateTo ) {
			return null;
		}
		return new DateTime( "{$this->dateTo}T00:00:00Z" );
	}

	/**
	 * Returns {@link $includeRedirects}.
	 * @return bool
	 */
	public function getIncludeRedirects(): bool {
		return $this->includeRedirects;
	}

	/**
	 * Returns {@link $includeTalkPages}.
	 * @return bool
	 */
	public function getIncludeTalkPages(): bool {
		return $this->includeTalkPages;
	}

	/**
	 * Returns a merger of {@link $pages} and {@link $associatedPages}.
	 * @return string[]
	 */
	public function getAllPages(): array {
		return array_merge( $this->getPages(), $this->getAssociatedPages() );
	}

	/**
	 * Returns {@link $minPageSize}.
	 *
	 * @return int
	 */
	public function getMinPageSize(): int {
		return $this->minPageSize;
	}

	/**
	 * Returns {@link $maxPageSize}.
	 *
	 * @return int
	 */
	public function getMaxPageSize(): int {
		return $this->maxPageSize;
	}

	/**
	 * Returns {@link $pages}.
	 * @return string[]
	 */
	public function getPages(): array {
		return $this->pages;
	}

	/**
	 * Returns {@link $associatedPages}.
	 * @return string[]
	 */
	public function getAssociatedPages(): array {
		return $this->associatedPages;
	}

	/**
	 * Returns {@link $originalPages}.
	 * @return string[]
	 */
	public function getOriginalPages(): array {
		return $this->originalPages;
	}

	/**
	 * Returns {@link $nukeAccessStatus}.
	 * @return int
	 */
	public function getNukeAccessStatus(): int {
		return $this->nukeAccessStatus;
	}

	/**
	 * Returns {@link requestContext}.
	 * @return IContextSource
	 */
	public function getRequestContext(): IContextSource {
		return $this->requestContext;
	}

	/**
	 * Returns {@link $useTemporaryAccounts}.
	 * @return bool
	 */
	public function willUseTemporaryAccounts(): bool {
		return $this->useTemporaryAccounts;
	}

	/**
	 * Returns whether a target was specified.
	 *
	 * @return bool
	 */
	public function hasTarget(): bool {
		return $this->target != '';
	}

	/**
	 * Returns whether this request has pages selected.
	 *
	 * @return bool
	 */
	public function hasPages(): bool {
		return count( $this->pages ) > 0;
	}

	/**
	 * Returns whether this request has pages shown to the user.
	 *
	 * @return bool
	 */
	public function hasOriginalPages(): bool {
		return count( $this->originalPages ) > 0;
	}

	/**
	 * Validate values for all stages of Nuke. Includes filter validation, and validation prior to
	 * running any "confirm"/"delete" stages. Determines what error messages should be shown to
	 * the user. Returns `true` on success, a string value containing the error for failures.
	 *
	 * @return string|true
	 */
	public function validate() {
		$promptValidation = $this->validatePrompt();
		if ( $promptValidation !== true ) {
			return $promptValidation;
		}

		if (
			(
				// This is a confirm/delete
				$this->action == SpecialNuke::ACTION_CONFIRM ||
				$this->action == SpecialNuke::ACTION_DELETE
			) &&
			// No pages were selected or provided.
			!$this->hasPages()
		) {
			if ( !$this->hasOriginalPages() ) {
				// No page list was requested. This is an early confirm attempt without having
				// listed the pages at all. Show the list form again.
				return $this->requestContext->msg( 'nuke-nolist' )->text();
			} else {
				// Pages were not requested but a page list exists. The user did not select any
				// pages. Show the list form again.
				return $this->requestContext->msg( 'nuke-noselected' )->text();
			}
		}

		return true;
	}

	/**
	 * Validate values for the "list" or "prompt" stages of Nuke. Determines what error
	 * messages should be shown to the user. Returns `true` on success, a string value containing
	 * the error for failures.
	 *
	 * Any error returned by this function should be something that blocks the search process.
	 *
	 * @return string|true
	 */
	public function validatePrompt() {
		$fromValidationResult = $this->validateDate( $this->dateFrom );
		if ( $fromValidationResult !== true ) {
			return $fromValidationResult;
		}

		$toValidationResult = $this->validateDate( $this->dateTo );
		if ( $toValidationResult !== true ) {
			return $toValidationResult;
		}

		return true;
	}

	/**
	 * Validate a date-related filter. Checks if the date is before the Nuke max age.
	 *
	 * @param string $value The value to validate
	 * @return string|true
	 */
	protected function validateDate( string $value ) {
		if ( $value == '' ) {
			// No value is valid.
			return true;
		}

		$now = ( new DateTime() )
			->setTime( 0, 0 )
			->getTimestamp();
		$maxAge = $this->getNukeMaxAge();

		try {
			$timestamp = ( new DateTime( $value . "T00:00:00Z" ) )
				->getTimestamp();
			if ( $timestamp < $now - $maxAge ) {
				return $this->requestContext->msg(
					'nuke-date-limited',
					$this->requestContext->getLanguage()->formatTimePeriod( $maxAge, [
						'avoid' => 'avoidhours',
						'noabbrevs' => true
					] )
				)->text();
			}
		} catch ( \Exception $e ) {
			// FIXME: This should be changed to use DateMalformedStringException when MediaWiki
			// begins using PHP 8.3 as a minimum.
			return $this->requestContext->msg( 'htmlform-date-invalid' )->text();
		}
		return true;
	}

	/**
	 * Get the user-provided deletion reason, or a default deletion reason if one wasn't
	 * provided.
	 *
	 * @return string
	 */
	public function getDeleteReason(): string {
		$context = $this->requestContext;
		$target = $this->target;
		$request = $context->getRequest();

		if ( $this->useTemporaryAccounts && IPUtils::isValid( $target ) ) {
			$defaultReason = $context->msg( 'nuke-defaultreason-tempaccount' )
				->inContentLanguage()
				->text();
		} else {
			$defaultReason = $target === ''
				? $context->msg( 'nuke-multiplepeople' )->inContentLanguage()->text()
				: $context->msg( 'nuke-defaultreason', $target )->inContentLanguage()->text();
		}

		$dropdownSelection = $request->getText( 'wpDeleteReasonList', 'other' );
		$reasonInput = $request->getText( 'wpReason', $defaultReason );

		if ( $dropdownSelection === 'other' ) {
			return $reasonInput;
		} elseif ( $reasonInput !== '' ) {
			// Entry from drop down menu + additional comment
			$separator = $context->msg( 'colon-separator' )->inContentLanguage()->text();
			return $dropdownSelection . $separator . $reasonInput;
		} else {
			return $dropdownSelection;
		}
	}

	/**
	 * Get the maximum age in seconds that a page can be before it cannot be deleted by Nuke.
	 *
	 * @param bool $useRCMaxAge Whether to use `$wgRCMaxAge` as a fallback.
	 * @return int
	 */
	public function getNukeMaxAge( bool $useRCMaxAge = true ): int {
		$maxAge = $this->requestContext->getConfig()->get( NukeConfigNames::MaxAge );
		// If no Nuke-specific max age was set, this should match the value of `$wgRCMaxAge`.
		if ( !$maxAge && $useRCMaxAge ) {
			$maxAge = $this->requestContext->getConfig()->get( MainConfigNames::RCMaxAge );
		}
		return $maxAge;
	}

	/**
	 * Get the maximum age in days that a page can be before it cannot be deleted by Nuke when a username is provided.
	 *
	 * @return float
	 */
	public function getNukeMaxAgeInDays(): float {
		$secondsInADay = 86400;
		return round( $this->requestContext->getConfig()->get( NukeConfigNames::MaxAge ) / $secondsInADay );
	}

	/**
	 * Get the maximum age in days that a page can be before it cannot be deleted by Nuke when no username is provided.
	 *
	 * @return float
	 */
	public function getRecentChangesMaxAgeInDays(): float {
		$secondsInADay = 86400;
		return round( $this->requestContext->getConfig()->get( MainConfigNames::RCMaxAge ) / $secondsInADay );
	}

	/**
	 * Calculate any search notices that need to be displayed with the results.
	 * This is based on the search parameters.
	 *
	 * @return string[] Array of i18n strings to display as a search notice
	 */
	public function calculateSearchNotices(): array {
		$notices = [];

		// first check if any values are being ignored
		$ignoringValues = false;

		if ( $this->maxPageSize < 0 ) {
			// if the maximum is negative, it's invalid
			// it is allowed to have it be 0,
			// because a 0-byte page can exist
			// the QueryBuilder code will ignore negative values
			$notices[] = "nuke-searchnotice-negmax";
			$ignoringValues = true;
		}
		if ( $this->minPageSize < 0 ) {
			// if the minimum is negative, then it's not really a minimum
			// tell the user the QueryBuilder code will ignore it
			// this is last because we can still return results if the minimum is negative
			$notices[] = "nuke-searchnotice-negmin";
			$ignoringValues = true;
		}

		// if we're not ignoring either, check for incompatibility
		if ( !$ignoringValues ) {
			if ( $this->minPageSize > $this->maxPageSize ) {
				// if the maximum is less than the minimum then
				// there's no way any results can be returned
				$notices[] = "nuke-searchnotice-minmorethanmax";
			}
		}

		return $notices;
	}

}

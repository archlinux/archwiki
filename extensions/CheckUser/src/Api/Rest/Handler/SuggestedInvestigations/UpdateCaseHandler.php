<?php

namespace MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\CaseNotFoundException;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Formatters\StatusReasonFormatter;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Language\Language;
use MediaWiki\Rest\Handler\Helper\RestAuthorizeTrait;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use RuntimeException;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\NumericDef;

/**
 * @internal Only for use by the Special:SuggestedInvestigations special page. Not intended for wider use
 */
class UpdateCaseHandler extends SimpleHandler {

	use TokenAwareHandlerTrait;
	use RestAuthorizeTrait;

	public function __construct(
		private readonly Config $config,
		private readonly Language $contentLanguage,
		private readonly SuggestedInvestigationsCaseManagerService $caseManager,
		private readonly StatusReasonFormatter $statusReasonFormatter,
	) {
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ): void {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	/**
	 * @throws LocalizedHttpException
	 * @throws HttpException
	 */
	public function run(): Response {
		// We cannot easily conditionally enable REST API routes (unlike Special pages), so we should instead
		// return a 404. If it becomes possible to conditionally enable REST API routes based on config, that
		// method should be used instead.
		if ( !$this->config->get( 'CheckUserSuggestedInvestigationsEnabled' ) ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'checkuser-suggestedinvestigations-case-update-feature-not-enabled' ),
				404
			);
		}

		$this->authorizeActionOrThrow( $this->getAuthority(), 'checkuser' );

		$caseId = $this->getValidatedParams()['caseId'];

		// Fetch the properties to be updated from the request body, which should be a valid array (because the
		// request body was validated before calling ::run)
		$body = $this->getValidatedBody() ?? [];
		$status = $body['status'];

		// We truncate the reason to fit within the column, though on the front-end we enforce a limit to avoid
		// unexpected truncation.
		$reason = $this->contentLanguage->truncateForDatabase( trim( $body['reason'] ), 255 );

		$newStatus = match ( $status ) {
			'open' => CaseStatus::Open,
			'invalid' => CaseStatus::Invalid,
			'resolved' => CaseStatus::Resolved,
			// We don't need a user-friendly message here because the definition in ::getBodyParamSettings
			// should have rejected any value than the ones we handled above. This is a sanity check.
			default => throw new RuntimeException( "Unhandled status $status" ),
		};

		$performer = $this->getAuthority()->getUser();

		try {
			$this->caseManager->setCaseStatus( $caseId, $newStatus, $reason, $performer->getId() );
		} catch ( CaseNotFoundException ) {
			throw new LocalizedHttpException(
				MessageValue::new( 'checkuser-suggestedinvestigations-case-update-case-not-found' ),
				400
			);
		}

		$formattedReason = $this->statusReasonFormatter->format(
			$reason,
			$newStatus,
			$performer->getName(),
			RequestContext::getMain()
		);

		return $this->getResponseFactory()->createJson( [
			'caseId' => $caseId,
			'status' => $status,
			'reason' => $reason,
			'formattedReason' => $formattedReason,
		] );
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition() + [
			'status' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => [ 'open', 'resolved', 'invalid' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'reason' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
			],
		];
	}

	/** @inheritDoc */
	public function getParamSettings(): array {
		return [
			'caseId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				NumericDef::PARAM_MIN => 1,
			],
		];
	}
}

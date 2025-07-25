<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Config\Config;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentityValue;
use TypeError;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Handler for POST requests to /checkuser/v0/useragent-clienthints/{type}/{id}
 *
 * Intended to be called by the ext.checkUser.clientHints ResourceLoader module,
 * in response to 'postEdit' mw.hook events.
 *
 * Eventually we can also use this endpoint for mapping data to CheckUser log events
 * in cu_log_event and cu_private_event.
 */
class UserAgentClientHintsHandler extends SimpleHandler {
	use TokenAwareHandlerTrait;

	private Config $config;
	private RevisionStore $revisionStore;
	private UserAgentClientHintsManager $userAgentClientHintsManager;
	private IConnectionProvider $dbProvider;
	private ActorStore $actorStore;

	public function __construct(
		Config $config,
		RevisionStore $revisionStore,
		UserAgentClientHintsManager $userAgentClientHintsManager,
		IConnectionProvider $dbProvider,
		ActorStore $actorStore
	) {
		$this->config = $config;
		$this->revisionStore = $revisionStore;
		$this->userAgentClientHintsManager = $userAgentClientHintsManager;
		$this->dbProvider = $dbProvider;
		$this->actorStore = $actorStore;
	}

	/**
	 * @inheritDoc
	 */
	public function validate( Validator $restValidator ): void {
		parent::validate( $restValidator );
		// Allow anonymous token needs to be true as logged out users can make requests to
		// this endpoint via the ext.checkUser.clientHints ResourceLoader module.
		$this->validateToken( true );
	}

	/** @inheritDoc */
	public function run() {
		if ( !$this->config->get( 'CheckUserClientHintsEnabled' ) ) {
			// Pretend the route doesn't exist if the feature flag is off.
			throw new LocalizedHttpException(
				new MessageValue( 'rest-no-match' ), 404
			);
		}
		$data = $this->getValidatedBody();
		// $data should be an array, but can be null when validation
		// failed and/or when the content type was form data.
		if ( !is_array( $data ) ) {
			// Taken from Validator::validateBody
			[ $contentType ] = explode( ';', $this->getRequest()->getHeaderLine( 'Content-Type' ), 2 );
			$contentType = strtolower( trim( $contentType ) );
			if ( $contentType !== 'application/json' ) {
				// Same exception as used in UnsupportedContentTypeBodyValidator
				throw new LocalizedHttpException(
					new MessageValue( 'rest-unsupported-content-type', [ $contentType ] ),
					415
				);
			} else {
				// Should be caught by JsonBodyValidator::validateBody, but if this
				// point is reached a non-array still indicates a problem with the
				// data submitted by the client and thus a 400 error is appropriate.
				throw new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 );
			}
		}
		try {
			// ::newFromJsApi with the $data may raise a TypeError as the $data
			// does not have its type validated (T305973).
			$clientHints = ClientHintsData::newFromJsApi( $data );
		} catch ( TypeError $e ) {
			throw new LocalizedHttpException( new MessageValue( 'rest-bad-json-body' ), 400 );
		}
		$type = $this->getValidatedParams()['type'];
		$identifier = $this->getValidatedParams()['id'];
		if ( $type === 'revision' ) {
			$this->performValidationForRevision( $identifier );
		} elseif ( $type === 'privatelog' ) {
			$this->performValidationForPrivateLog( $identifier );
		} else {
			// If the type is not supported, pretend the route doesn't exist.
			throw new LocalizedHttpException(
				new MessageValue( 'rest-no-match' ), 404
			);
		}
		$status = $this->userAgentClientHintsManager->insertClientHintValues( $clientHints, $identifier, $type );
		if ( !$status->isGood() ) {
			$error = $status->getErrors()[0];
			// A client hints mapping entry already exists.
			throw new LocalizedHttpException(
				new MessageValue( $error['message'], $error['params'][0] ),
				400
			);
		}

		return $this->getResponseFactory()->createJson( [
			'value' => $this->getResponseFactory()->formatMessage(
				new MessageValue( 'checkuser-api-useragent-clienthints-explanation' )
			)
		] );
	}

	/**
	 * Check whether Client Hints data can be stored for the given revision ID.
	 * This method checks that the revision with this ID exists, was not made
	 * over wgCheckUserClientHintsRestApiMaxTimeLag seconds ago, and that the
	 * user making the request made the edit with this ID.
	 *
	 * @param int $revisionId The revision ID
	 * @return void
	 * @throws LocalizedHttpException If the checks fail, this exception will be raised.
	 */
	private function performValidationForRevision( int $revisionId ) {
		// Check the revision exists.
		$revision = $this->revisionStore->getRevisionById( $revisionId );
		if ( !$revision ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-revision', [ $revisionId ] ), 404 );
		}
		$this->performTimestampValidation( $revision->getTimestamp(), 'revision', $revisionId );
		// Check the performer of the action is the same as the user submitting this REST API request
		$user = $this->getAuthority()->getUser();
		if (
			!$revision->getUser( RevisionRecord::RAW ) ||
			!$revision->getUser( RevisionRecord::RAW )->equals( $user )
		) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-revision-user-mismatch',
					[ $user->getId(), $revisionId ]
				),
				401
			);
		}
	}

	/**
	 * Check whether Client Hints data can be stored for the private log event ID.
	 * This method checks that the revision with this private log event ID exists,
	 * was not made over wgCheckUserClientHintsRestApiMaxTimeLag seconds ago, and
	 * that the user making the request performed the private log.
	 *
	 * @param int $privateLogId The private log ID
	 * @return void
	 * @throws LocalizedHttpException If the checks fail, this exception will be raised.
	 */
	private function performValidationForPrivateLog( int $privateLogId ) {
		// Fetch details about the private event with ID $privateLogId
		$dbr = $this->dbProvider->getReplicaDatabase();
		$privateEventRow = $dbr->newSelectQueryBuilder()
			->select( [ 'cupe_timestamp', 'cupe_actor', 'cupe_ip' ] )
			->from( 'cu_private_event' )
			->where( [ 'cupe_id' => $privateLogId ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $privateEventRow === false ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-nonexistent-id', [ 'privatelog', $privateLogId ]
				),
				404
			);
		}
		$this->performTimestampValidation( $privateEventRow->cupe_timestamp, 'privatelog', $privateLogId );
		// Check the performer of the action is the same as the user submitting this REST API request
		if ( $privateEventRow->cupe_actor === null && $privateEventRow->cupe_ip ) {
			// Use the IP as the user_text if the actor ID is NULL and the IP is not NULL (T353953).
			$performingUser = new UserIdentityValue( 0, $privateEventRow->cupe_ip );
		} else {
			$performingUser = $this->actorStore->getActorById( $privateEventRow->cupe_actor, $dbr );
		}
		$user = $this->getAuthority()->getUser();
		if ( !$performingUser->equals( $user ) ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-revision-user-mismatch',
					[ $user->getId(), $privateLogId ]
				),
				401
			);
		}
	}

	/**
	 * Validate that the API was not called over wgCheckUserClientHintsRestApiMaxTimeLag
	 * seconds ago.
	 *
	 * @param ?string $associatedEntryTimestamp The timestamp associated with the $identifier in TS_MW form.
	 *   If null, the validation will always fail.
	 * @param string $type The type of the $identifier (e.g. revision)
	 * @param int $identifier The ID of the entry of type $type
	 * @return void
	 * @throws LocalizedHttpException If the validation fails, this exception will be raised.
	 */
	private function performTimestampValidation(
		?string $associatedEntryTimestamp, string $type, int $identifier
	): void {
		// Check that the API was not called too long after the edit
		$cutoff = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - $this->config->get( 'CheckUserClientHintsRestApiMaxTimeLag' )
		);
		// If there is no timestamp associated with this action, then
		// this method cannot perform the timestamp check. This should
		// rarely happen and is likely to occur for actions that are
		// already too old, so just don't store data in this case.
		if ( $associatedEntryTimestamp < $cutoff ) {
			throw new LocalizedHttpException(
				new MessageValue(
					'checkuser-api-useragent-clienthints-called-too-late',
					[ $type, $identifier ]
				),
				403
			);
		}
	}

	/** @inheritDoc */
	public function needsWriteAccess() {
		return true;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => UserAgentClientHintsManager::SUPPORTED_TYPES,
				ParamValidator::PARAM_REQUIRED => true,
			],
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'brands' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array'
			],
			'mobile' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'platform' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
			],
			'architecture' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string'
			],
			'bitness' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string'
			],
			'fullVersionList' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array'
			],
			'model' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
			],
			'platformVersion' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
			],
			// While this is deprecated and not requested by the JS code, some clients still send this value so we
			// need to define it as an acceptable parameter (T350316) to prevent the valid request from otherwise
			// failing.
			'uaFullVersion' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
			],
		] + $this->getTokenParamDefinition();
	}
}

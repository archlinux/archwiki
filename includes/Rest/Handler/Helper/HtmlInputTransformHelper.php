<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
namespace MediaWiki\Rest\Handler\Helper;

use Content;
use InvalidArgumentException;
use Language;
use LanguageCode;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\Edit\ParsoidOutputStash;
use MediaWiki\Edit\SelserContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\Parsoid\HtmlToContentTransform;
use MediaWiki\Parser\Parsoid\HtmlTransformFactory;
use MediaWiki\Parser\Parsoid\PageBundleParserOutputConverter;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Parser\Parsoid\ParsoidRenderID;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MWUnknownContentModelException;
use ParserOptions;
use ParserOutput;
use Status;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\Parsoid;

/**
 * REST helper for converting HTML to page content source (e.g. wikitext).
 *
 * @since 1.40
 *
 * @unstable Pending consolidation of the Parsoid extension with core code.
 */
class HtmlInputTransformHelper {
	/**
	 * @internal
	 * @var string[]
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ParsoidCacheConfig
	];

	/** @var HtmlTransformFactory */
	private $htmlTransformFactory;

	/** @var PageIdentity|null */
	private $page = null;

	/** @var StatsdDataFactoryInterface */
	private $stats;

	/** @var array|null */
	private $parameters = null;

	/**
	 * @var HtmlToContentTransform
	 */
	private $transform;

	/**
	 * @var ParsoidOutputStash
	 */
	private $parsoidOutputStash;

	/**
	 * @var ParsoidOutputAccess
	 */
	private $parsoidOutputAccess;

	/**
	 * @var array
	 */
	private $envOptions;

	/**
	 * @param StatsdDataFactoryInterface $statsDataFactory
	 * @param HtmlTransformFactory $htmlTransformFactory
	 * @param ParsoidOutputStash $parsoidOutputStash
	 * @param ParsoidOutputAccess $parsoidOutputAccess
	 * @param array $envOptions
	 */
	public function __construct(
		StatsdDataFactoryInterface $statsDataFactory,
		HtmlTransformFactory $htmlTransformFactory,
		ParsoidOutputStash $parsoidOutputStash,
		ParsoidOutputAccess $parsoidOutputAccess,
		array $envOptions = []
	) {
		$this->stats = $statsDataFactory;
		$this->htmlTransformFactory = $htmlTransformFactory;
		$this->parsoidOutputStash = $parsoidOutputStash;
		$this->envOptions = $envOptions + [
			'outputContentVersion' => Parsoid::defaultHTMLVersion(),
			'offsetType' => 'byte',
		];
		$this->parsoidOutputAccess = $parsoidOutputAccess;
	}

	/**
	 * @return array
	 */
	public function getParamSettings(): array {
		// JSON body schema:
		/*
		doc:
			properties:
				headers:
					type: array
					items:
						type: string
				body:
					type: [ string, object ]
			required: [ body ]

		body:
			properties:
				offsetType:
					type: string
				revid:
					type: integer
				renderid:
					type: string
				etag:
					type: string
				html:
					type: [ doc, string ]
				data-mw:
					type: doc
				original:
					properties:
						html:
							type: doc
						source:
							type: doc
						data-mw:
							type: doc
						data-parsoid:
							type: doc
			required: [ html ]
		 */

		// FUTURE: more params
		// - slot (for loading the base content)

		return [
			// XXX: should we really declare this here? Or should end endpoint do this?
			//      We are not reading this property...
			'title' => [
				Handler::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => false,
			],
			// XXX: Needed for compatibility with the parsoid transform endpoint.
			//      But revid should just be part of the info about the original data
			//      in the body.
			'oldid' => [
				Handler::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'int',
				ParamValidator::PARAM_DEFAULT => 0,
				ParamValidator::PARAM_REQUIRED => false,
			],
			// XXX: Supported for compatibility with the parsoid transform endpoint.
			//      If given, it should be 'html' or 'pagebundle'.
			'from' => [
				Handler::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => false,
			],
			// XXX: Supported for compatibility with the parsoid transform endpoint.
			//      Ignored.
			'format' => [
				Handler::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'contentmodel' => [ // XXX: get this from the Accept header?
				Handler::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'language' => [ // TODO: get this from Accept-Language header?!
				Handler::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_REQUIRED => false,
			]
		];
	}

	/**
	 * Modify body and parameters to provide compatibility with legacy endpoints.
	 *
	 * @see ParsoidHandler::getRequestAttributes
	 *
	 * @param array<string,mixed> &$body
	 * @param array<string,mixed> &$parameters
	 *
	 * @throws HttpException
	 *
	 * @return void
	 */
	private static function normalizeParameters( array &$body, array &$parameters ) {
		// If the revision ID is given in the path, pretend it was given in the body.
		if ( isset( $parameters['oldid'] ) && (int)$parameters['oldid'] > 0 ) {
			$body['original']['revid'] = (int)$parameters['oldid'];
		}

		// If an etag is given in the body, use it as the render ID.
		// Note that we support ETag format in the renderid field.
		if ( !empty( $body['original']['etag'] ) ) {
			// @phan-suppress-next-line PhanTypeInvalidDimOffset false positive
			$body['original']['renderid'] = $body['original']['etag'];
		}

		// Accept 'wikitext' as an alias for 'source'.
		if ( isset( $body['original']['wikitext'] ) ) {
			// @phan-suppress-next-line PhanTypeInvalidDimOffset false positive
			$body['original']['source'] = $body['original']['wikitext'];
			unset( $body['original']['wikitext'] );
		}

		// If 'from' is not set, we accept page bundle style input as well as full HTML.
		// If 'from' is set, we only accept page bundle style input if it is set to FORMAT_PAGEBUNDLE.
		if (
			isset( $parameters['from'] ) && $parameters['from'] !== '' &&
			$parameters['from'] !== ParsoidFormatHelper::FORMAT_PAGEBUNDLE
		) {
			unset( $body['original']['data-parsoid']['body'] );
			unset( $body['original']['data-mw']['body'] );
			unset( $body['data-mw']['body'] );
		}

		// If 'from' is given, it must be html or pagebundle.
		if (
			isset( $parameters['from'] ) && $parameters['from'] !== '' &&
			$parameters['from'] !== ParsoidFormatHelper::FORMAT_HTML &&
			$parameters['from'] !== ParsoidFormatHelper::FORMAT_PAGEBUNDLE
		) {
			throw new HttpException( 'Unsupported input: ' . $parameters['from'], 400 );
		}

		if ( isset( $body['contentmodel'] ) && $body['contentmodel'] !== '' ) {
			$parameters['contentmodel'] = $body['contentmodel'];
		} elseif ( isset( $parameters['format'] ) && $parameters['format'] !== '' ) {
			$parameters['contentmodel'] = $parameters['format'];
		}
	}

	/**
	 * @param PageIdentity $page
	 * @param array|string $body Body structure, or an HTML string
	 * @param array $parameters
	 * @param RevisionRecord|null $originalRevision
	 * @param Language|null $pageLanguage
	 *
	 * @throws HttpException
	 */
	public function init(
		PageIdentity $page,
		$body,
		array $parameters,
		?RevisionRecord $originalRevision = null,
		?Language $pageLanguage = null
	) {
		if ( is_string( $body ) ) {
			$body = [ 'html' => $body ];
		}

		self::normalizeParameters( $body, $parameters );

		$this->page = $page;
		$this->parameters = $parameters;

		if ( !isset( $body['html'] ) ) {
			throw new HttpException( 'Expected `html` key in body' );
		}

		$html = is_array( $body['html'] ) ? $body['html']['body'] : $body['html'];

		// TODO: validate $body against a proper schema.
		$this->transform = $this->htmlTransformFactory->getHtmlToContentTransform(
			$html,
			$this->page
		);

		$this->transform->setMetrics( $this->stats );

		// NOTE: Env::getContentModel will fall back to the page's recorded content model
		//       if none is set here.
		$this->transform->setOptions( [
			'contentmodel' => $this->parameters['contentmodel'] ?? null,
			'offsetType' => $body['offsetType'] ?? $this->envOptions['offsetType'],
		] );

		$original = $body['original'] ?? [];
		$originalRendering = null;

		if ( !isset( $original['html'] ) && !empty( $original['renderid'] ) ) {
			$key = $original['renderid'];
			if ( preg_match( '!^(W/)?".*"$!', $key ) ) {
				$originalRendering = ParsoidRenderID::newFromETag( $key );

				if ( !$originalRendering ) {
					throw new HttpException( "Bad ETag: $key", 400 );
				}
			} else {
				$originalRendering = ParsoidRenderID::newFromKey( $key );
			}
		} elseif ( !empty( $original['html'] ) || !empty( $original['data-parsoid'] ) ) {
			// NOTE: We might have an incomplete PageBundle here, with no HTML but with data-parsoid!
			// XXX: Do we need to support that, or can that just be a 400?
			$originalRendering = new PageBundle(
				$original['html']['body'] ?? '',
				$original['data-parsoid']['body'] ?? null,
				$original['data-mw']['body'] ?? null,
				null, // will be derived from $original['html']['headers']['content-type']
				$original['html']['headers'] ?? []
			);
		}

		if ( !$originalRevision && !empty( $original['revid'] ) ) {
			$originalRevision = (int)$original['revid'];
		}

		if ( $originalRevision || $originalRendering ) {
			$this->setOriginal( $originalRevision, $originalRendering );
		} else {
			if ( $this->page->exists() ) {
				$this->stats->increment( 'html_input_transform.original_html.not_given.page_exists' );
			} else {
				$this->stats->increment( 'html_input_transform.original_html.not_given.page_not_exist' );
			}
		}

		if ( isset( $body['data-mw']['body'] ) ) {
			$this->transform->setModifiedDataMW( $body['data-mw']['body'] );
		}

		if ( $pageLanguage ) {
			$this->transform->setContentLanguage( $pageLanguage );
		} elseif ( isset( $parameters['language'] ) && $parameters['language'] !== '' ) {
			$pageLanguage = LanguageCode::normalizeNonstandardCodeAndWarn(
				$parameters['language']
			);
			$this->transform->setContentLanguage( $pageLanguage );
		}

		if ( isset( $original['source']['body'] ) ) {
			// XXX: do we really have to support wikitext overrides?
			$this->transform->setOriginalText( $original['source']['body'] );
		}
	}

	/**
	 * Return HTMLTransform object, so additional context can be provided by calling setters on it.
	 * @return HtmlToContentTransform
	 */
	public function getTransform(): HtmlToContentTransform {
		return $this->transform;
	}

	/**
	 * Set metrics sink.
	 *
	 * @param StatsdDataFactoryInterface $stats
	 */
	public function setMetrics( StatsdDataFactoryInterface $stats ) {
		$this->stats = $stats;

		if ( $this->transform ) {
			$this->transform->setMetrics( $stats );
		}
	}

	/**
	 * Supply information about the revision and rendering that was the original basis of
	 * the input HTML. This is used to apply selective serialization (selser), if possible.
	 *
	 * @param RevisionRecord|int|null $rev
	 * @param ParsoidRenderID|PageBundle|ParserOutput|null $originalRendering
	 */
	public function setOriginal( $rev, $originalRendering ) {
		if ( $originalRendering instanceof ParsoidRenderID ) {
			$renderId = $originalRendering;

			// If the client asked for a render ID, load original data from stash
			try {
				$selserContext = $this->fetchSelserContextFromStash( $renderId );
			} catch ( InvalidArgumentException $ex ) {
				$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.bad' );
				throw new HttpException(
					'Bad stash key',
					400,
					[
						'reason' => $ex->getMessage(),
						'key' => "$renderId"
					]
				);
			}

			if ( !$selserContext ) {
				// NOTE: When the client asked for a specific stash key (resp. etag),
				//       we should fail with a 412 if we don't have the specific rendering.
				//       On the other hand, of the client only provided a base revision ID,
				//       we can re-parse and hope for the best.

				throw new HttpException(
					'No stashed content found for ' . $renderId, 412
				);

				// TODO: This class should provide getETag and getLastModified methods for use by
				//       the REST endpoint, to provide proper support for conditionals.
				//       However, that requires some refactoring of how HTTP conditional checks
				//       work in the Handler base class.
			}

			if ( !$rev ) {
				$rev = $renderId->getRevisionID();
			}

			$originalRendering = $selserContext->getPageBundle();
			$content = $selserContext->getContent();

			if ( $content ) {
				$this->transform->setOriginalContent( $content );
			}
		} elseif ( !$originalRendering && $rev ) {
			// The client provided a revision ID, but not stash key.
			// Try to get a rendering for the given revision, and use it as the basis for selser.
			// Chances are good that the resulting diff will be reasonably clean.
			// NOTE: If we don't have a revision ID, we should not attempt selser!
			$originalRendering = $this->fetchParserOutputFromParsoid( $rev, true );

			if ( $originalRendering ) {
				$this->stats->increment( 'html_input_transform.original_html.given.as_revid.found' );
			} else {
				$this->stats->increment( 'html_input_transform.original_html.given.as_revid.not_found' );
			}
		} elseif ( $originalRendering ) {
			$this->stats->increment( 'html_input_transform.original_html.given.verbatim' );
		}

		if ( $originalRendering instanceof ParserOutput ) {
			$originalRendering = PageBundleParserOutputConverter::pageBundleFromParserOutput( $originalRendering );

			// NOTE: Use the default if we got a ParserOutput object.
			//       Don't apply the default if we got passed a PageBundle,
			//       in that case, we want to require the version to be explicit.
			if ( $originalRendering->version === null && !isset( $originalRendering->headers['content-type'] ) ) {
				$originalRendering->version = Parsoid::defaultHTMLVersion();
			}
		}

		if ( !$originalRendering instanceof PageBundle ) {
			return;
		}

		if ( $originalRendering->version !== null ) {
			$this->transform->setOriginalSchemaVersion( $originalRendering->version );
		} elseif ( !empty( $originalRendering->headers['content-type'] ) ) {
			$vOriginal = ParsoidFormatHelper::parseContentTypeHeader(
				// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Silly Phan, we just checked.
				$originalRendering->headers['content-type']
			);

			if ( $vOriginal ) {
				$this->transform->setOriginalSchemaVersion( $vOriginal );
			}
		}

		if ( $rev instanceof RevisionRecord ) {
			$this->transform->setOriginalRevision( $rev );
		} elseif ( $rev && is_int( $rev ) ) {
			$this->transform->setOriginalRevisionId( $rev );
		}

		// NOTE: We might have an incomplete PageBundle here, with no HTML.
		//       PageBundle::$html is declared to not be nullable, so it would be set to the empty
		//       string if not given. Note however that it might also be null, since it's a public field.
		if ( $originalRendering->html !== null && $originalRendering->html !== '' ) {
			$this->transform->setOriginalHtml( $originalRendering->html );
		}

		if ( $originalRendering->parsoid !== null ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Silly Phan, we just checked.
			$this->transform->setOriginalDataParsoid( $originalRendering->parsoid );
		}

		if ( $originalRendering->mw !== null ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable Silly Phan, we just checked.
			$this->transform->setOriginalDataMW( $originalRendering->mw );
		}
	}

	/**
	 * @return Content the content derived from the input HTML.
	 * @throws HttpException
	 */
	public function getContent(): Content {
		try {
			return $this->transform->htmlToContent();
		} catch ( ClientError $e ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => $e->getMessage() ]
			);
		} catch ( ResourceLimitExceededException $e ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-resource-limit-exceeded' ),
				413,
				[ 'reason' => $e->getMessage() ]
			);
		} catch ( MWUnknownContentModelException $e ) {
			throw new HttpException( $e->getMessage(), 400 );
		}
	}

	/**
	 * Creates a response containing the content derived from the input HTML.
	 * This will set the appropriate Content-Type header.
	 *
	 * @param ResponseInterface $response
	 */
	public function putContent( ResponseInterface $response ) {
		$content = $this->getContent();
		$data = $content->serialize();

		try {
			$contentType = ParsoidFormatHelper::getContentType(
				$content->getModel(),
				$this->envOptions['outputContentVersion']
			);
		} catch ( InvalidArgumentException $e ) {
			// If Parsoid doesn't know the content type,
			// ask the ContentHandler!
			$contentType = $content->getDefaultFormat();
		}

		$response->setHeader( 'Content-Type', $contentType );
		$response->getBody()->write( $data );
	}

	/**
	 * @param RevisionRecord|int $rev
	 * @param bool $mayParse
	 *
	 * @return ParserOutput|null
	 * @throws HttpException
	 */
	private function fetchParserOutputFromParsoid( $rev, bool $mayParse ): ?ParserOutput {
		$parserOptions = ParserOptions::newFromAnon();

		try {
			if ( $mayParse ) {
				$status = $this->parsoidOutputAccess->getParserOutput(
					$this->page,
					$parserOptions,
					$rev
				);

				if ( !$status->isOK() ) {
					$this->throwHttpExceptionForStatus( $status );
				}

				$parserOutput = $status->getValue();
			} else {
				$parserOutput = $this->parsoidOutputAccess->getCachedParserOutput(
					$this->page,
					$parserOptions,
					$rev
				);
			}
		} catch ( RevisionAccessException $e ) {
			// The client supplied bad revision ID, or the revision was deleted or suppressed.
			throw new HttpException(
				'The specified revision does not exist.',
				404,
				[ 'reason' => $e->getMessage() ]
			);
		}

		return $parserOutput;
	}

	/**
	 * @param ParsoidRenderID $renderID
	 *
	 * @return SelserContext|null
	 */
	private function fetchSelserContextFromStash( $renderID ): ?SelserContext {
		$selserContext = $this->parsoidOutputStash->get( $renderID );

		if ( $selserContext ) {
			$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.' .
				'stash_hit.found.hit' );

			return $selserContext;
		} else {
			// Looks like the rendering is gone from stash (or the client send us a bogus key).
			// Try to load it from the parser cache instead.
			// On a wiki with low edit frequency, there is a good chance that it's still there.
			try {
				$parserOutput = $this->fetchParserOutputFromParsoid( $renderID->getRevisionID(), false );

				if ( !$parserOutput ) {
					$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.' .
						'stash_miss_pc_fallback.not_found.miss' );
					return null;
				}

				$cachedRenderID = $this->parsoidOutputAccess->getParsoidRenderID( $parserOutput );
				if ( $cachedRenderID->getKey() !== $renderID->getKey() ) {
					$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.' .
						'stash_miss_pc_fallback.not_found.mismatch' );

					// It's not the correct rendering.
					return null;
				}

				$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.' .
					'stash_miss_pc_fallback.found.hit' );

				$pb = PageBundleParserOutputConverter::pageBundleFromParserOutput( $parserOutput );
				return new SelserContext( $pb, $renderID->getRevisionID() );
			} catch ( HttpException $e ) {
				$this->stats->increment( 'html_input_transform.original_html.given.as_renderid.' .
					'stash_miss_pc_fallback.not_found.failed' );

				// If the revision isn't found, don't trigger a 404. Return null to trigger a 412.
				return null;
			}
		}
	}

	/**
	 * @param Status $status
	 *
	 * @return never
	 * @throws HttpException
	 */
	private function throwHttpExceptionForStatus( Status $status ) {
		// TODO: make this nicer.
		if ( $status->hasMessage( 'parsoid-resource-limit-exceeded' ) ) {
			throw new HttpException(
				'Resource limit exceeeded',
				413,
				[ 'reason' => $status->getHTML() ]
			);
		} else {
			throw new HttpException(
				'Parsoid error',
				400,
				[ 'reason' => $status->getHTML() ]
			);
		}
	}

}

/** @deprecated since 1.40, remove in 1.41 */
class_alias( HtmlInputTransformHelper::class, "MediaWiki\\Rest\\Handler\\HtmlInputTransformHelper" );

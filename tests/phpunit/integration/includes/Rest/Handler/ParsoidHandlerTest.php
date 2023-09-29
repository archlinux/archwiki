<?php

namespace MediaWiki\Tests\Rest\Handler;

use Composer\Semver\Semver;
use Exception;
use Generator;
use JavaScriptContent;
use Language;
use LanguageCode;
use MediaWiki\MainConfigNames;
use MediaWiki\MainConfigSchema;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\ParserCacheFactory;
use MediaWiki\Parser\Parsoid\Config\PageConfigFactory;
use MediaWiki\Parser\Parsoid\HtmlToContentTransform;
use MediaWiki\Parser\Parsoid\HtmlTransformFactory;
use MediaWiki\Parser\RevisionOutputCache;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Rest\Handler\Helper\HtmlInputTransformHelper;
use MediaWiki\Rest\Handler\Helper\ParsoidFormatHelper;
use MediaWiki\Rest\Handler\ParsoidHandler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseFactory;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Tests\Rest\RestTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use NullStatsdDataFactory;
use ParserCache;
use PHPUnit\Framework\MockObject\MockObject;
use TitleValue;
use Wikimedia\Bcp47Code\Bcp47Code;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageValue;
use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\Mocks\MockMetrics;
use Wikimedia\Parsoid\Parsoid;
use WikitextContent;

/**
 * @group Database
 * @covers \MediaWiki\Rest\Handler\ParsoidHandler
 * @covers \MediaWiki\Parser\Parsoid\HtmlToContentTransform
 */
class ParsoidHandlerTest extends MediaWikiIntegrationTestCase {
	use RestTestTrait;

	/**
	 * Default request attributes, see ParsoidHandler::getRequestAttributes()
	 */
	private const DEFAULT_ATTRIBS = [
		'titleMissing' => false,
		'pageName' => '',
		'oldid' => null,
		'body_only' => null,
		'errorEnc' => 'plain',
		'iwp' => 'exwiki',
		'subst' => null,
		'offsetType' => 'byte',
		'opts' => [],
		'envOptions' => [
			'prefix' => 'exwiki',
			'domain' => 'wiki.example.com',
			'pageName' => '',
			'offsetType' => 'byte',
			'cookie' => '',
			'reqId' => 'test+test+test',
			'userAgent' => 'UTAgent',
			'htmlVariantLanguage' => null,
			'outputContentVersion' => Parsoid::AVAILABLE_VERSIONS[0],
		],
	];

	/** @var string Imperfect wikitext to be preserved if selser is applied. Corresponds to Selser.html. */
	private const IMPERFECT_WIKITEXT = "<div  >Turaco</DIV>";

	/** @var string Normalized version of IMPERFECT_WIKITEXT, expected when no selser is applied. */
	private const NORMALIZED_WIKITEXT = "<div>Turaco</div>";

	public function setUp(): void {
		// enable Pig Latin variant conversion
		$this->overrideConfigValues( [
			'UsePigLatinVariant' => true,
			'ParsoidSettings' => [
				'useSelser' => true,
				'linting' => true,
			]
		] );
	}

	private function createRouter( $authority, $request ) {
		return $this->newRouter( [
			'authority' => $authority,
			'request' => $request,
		] );
	}

	private function newParsoidHandler( $methodOverrides = [], $serviceOverrides = [] ): ParsoidHandler {
		$parsoidSettings = [];
		$method = 'POST';

		$parsoidSettings += MainConfigSchema::getDefaultValue( MainConfigNames::ParsoidSettings );

		$dataAccess = $serviceOverrides['ParsoidDataAccess'] ?? $this->getServiceContainer()->getParsoidDataAccess();
		$siteConfig = $serviceOverrides['ParsoidSiteConfig'] ?? $this->getServiceContainer()->getParsoidSiteConfig();
		$pageConfigFactory = $serviceOverrides['ParsoidPageConfigFactory']
			?? $this->getServiceContainer()->getParsoidPageConfigFactory();

		$handler = new class (
			$this,
			$parsoidSettings,
			$siteConfig,
			$pageConfigFactory,
			$dataAccess,
			$methodOverrides
		) extends ParsoidHandler {
			private $testCase;
			private $overrides;

			public function __construct(
				$testCase,
				array $parsoidSettings,
				SiteConfig $siteConfig,
				PageConfigFactory $pageConfigFactory,
				DataAccess $dataAccess,
				array $overrides
			) {
				parent::__construct(
					$parsoidSettings,
					$siteConfig,
					$pageConfigFactory,
					$dataAccess
				);

				$this->testCase = $testCase;
				$this->overrides = $overrides;
			}

			protected function parseHTML( string $html, bool $validateXMLNames = false ): Document {
				if ( isset( $this->overrides['parseHTML'] ) ) {
					return $this->overrides['parseHTML']( $html, $validateXMLNames );
				}

				return parent::parseHTML(
					$html,
					$validateXMLNames
				);
			}

			protected function newParsoid(): Parsoid {
				if ( isset( $this->overrides['newParsoid'] ) ) {
					return $this->overrides['newParsoid']();
				}

				return parent::newParsoid();
			}

			protected function getHtmlInputTransformHelper(
				array $attribs,
				string $html,
				$page
			): HtmlInputTransformHelper {
				if ( isset( $this->overrides['getHtmlInputHelper'] ) ) {
					return $this->overrides['getHtmlInputHelper']();
				}

				return parent::getHtmlInputTransformHelper(
					$attribs,
					$html,
					$page
				);
			}

			public function execute(): Response {
				ParsoidHandlerTest::fail( 'execute was not expected to be called' );
			}

			public function &getRequestAttributes(): array {
				if ( isset( $this->overrides['getRequestAttributes'] ) ) {
					return $this->overrides['getRequestAttributes']();
				}

				return parent::getRequestAttributes();
			}

			public function acceptable( array &$attribs ): bool {
				if ( isset( $this->overrides['acceptable'] ) ) {
					return $this->overrides['acceptable']( $attribs );
				}

				return parent::acceptable( $attribs );
			}

			public function createPageConfig(
				string $title,
				?int $revision,
				?string $wikitextOverride = null,
				?Bcp47Code $pagelanguageOverride = null
			): PageConfig {
				if ( isset( $this->overrides['createPageConfig'] ) ) {
					return $this->overrides['createPageConfig'](
						$title, $revision, $wikitextOverride, $pagelanguageOverride
					);
				}

				return parent::createPageConfig(
					$title,
					$revision,
					$wikitextOverride,
					$pagelanguageOverride
				);
			}

			public function tryToCreatePageConfig(
				array $attribs, ?string $wikitext = null, bool $html2WtMode = false
			): PageConfig {
				if ( isset( $this->overrides['tryToCreatePageConfig'] ) ) {
					return $this->overrides['tryToCreatePageConfig'](
						$attribs, $wikitext, $html2WtMode
					);
				}
				$attribs += [
					'pagelanguage' => $this->testCase->createLanguageMock( 'en' ),
				];

				return parent::tryToCreatePageConfig(
					$attribs, $wikitext, $html2WtMode
				);
			}

			public function createRedirectResponse(
				string $path,
				array $pathParams = [],
				array $queryParams = []
			): Response {
				return parent::createRedirectResponse(
					$path,
					$pathParams,
					$queryParams
				);
			}

			public function createRedirectToOldidResponse(
				PageConfig $pageConfig,
				array $attribs
			): Response {
				return parent::createRedirectToOldidResponse(
					$pageConfig,
					$attribs
				);
			}

			public function wt2html(
				PageConfig $pageConfigConfig,
				array $attribs,
				?string $wikitext = null
			) {
				return parent::wt2html(
					$pageConfigConfig,
					$attribs,
					$wikitext
				);
			}

			public function html2wt( $page, array $attribs, string $html ) {
				return parent::html2wt(
					$page,
					$attribs,
					$html
				);
			}

			public function pb2pb( array $attribs ) {
				return parent::pb2pb( $attribs );
			}

			public function updateRedLinks(
				PageConfig $pageConfig,
				array $attribs,
				array $revision
			) {
				return parent::updateRedLinks(
					$pageConfig,
					$attribs,
					$revision
				);
			}

			public function languageConversion(
				PageConfig $pageConfig,
				array $attribs,
				array $revision
			) {
				return parent::languageConversion(
					$pageConfig,
					$attribs,
					$revision
				);
			}
		};

		$authority = new UltimateAuthority( new UserIdentityValue( 0, '127.0.0.1' ) );
		$request = new RequestData( [ 'method' => $method ] );
		$router = $this->createRouter( $authority, $request );
		$config = [];

		$formatter = new class implements ITextFormatter {
			public function getLangCode() {
				return 'qqx';
			}

			public function format( MessageValue $message ) {
				return $message->dump();
			}
		};

		/** @var ResponseFactory|MockObject $responseFactory */
		$responseFactory = new ResponseFactory( [ 'qqx' => $formatter ] );

		$handler->init(
			$router,
			$request,
			$config,
			$authority,
			$responseFactory,
			$this->createHookContainer(),
			$this->getSession( true )
		);

		return $handler;
	}

	/**
	 * @param PageIdentity $page
	 * @param int|string|RevisionRecord|null $revIdOrText
	 *
	 * @return PageConfig
	 * @throws \MWException
	 */
	private function getPageConfig( PageIdentity $page, $revIdOrText = null ): PageConfig {
		$rev = null;
		if ( is_string( $revIdOrText ) ) {
			$rev = new MutableRevisionRecord( $page );
			$rev->setContent( SlotRecord::MAIN, new WikitextContent( $revIdOrText ) );
		} else {
			// may be null or an int or a RevisionRecord
			$rev = $revIdOrText;
		}

		return $this->getServiceContainer()->getParsoidPageConfigFactory()->create( $page, null, $rev );
	}

	private function getPageConfigFactory( PageIdentity $page ): PageConfigFactory {
		/** @var PageConfigFactory|MockObject $pageConfigFactory */
		$pageConfigFactory = $this->createNoOpMock( PageConfigFactory::class, [ 'create' ] );
		$pageConfigFactory->method( 'create' )->willReturn( $this->getPageConfig( $page ) );
		return $pageConfigFactory;
	}

	private function getTextFromFile( string $name ): string {
		return trim( file_get_contents( __DIR__ . "/data/Transform/$name" ) );
	}

	private function getJsonFromFile( string $name ): array {
		$text = $this->getTextFromFile( $name );
		return json_decode( $text, JSON_OBJECT_AS_ARRAY );
	}

	// Mostly lifted from the contentTypeMatcher in tests/api-testing/REST/Transform.js
	private function contentTypeMatcher( string $expected, string $actual ): bool {
		if ( $expected === 'application/json' ) {
			return $actual === $expected;
		}

		$pattern = '/^([-\w]+\/[-\w]+); charset=utf-8; profile="https:\/\/www.mediawiki.org\/wiki\/Specs\/([-\w]+)\/(\d+\.\d+\.\d+)"$/';

		preg_match( $pattern, $expected, $expectedParts );
		if ( !$expectedParts ) {
			return false;
		}
		[ , $expectedMime, $expectedSpec, $expectedVersion ] = $expectedParts;

		preg_match( $pattern, $actual, $actualParts );
		if ( !$actualParts ) {
			return false;
		}
		[ , $actualMime, $actualSpec, $actualVersion ] = $actualParts;

		// Match version using caret semantics
		if ( !Semver::satisfies( $actualVersion, "^{$expectedVersion}" ) ) {
			return false;
		}

		if ( $actualMime !== $expectedMime || $actualSpec !== $expectedSpec ) {
			return false;
		}

		return true;
	}

	public function provideHtml2wt() {
		$profileVersion = '2.6.0';
		$wikitextProfileUri = 'https://www.mediawiki.org/wiki/Specs/wikitext/1.0.0';
		$htmlProfileUri = 'https://www.mediawiki.org/wiki/Specs/HTML/' . $profileVersion;
		$dataParsoidProfileUri = 'https://www.mediawiki.org/wiki/Specs/data-parsoid/' . $profileVersion;

		$wikiTextContentType = "text/plain; charset=utf-8; profile=\"$wikitextProfileUri\"";
		$htmlContentType = "text/html;profile=\"$htmlProfileUri\"";
		$dataParsoidContentType = "application/json;profile=\"$dataParsoidProfileUri\"";

		$htmlHeaders = [
			'content-type' => $htmlContentType,
		];

		// NOTE: profile version 999 is a placeholder for a future feature, see T78676
		$htmlContentType999 = 'text/html;profile="https://www.mediawiki.org/wiki/Specs/HTML/999.0.0"';
		$htmlHeaders999 = [
			'content-type' => $htmlContentType999,
		];

		// should convert html to wikitext ///////////////////////////////////
		$html = $this->getTextFromFile( 'MainPage-data-parsoid.html' );
		$expectedText = [
			'MediaWiki has been successfully installed',
			'== Getting started ==',
		];

		$attribs = [];
		yield 'should convert html to wikitext' => [
			$attribs,
			$html,
			$expectedText,
		];

		// should load original wikitext by revision id ////////////////////
		$attribs = [
			'oldid' => 1, // will be replaced by the actual revid
		];
		yield 'should load original wikitext by revision id' => [
			$attribs,
			$html,
			$expectedText,
		];

		// should accept original wikitext in body ////////////////////
		$originalWikitext = $this->getTextFromFile( 'OriginalMainPage.wikitext' );
		$attribs = [
			'opts' => [
				'original' => [
					'wikitext' => [
						'headers' => [
							'content-type' => $wikiTextContentType,
						],
						'body' => $originalWikitext,
					]
				]
			],
		];
		yield 'should accept original wikitext in body' => [
			$attribs,
			$html,
			$expectedText, // TODO: ensure it's actually used!
		];

		// should use original html for selser (default) //////////////////////
		$originalDataParsoid = $this->getJsonFromFile( 'MainPage-original.data-parsoid' );
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $this->getTextFromFile( 'MainPage-original.html' ),
					],
					'data-parsoid' => [
						'headers' => [
							'content-type' => $dataParsoidContentType,
						],
						'body' => $originalDataParsoid
					]
				]
			],
		];
		yield 'should use original html for selser (default)' => [
			$attribs,
			$html,
			$expectedText,
		];

		// should use original html for selser (1.1.1, meta) ///////////////////
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => [
							// XXX: If this is required anyway, how do we know we are using the
							//      version given in the HTML?
							'content-type' => 'text/html; profile="mediawiki.org/specs/html/1.1.1"',
						],
						'body' => $this->getTextFromFile( 'MainPage-data-parsoid-1.1.1.html' ),
					],
					'data-parsoid' => [
						'headers' => [
							'content-type' => $dataParsoidContentType,
						],
						'body' => $originalDataParsoid
					]
				]
			],
		];
		yield 'should use original html for selser (1.1.1, meta)' => [
			$attribs,
			$html,
			$expectedText,
		];

		// should accept original html for selser (1.1.1, headers) ////////////
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => [
							// Set the schema version to 1.1.1!
							'content-type' => 'text/html; profile="mediawiki.org/specs/html/1.1.1"',
						],
						// No schema version in HTML
						'body' => $this->getTextFromFile( 'MainPage-original.html' ),
					],
					'data-parsoid' => [
						'headers' => [
							'content-type' => $dataParsoidContentType,
						],
						'body' => $originalDataParsoid
					]
				]
			],
		];
		yield 'should use original html for selser (1.1.1, headers)' => [
			$attribs,
			$html,
			$expectedText,
		];

		// Return original wikitext when HTML doesn't change ////////////////////////////
		// New and old html are identical, which should produce no diffs
		// and reuse the original wikitext.
		$html = $this->getTextFromFile( 'Selser.html' );

		// Original wikitext (to be preserved by selser)
		$originalWikitext = self::IMPERFECT_WIKITEXT;

		// Normalized wikitext (when no selser is applied)
		$normalizedWikitext = self::NORMALIZED_WIKITEXT;

		$dataParsoid = [ // Per Selser.html
			'ids' => [
				'mwAA' => [ 'dsr' => [ 0, 19, 0, 0 ] ],
				'mwAg' => [ 'stx' => 'html', 'dsr' => [ 0, 19, 7, 6 ] ],
				'mwAQ' => []
			]
		];

		$attribs = [
			'oldid' => 1, // Will be replaced by the revision ID of the default test page
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						// original HTML is the same as the new HTML
						'body' => $html
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					]
				]
			],
		];

		yield 'selser should return original wikitext if the HTML didn\'t change (original HTML given)' => [
			$attribs,
			$html,
			[ $originalWikitext ], // Returns original wikitext, because HTML didn't change.
		];

		unset( $attribs['opts']['original'] );
		yield 'selser should return original wikitext if the HTML didn\'t change (original HTML from ParserCache)' => [
			$attribs,
			$html,
			[ $originalWikitext ], // Returns original wikitext, because HTML didn't change.
		];

		// Should fall back to non-selective serialization. //////////////////
		// Without the original wikitext, use non-selective serialization.
		$attribs = [
			// No wikitext, no revid/oldid
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						// original HTML is the same as the new HTML
						'body' => $html
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					]
				]
			],
		];
		yield 'Should fall back to non-selective serialization' => [
			$attribs,
			$html,
			[ $normalizedWikitext ],
		];

		// should apply data-parsoid to duplicated ids /////////////////////////
		$dataParsoid = [
			'ids' => [
				'mwAA' => [],
				'mwBB' => [ 'autoInsertedEnd' => true, 'stx' => 'html' ]
			]
		];
		$html = '<html><body id="mwAA"><div id="mwBB">data-parsoid test</div>' .
			'<div id="mwBB">data-parsoid test</div></body></html>';
		$originalHtml = '<html><body id="mwAA"><div id="mwBB">data-parsoid test</div></body></html>';

		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $originalHtml
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					]
				]
			],
		];
		yield 'should apply data-parsoid to duplicated ids' => [
			$attribs,
			$html,
			[ '<div>data-parsoid test<div>data-parsoid test' ],
		];

		// should ignore data-parsoid if the input format is not pagebundle ////////////////////////
		$html = '<html><body id="mwAA"><div id="mwBB">data-parsoid test</div>' .
			'<div id="mwBB">data-parsoid test</div></body></html>';
		$originalHtml = '<html><body id="mwAA"><div id="mwBB">data-parsoid test</div></body></html>';

		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_HTML,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $originalHtml
					],
					'data-parsoid' => [
						// This has 'autoInsertedEnd' => true, which would cause
						// closing </div> tags to be omitted.
						'body' => $dataParsoid,
					]
				]
			],
		];
		yield 'should ignore data-parsoid if the input format is not pagebundle' => [
			$attribs,
			$html,
			[ '<div>data-parsoid test</div><div>data-parsoid test</div>' ],
		];

		// should apply original data-mw ///////////////////////////////////////
		$html = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">hi</p>';
		$originalHtml = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">ho</p>';
		$dataParsoid = [ 'ids' => [ 'mwAQ' => [ 'pi' => [ [ [ 'k' => '1' ] ] ] ] ] ];
		$dataMediaWiki = [
			'ids' => [
				'mwAQ' => [
					'parts' => [ [
						'template' => [
							'target' => [ 'wt' => '1x', 'href' => './Template:1x' ],
							'params' => [ '1' => [ 'wt' => 'hi' ] ],
							'i' => 0
						]
					] ]
				]
			]
		];
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $originalHtml
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [
						'body' => $dataMediaWiki,
					],
				]
			],
		];
		yield 'should apply original data-mw' => [
			$attribs,
			$html,
			[ '{{1x|hi}}' ],
		];

		// should give precedence to inline data-mw over original ////////
		$html = '<p about="#mwt1" typeof="mw:Transclusion" data-mw=\'{"parts":[{"template":{"target":{"wt":"1x","href":"./Template:1x"},"params":{"1":{"wt":"hi"}},"i":0}}]}\' id="mwAQ">hi</p>';
		$originalHtml = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">ho</p>';
		$dataParsoid = [ 'ids' => [ 'mwAQ' => [ 'pi' => [ [ [ 'k' => '1' ] ] ] ] ] ];
		$dataMediaWiki = [ 'ids' => [ 'mwAQ' => [] ] ]; // Missing data-mw.parts!
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $originalHtml
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [
						'body' => $dataMediaWiki,
					],
				]
			],
		];
		yield 'should give precedence to inline data-mw over original' => [
			$attribs,
			$html,
			[ '{{1x|hi}}' ],
		];

		// should not apply original data-mw if modified is supplied ///////////
		$html = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">hi</p>';
		$originalHtml = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">ho</p>';
		$dataParsoid = [ 'ids' => [ 'mwAQ' => [ 'pi' => [ [ [ 'k' => '1' ] ] ] ] ] ];
		$dataMediaWiki = [ 'ids' => [ 'mwAQ' => [] ] ]; // Missing data-mw.parts!
		$dataMediaWikiModified = [
			'ids' => [
				'mwAQ' => [
					'parts' => [ [
						'template' => [
							'target' => [ 'wt' => '1x', 'href' => './Template:1x' ],
							'params' => [ '1' => [ 'wt' => 'hi' ] ],
							'i' => 0
						]
					] ]
				]
			]
		];
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'data-mw' => [ // modified data
					'body' => $dataMediaWikiModified,
				],
				'original' => [
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $originalHtml
					],
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [ // original data
						'body' => $dataMediaWiki,
					],
				]
			],
		];
		yield 'should not apply original data-mw if modified is supplied' => [
			$attribs,
			$html,
			[ '{{1x|hi}}' ],
		];

		// should apply original data-mw when modified is absent (captions 1) ///////////
		$html = $this->getTextFromFile( 'Image.html' );
		$dataParsoid = [ 'ids' => [
			'mwAg' => [ 'optList' => [ [ 'ck' => 'caption', 'ak' => 'Testing 123' ] ] ],
			'mwAw' => [ 'a' => [ 'href' => './File:Foobar.jpg' ], 'sa' => [] ],
			'mwBA' => [
				'a' => [ 'resource' => './File:Foobar.jpg', 'height' => '28', 'width' => '240' ],
				'sa' => [ 'resource' => 'File:Foobar.jpg' ]
			]
		] ];
		$dataMediaWiki = [ 'ids' => [ 'mwAg' => [ 'caption' => 'Testing 123' ] ] ];

		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [ // original data
						'body' => $dataMediaWiki,
					],
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $html
					],
				]
			],
		];
		yield 'should apply original data-mw when modified is absent (captions 1)' => [
			$attribs,
			$html, // modified HTML
			[ '[[File:Foobar.jpg|Testing 123]]' ],
		];

		// should give precedence to inline data-mw over modified (captions 2) /////////////
		$htmlModified = $this->getTextFromFile( 'Image-data-mw.html' );
		$dataMediaWikiModified = [
			'ids' => [
				'mwAg' => [ 'caption' => 'Testing 123' ]
			]
		];

		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'data-mw' => [
					'body' => $dataMediaWikiModified,
				],
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [ // original data
						'body' => $dataMediaWiki,
					],
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $html
					],
				]
			],
		];
		yield 'should give precedence to inline data-mw over modified (captions 2)' => [
			$attribs,
			$htmlModified, // modified HTML
			[ '[[File:Foobar.jpg]]' ],
		];

		// should give precedence to modified data-mw over original (captions 3) /////////////
		$dataMediaWikiModified = [
			'ids' => [
				'mwAg' => []
			]
		];

		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'data-mw' => [
					'body' => $dataMediaWikiModified,
				],
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [ // original data
						'body' => $dataMediaWiki,
					],
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $html
					],
				]
			],
		];
		yield 'should give precedence to modified data-mw over original (captions 3)' => [
			$attribs,
			$html, // modified HTML
			[ '[[File:Foobar.jpg]]' ],
		];

		// should apply extra normalizations ///////////////////
		$htmlModified = 'Foo<h2></h2>Bar';
		$attribs = [
			'opts' => [
				'original' => []
			],
		];
		yield 'should apply extra normalizations' => [
			$attribs,
			$htmlModified, // modified HTML
			[ 'FooBar' ], // empty tag was stripped
		];

		// should apply version downgrade ///////////
		$htmlOfMinimal = $this->getTextFromFile( 'Minimal.html' ); // Uses profile version 2.4.0
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => [
							// Specify newer profile version for original HTML
							'content-type' => 'text/html;profile="https://www.mediawiki.org/wiki/Specs/HTML/999.0.0"'
						],
						// The profile version given inline in the original HTML doesn't matter, it's ignored
						'body' => $htmlOfMinimal,
					],
					'data-parsoid' => [ 'body' => [ 'ids' => [] ] ],
					'data-mw' => [ 'body' => [ 'ids' => [] ] ], // required by version 999.0.0
				]
			],
		];
		yield 'should apply version downgrade' => [
			$attribs,
			$htmlOfMinimal,
			[ '123' ]
		];

		// should not apply version downgrade if versions are the same ///////////
		$htmlOfMinimal = $this->getTextFromFile( 'Minimal.html' ); // Uses profile version 2.4.0
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => [
							// Specify the exact same version specified inline in Minimal.html 2.4.0
							'content-type' => 'text/html;profile="https://www.mediawiki.org/wiki/Specs/HTML/2.4.0"'
						],
						// The profile version given inline in the original HTML doesn't matter, it's ignored
						'body' => $htmlOfMinimal,
					],
					'data-parsoid' => [ 'body' => [ 'ids' => [] ] ],
				]
			],
		];
		yield 'should not apply version downgrade if versions are the same' => [
			$attribs,
			$htmlOfMinimal,
			[ '123' ]
		];

		// should convert html to json ///////////////////////////////////
		$html = $this->getTextFromFile( 'JsonConfig.html' );
		$expectedText = [
			'{"a":4,"b":3}',
		];

		$attribs = [
			'opts' => [
				// even if the path says "wikitext", the contentmodel from the body should win.
				'format' => ParsoidFormatHelper::FORMAT_WIKITEXT,
				'contentmodel' => CONTENT_MODEL_JSON,
			],
		];
		yield 'should convert html to json' => [
			$attribs,
			$html,
			$expectedText,
			[ 'content-type' => 'application/json' ],
		];

		// page bundle input should work with no original data present  ///////////
		$htmlOfMinimal = $this->getTextFromFile( 'Minimal.html' ); // Uses profile version 2.4.0
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [],
			],
		];
		yield 'page bundle input should work with no original data present' => [
			$attribs,
			$htmlOfMinimal,
			[ '123' ]
		];
	}

	private function makePage( $title, $wikitext ): RevisionRecord {
		$title = new TitleValue( NS_MAIN, $title );
		$rev = $this->getServiceContainer()->getRevisionLookup()->getRevisionByTitle( $title );

		if ( $rev ) {
			return $rev;
		}

		/** @var RevisionRecord $rev */
		[ 'revision-record' => $rev ] = $this->editPage( 'Test_html2wt', $wikitext )->getValue();

		return $rev;
	}

	/**
	 * @dataProvider provideHtml2wt
	 *
	 * @param array $attribs
	 * @param string $html
	 * @param string[] $expectedText
	 * @param string[] $expectedHeaders
	 *
	 * @covers MediaWiki\Parser\Parsoid\HtmlToContentTransform
	 * @covers MediaWiki\Rest\Handler\ParsoidHandler::html2wt
	 */
	public function testHtml2wt(
		array $attribs,
		string $html,
		array $expectedText,
		array $expectedHeaders = []
	) {
		$wikitextProfileUri = 'https://www.mediawiki.org/wiki/Specs/wikitext/1.0.0';
		$expectedHeaders += [
			'content-type' => "text/plain; charset=utf-8; profile=\"$wikitextProfileUri\"",
		];

		$wikitext = self::IMPERFECT_WIKITEXT;

		$rev = $this->makePage( 'Test_html2wt', $wikitext );
		$page = $rev->getPage();

		$pageConfig = $this->getPageConfig( $page );

		$attribs += self::DEFAULT_ATTRIBS;
		$attribs['opts'] += self::DEFAULT_ATTRIBS['opts'];
		$attribs['opts']['from'] ??= 'html';
		$attribs['envOptions'] += self::DEFAULT_ATTRIBS['envOptions'];

		if ( $attribs['oldid'] ) {
			// Set the actual ID of an existing revision
			$attribs['oldid'] = $rev->getId();
		}

		$handler = $this->newParsoidHandler();

		$response = $handler->html2wt( $pageConfig, $attribs, $html );
		$body = $response->getBody();
		$body->rewind();
		$wikitext = $body->getContents();

		foreach ( $expectedHeaders as $name => $value ) {
			$this->assertSame( $value, $response->getHeaderLine( $name ) );
		}

		foreach ( (array)$expectedText as $exp ) {
			$this->assertStringContainsString( $exp, $wikitext );
		}
	}

	public function testHtml2wtMetrics() {
		$page = $this->getExistingTestPage();
		$pageConfig = $this->getPageConfig( $page );

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts'] += self::DEFAULT_ATTRIBS['opts'];
		$attribs['opts']['from'] ??= 'html';
		$attribs['envOptions'] += self::DEFAULT_ATTRIBS['envOptions'];

		$metrics = new class () extends MockMetrics {
			public $data = [];

			public function timing( $key, $time ) {
				$this->data[$key] = $time;
				parent::timing( $key, $time );
			}

			public function increment( $key ) {
				$v = $this->data[$key] ?? 0;
				$this->data[$key] = $v + 1;
				return parent::increment( $key );
			}
		};

		$siteConfig = $this->createNoOpMock( SiteConfig::class, [ 'metrics' ] );
		$siteConfig->method( 'metrics' )->willReturn( $metrics );

		$handler = $this->newParsoidHandler( [], [ 'ParsoidSiteConfig' => $siteConfig ] );

		$handler->html2wt( $pageConfig, $attribs, '<p>test</p>' );

		$this->assertArrayHasKey( 'html2wt.size.input', $metrics->data );
		$this->assertArrayHasKey( 'html2wt.original.version.' . Parsoid::defaultHTMLVersion(), $metrics->data );
		$this->assertArrayHasKey( 'html2wt.init', $metrics->data );
		$this->assertArrayHasKey( 'html2wt.total', $metrics->data );
		$this->assertArrayHasKey( 'html2wt.size.output', $metrics->data );
		$this->assertArrayHasKey( 'html2wt.timePerInputKB', $metrics->data );
	}

	public function provideHtml2wtThrows() {
		$html = '<html lang="en"><body>123</body></html>';

		$profileVersion = '2.4.0';
		$htmlProfileUri = 'https://www.mediawiki.org/wiki/Specs/HTML/' . $profileVersion;
		$htmlContentType = "text/html;profile=\"$htmlProfileUri\"";
		$htmlHeaders = [
			'content-type' => $htmlContentType,
		];

		// XXX: what does version 999.0.0 mean?!
		$htmlContentType999 = 'text/html;profile="https://www.mediawiki.org/wiki/Specs/HTML/999.0.0"';
		$htmlHeaders999 = [
			'content-type' => $htmlContentType999,
		];

		// Content-type of original html is missing ////////////////////////////
		$attribs = [
			'opts' => [
				'original' => [
					'html' => [
						// no headers with content type
						'body' => $html,
					],
				]
			],
		];
		yield 'Content-type of original html is missing' => [
			$attribs,
			$html,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'Content-type of original html is missing.' ]
			)
		];

		// should fail to downgrade the original version for an unknown transition ///////////
		$htmlOfMinimal = $this->getTextFromFile( 'Minimal.html' );
		$htmlOfMinimal2222 = $this->getTextFromFile( 'Minimal-2222.html' );
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => [
							// Specify version 2222.0.0!
							'content-type' => 'text/html;profile="https://www.mediawiki.org/wiki/Specs/HTML/2222.0.0"'
						],
						'body' => $htmlOfMinimal2222,
					],
					'data-parsoid' => [ 'body' => [ 'ids' => [] ] ],
				]
			],
		];
		yield 'should fail to downgrade the original version for an unknown transition' => [
			$attribs,
			$htmlOfMinimal,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'No downgrade possible from schema version 2222.0.0 to 2.4.0.' ]
			)
		];

		// DSR offsetType mismatch: UCS2 vs byte ///////////////////////////////
		$attribs = [
			'offsetType' => 'byte',
			'envOptions' => [
				'offsetType' => 'byte',
			],
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $html,
					],
					'data-parsoid' => [
						'body' => [
							'offsetType' => 'UCS2',
							'ids' => [],
						]
					],
				]
			],
		];
		yield 'DSR offsetType mismatch: UCS2 vs byte' => [
			$attribs,
			$html,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'DSR offsetType mismatch: UCS2 vs byte' ]
			)
		];

		// DSR offsetType mismatch: byte vs UCS2 ///////////////////////////////
		$attribs = [
			'offsetType' => 'UCS2',
			'envOptions' => [
				'offsetType' => 'UCS2',
			],
			'opts' => [
				// Enable selser
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $html,
					],
					'data-parsoid' => [
						'body' => [
							'offsetType' => 'byte',
							'ids' => [],
						]
					],
				]
			],
		];
		yield 'DSR offsetType mismatch: byte vs UCS2' => [
			$attribs,
			$html,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'DSR offsetType mismatch: byte vs UCS2' ]
			)
		];

		// Could not find previous revision ////////////////////////////
		$attribs = [
			'oldid' => 1155779922,
			'opts' => [
				// set original HTML to enable selser
				'original' => [
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $html,
					]
				]
			]
		];
		yield 'Could not find previous revision' => [
			$attribs,
			$html,
			new HttpException(
				'The specified revision is deleted or suppressed.',
				404
			)
		];

		// should return a 400 for missing inline data-mw (2.x) ///////////////////
		$html = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">hi</p>';
		$dataParsoid = [ 'ids' => [ 'mwAQ' => [ 'pi' => [ [ [ 'k' => '1' ] ] ] ] ] ];
		$htmlOrig = '<p about="#mwt1" typeof="mw:Transclusion" id="mwAQ">ho</p>';
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'html' => [
						'headers' => $htmlHeaders,
						// slightly modified
						'body' => $htmlOrig,
					]
				]
			],
		];
		yield 'should return a 400 for missing inline data-mw (2.x)' => [
			$attribs,
			$html,
			new HttpException(
				'Cannot serialize mw:Transclusion without data-mw.parts or data-parsoid.src',
				400
			)
		];

		// should return a 400 for not supplying data-mw //////////////////////
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $htmlOrig,
					]
				]
			],
		];
		yield 'should return a 400 for not supplying data-mw' => [
			$attribs,
			$html,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'Invalid data-mw was provided.' ]
			)
		];

		// should return a 400 for missing modified data-mw
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'data-parsoid' => [
						'body' => $dataParsoid,
					],
					'data-mw' => [
						'body' => [
							// Missing data-mw.parts!
							'ids' => [ 'mwAQ' => [] ],
						]
					],
					'html' => [
						'headers' => $htmlHeaders999,
						'body' => $htmlOrig,
					]
				]
			],
		];
		yield 'should return a 400 for missing modified data-mw' => [
			$attribs,
			$html,
			new HttpException(
				'Cannot serialize mw:Transclusion without data-mw.parts or data-parsoid.src',
				400
			)
		];

		// should return http 400 if supplied data-parsoid is empty ////////////
		$html = '<html><head></head><body><p>hi</p></body></html>';
		$htmlOrig = '<html><head></head><body><p>ho</p></body></html>';
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE,
				'original' => [
					'data-parsoid' => [
						'body' => [],
					],
					'html' => [
						'headers' => $htmlHeaders,
						'body' => $htmlOrig,
					]
				]
			],
		];
		yield 'should return http 400 if supplied data-parsoid is empty' => [
			$attribs,
			$html,
			new LocalizedHttpException(
				new MessageValue( 'rest-html-backend-error' ),
				400,
				[ 'reason' => 'Invalid data-parsoid was provided.' ]
			)
		];

		// TODO: ResourceLimitExceededException from $parsoid->dom2wikitext -> 413
		// TODO: ClientError from $parsoid->dom2wikitext -> 413
		// TODO: Errors from PageBundle->validate
	}

	/**
	 * @dataProvider provideHtml2wtThrows
	 *
	 * @param array $attribs
	 * @param string $html
	 * @param Exception $expectedException
	 */
	public function testHtml2wtThrows(
		array $attribs,
		string $html,
		Exception $expectedException
	) {
		if ( isset( $attribs['oldid'] ) ) {
			// If a specific revision ID is requested, it's almost certain to no exist.
			// So we are testing with a non-existing page.
			$page = $this->getNonexistingTestPage();
		} else {
			$page = $this->getExistingTestPage();
		}

		$pageConfig = $this->getPageConfig( $page );

		$attribs += self::DEFAULT_ATTRIBS;
		$attribs['opts'] += self::DEFAULT_ATTRIBS['opts'];
		$attribs['opts']['from'] ??= 'html';
		$attribs['envOptions'] += self::DEFAULT_ATTRIBS['envOptions'];

		$handler = $this->newParsoidHandler();

		try {
			$handler->html2wt( $pageConfig, $attribs, $html );
			$this->fail( 'Expected exception: ' . $expectedException );
		} catch ( Exception $e ) {
			$this->assertInstanceOf( get_class( $expectedException ), $e );
			$this->assertSame( $expectedException->getCode(), $e->getCode() );

			if ( $expectedException instanceof HttpException ) {
				/** @var HttpException $e */
				$this->assertSame( $expectedException->getErrorData(), $e->getErrorData() );
			}

			$this->assertSame( $expectedException->getMessage(), $e->getMessage() );
		}
	}

	public function provideDom2wikitextException() {
		yield 'ClientError' => [
			new ClientError( 'test' ),
			new HttpException( 'test', 400 )
		];

		yield 'ResourceLimitExceededException' => [
			new ResourceLimitExceededException( 'test' ),
			new HttpException( 'test', 413 )
		];
	}

	/**
	 * @dataProvider provideDom2wikitextException
	 *
	 * @param Exception $throw
	 * @param Exception $expectedException
	 */
	public function testHtml2wtHandlesDom2wikitextException(
		Exception $throw,
		Exception $expectedException
	) {
		$html = '<p>hi</p>';
		$page = $this->getExistingTestPage();
		$attribs = [
			'opts' => [
				'from' => ParsoidFormatHelper::FORMAT_HTML
			]
		] + self::DEFAULT_ATTRIBS;

		// Make a fake Parsoid that throws
		/** @var Parsoid|MockObject $parsoid */
		$parsoid = $this->createNoOpMock( Parsoid::class, [ 'dom2wikitext' ] );
		$parsoid->method( 'dom2wikitext' )->willThrowException( $throw );

		// Make a fake HtmlTransformFactory that returns an HtmlToContentTransform that uses the fake Parsoid.
		/** @var HtmlTransformFactory|MockObject $factory */
		$factory = $this->createNoOpMock( HtmlTransformFactory::class, [ 'getHtmlToContentTransform' ] );
		$factory->method( 'getHtmlToContentTransform' )->willReturn( new HtmlToContentTransform(
			$html,
			$page,
			$parsoid,
			[],
			$this->getPageConfigFactory( $page ),
			$this->getServiceContainer()->getContentHandlerFactory()
		) );

		// Use an HtmlInputTransformHelper that uses the fake HtmlTransformFactory, so it ends up
		// using the HtmlToContentTransform that has the fake Parsoid which throws an exception.
		$handler = $this->newParsoidHandler( [
			'getHtmlInputHelper' => function () use ( $factory, $page, $html ) {
				$helper = new HtmlInputTransformHelper(
					new NullStatsdDataFactory(),
					$factory,
					$this->getServiceContainer()->getParsoidOutputStash(),
					$this->getServiceContainer()->getParsoidOutputAccess()
				);

				$helper->init( $page, [ 'html' => $html ], [] );
				return $helper;
			}
		] );

		// Check that the exception thrown by Parsoid gets converted as expected.
		$this->expectException( get_class( $expectedException ) );
		$this->expectExceptionCode( $expectedException->getCode() );
		$this->expectExceptionMessage( $expectedException->getMessage() );

		$handler->html2wt( $page, $attribs, $html );
	}

	/** @return Generator */
	public function provideTryToCreatePageConfigData() {
		$en = $this->createLanguageMock( 'en' );
		$ar = $this->createLanguageMock( 'ar' );
		$de = $this->createLanguageMock( 'de' );
		yield 'Default attribs for tryToCreatePageConfig()' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => 'Test', 'pagelanguage' => $en ],
			'wikitext' => null,
			'html2WtMode' => false,
			'expectedWikitext' => 'UTContent',
			'expectedPageLanguage' => $en,
		];

		yield 'tryToCreatePageConfig with wikitext' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => 'Test', 'pagelanguage' => $en ],
			'wikitext' => "=test=",
			'html2WtMode' => false,
			'expected wikitext' => '=test=',
			'expected page language' => $en,
		];

		yield 'tryToCreatePageConfig with html2WtMode set to true' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => 'Test', 'pagelanguage' => null ],
			'wikitext' => null,
			'html2WtMode' => true,
			'expected wikitext' => 'UTContent',
			'expected page language' => $en,
		];

		yield 'tryToCreatePageConfig with both wikitext and html2WtMode' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => 'Test', 'pagelanguage' => $ar ],
			'wikitext' => "=header=",
			'html2WtMode' => true,
			'expected wikitext' => '=header=',
			'expected page language' => $ar,
		];

		yield 'Try to create a page config with pageName set to empty string' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => '', 'pagelanguage' => $de ],
			'wikitext' => null,
			'html2WtMode' => false,
			'expected wikitext' => 'UTContent',
			'expected page language' => $de,
		];

		yield 'Try to create a page config with no page language' => [
			'attribs' => [ 'oldid' => 1, 'pageName' => '', 'pagelanguage' => null ],
			'wikitext' => null,
			false,
			'expected wikitext' => 'UTContent',
			'expected page language' => $en,
		];
	}

	/**
	 * @covers \MediaWiki\Rest\Handler\ParsoidHandler::tryToCreatePageConfig
	 *
	 * @dataProvider provideTryToCreatePageConfigData
	 */
	public function testTryToCreatePageConfig(
		array $attribs,
		$wikitext,
		$html2WtMode,
		$expectedWikitext,
		Language $expectedLanguage
	) {
		$pageConfig = $this->newParsoidHandler()->tryToCreatePageConfig( $attribs, $wikitext, $html2WtMode );

		$this->assertSame(
			$expectedWikitext,
			$pageConfig->getRevisionContent()->getContent( SlotRecord::MAIN )
		);

		$this->assertSame( $expectedLanguage->getCode(), $pageConfig->getPageLanguageBcp47()->getCode() );
	}

	/** @return Generator */
	public function provideTryToCreatePageConfigDataThrows() {
		$en = $this->createLanguageMock( 'en' );
		yield "PageConfig with oldid that doesn't exist" => [
			'attribs' => [ 'oldid' => null, 'pageName' => 'Test', 'pagelanguage' => $en ],
			'wikitext' => null,
			'html2WtMode' => false,
		];

		yield 'PageConfig with a bad title' => [
			[ 'oldid' => null, 'pageName' => 'Special:Badtitle', 'pagelanguage' => $en ],
			'wikitext' => null,
			'html2WtMode' => false,
		];

		yield "PageConfig with a revision that doesn't exist" => [
			// 'oldid' is so large because we want to emulate a revision
			// that doesn't exist.
			[ 'oldid' => 12345678, 'pageName' => 'Test', 'pagelanguage' => $en ],
			'wikitext' => null,
			'html2WtMode' => false,
		];
	}

	/**
	 * @covers \MediaWiki\Rest\Handler\ParsoidHandler::tryToCreatePageConfig
	 *
	 * @dataProvider provideTryToCreatePageConfigDataThrows
	 */
	public function testTryToCreatePageConfigThrows( array $attribs, $wikitext, $html2WtMode ) {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 404 );

		$this->newParsoidHandler()->tryToCreatePageConfig( $attribs, $wikitext, $html2WtMode );
	}

	public function provideRoundTripNoSelser() {
		yield 'space in heading' => [
			"==foo==\nsomething\n"
		];
	}

	public function provideRoundTripNeedingSelser() {
		yield 'uppercase tags' => [
			"<DIV>foo</div>"
		];
	}

	/**
	 * @dataProvider provideRoundTripNoSelser
	 */
	public function testRoundTripWithHTML( $wikitext ) {
		$handler = $this->newParsoidHandler();

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_HTML;

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext );
		$response = $handler->wt2html( $pageConfig, $attribs, $wikitext );
		$body = $response->getBody();
		$body->rewind();
		$html = $body->getContents();

		// Got HTML, now convert back
		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_HTML;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_WIKITEXT;

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, null, true );
		$response = $handler->html2wt( $pageConfig, $attribs, $html );
		$body = $response->getBody();
		$body->rewind();
		$actual = $body->getContents();

		// apply some normalization before comparing
		$actual = trim( $actual );
		$wikitext = trim( $wikitext );

		$this->assertSame( $wikitext, $actual );
	}

	/**
	 * @dataProvider provideRoundTripNoSelser
	 */
	public function testRoundTripWithPageBundleWithoutOriginalHTML( $wikitext ) {
		$handler = $this->newParsoidHandler();

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_PAGEBUNDLE;

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext );
		$response = $handler->wt2html( $pageConfig, $attribs, $wikitext );
		$body = $response->getBody();
		$body->rewind();
		$pbJson = $body->getContents();

		$pbData = json_decode( $pbJson, JSON_OBJECT_AS_ARRAY );
		$html = $pbData['html']['body']; // HTML with data-parsoid stripped out

		// Got HTML, now convert back
		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_PAGEBUNDLE;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['original'] = [
			'data-parsoid' => $pbData['data-parsoid'],
		];

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, null, true );
		$response = $handler->html2wt( $pageConfig, $attribs, $html );
		$body = $response->getBody();
		$body->rewind();
		$actual = $body->getContents();

		// apply some normalization before comparing
		$actual = trim( $actual );
		$wikitext = trim( $wikitext );

		$this->assertSame( $wikitext, $actual );
	}

	/**
	 * @dataProvider provideRoundTripNoSelser
	 * @dataProvider provideRoundTripNeedingSelser
	 */
	public function testRoundTripWithSelser( $wikitext ) {
		$handler = $this->newParsoidHandler();

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_PAGEBUNDLE;

		$page = $this->getExistingTestPage();
		$revid = $page->getLatest();

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext );
		$response = $handler->wt2html( $pageConfig, $attribs, $wikitext );
		$body = $response->getBody();
		$body->rewind();
		$pbJson = $body->getContents();

		$pbData = json_decode( $pbJson, JSON_OBJECT_AS_ARRAY );
		$html = $pbData['html']['body']; // HTML with data-parsoid stripped out

		// Got HTML, now convert back
		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['oldid'] = $revid;
		$attribs['opts']['revid'] = $revid;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_PAGEBUNDLE;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['original'] = $pbData;
		$attribs['opts']['original']['wikitext']['body'] = $wikitext;

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext, true );
		$response = $handler->html2wt( $pageConfig, $attribs, $html );
		$body = $response->getBody();
		$body->rewind();
		$actual = $body->getContents();

		// apply some normalization before comparing
		$actual = trim( $actual );
		$wikitext = trim( $wikitext );

		$this->assertSame( $wikitext, $actual );
	}

	/**
	 * @dataProvider provideRoundTripNoSelser
	 * @dataProvider provideRoundTripNeedingSelser
	 */
	public function testRoundTripWithStashing( $wikitext ) {
		$handler = $this->newParsoidHandler();

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_HTML;
		$attribs['opts']['stash'] = true;

		$page = $this->getExistingTestPage();
		$revid = $page->getLatest();

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext );
		$response = $handler->wt2html( $pageConfig, $attribs, $wikitext );

		$etag = $response->getHeaderLine( 'etag' );
		$this->assertNotEmpty( $etag, 'ETag' );

		$body = $response->getBody();
		$body->rewind();
		$html = $body->getContents();

		// Got HTML, now convert back
		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['oldid'] = $revid;
		$attribs['opts']['revid'] = $revid;
		$attribs['opts']['from'] = ParsoidFormatHelper::FORMAT_PAGEBUNDLE;
		$attribs['opts']['format'] = ParsoidFormatHelper::FORMAT_WIKITEXT;
		$attribs['opts']['original']['etag'] = $etag;
		$attribs['opts']['original']['wikitext'] = $wikitext;

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, $wikitext, true );
		$response = $handler->html2wt( $pageConfig, $attribs, $html );
		$body = $response->getBody();
		$body->rewind();
		$actual = $body->getContents();

		// apply some normalization before comparing
		$actual = trim( $actual );
		$wikitext = trim( $wikitext );

		$this->assertSame( $wikitext, $actual );
	}

	public function provideLanguageConversion() {
		$en = $this->createLanguageMock( 'en' );
		$enPigLatin = $this->createLanguageMock( 'en-x-piglatin' );
		$profileVersion = Parsoid::AVAILABLE_VERSIONS[0];
		$htmlProfileUri = 'https://www.mediawiki.org/wiki/Specs/HTML/' . $profileVersion;
		$htmlContentType = "text/html; charset=utf-8; profile=\"$htmlProfileUri\"";

		$defaultAttribs = [
			'oldid' => null,
			'pageName' => __METHOD__,
			'opts' => [],
			'envOptions' => [
				'inputContentVersion' => Parsoid::defaultHTMLVersion()
			]
		];

		$attribs = [
			'pagelanguage' => $en,
			'opts' => [
				'updates' => [
					'variant' => [
						'source' => $en,
						'target' => $enPigLatin
					]
				],
			],
		] + $defaultAttribs;

		$revision = [
			'contentmodel' => CONTENT_MODEL_WIKITEXT,
			'html' => [
				'headers' => [
					'content-type' => $htmlContentType,
				],
				'body' => '<p>test language conversion</p>',
			],
		];

		yield [
			$attribs,
			$revision,
			'>esttay anguagelay onversioncay<',
			[
				'content-type' => $htmlContentType,
				'content-language' => $enPigLatin->toBcp47Code(),
			]
		];
	}

	/**
	 * @dataProvider provideLanguageConversion
	 */
	public function testLanguageConversion(
		array $attribs,
		array $revision,
		string $expectedText,
		array $expectedHeaders
	) {
		$handler = $this->newParsoidHandler();

		$pageConfig = $handler->tryToCreatePageConfig( $attribs, null, true );
		$response = $handler->languageConversion( $pageConfig, $attribs, $revision );

		$body = $response->getBody();
		$body->rewind();
		$actual = $body->getContents();

		$pb = json_decode( $actual, true );
		$this->assertNotEmpty( $pb );
		$this->assertArrayHasKey( 'html', $pb );
		$this->assertArrayHasKey( 'body', $pb['html'] );

		$this->assertStringContainsString( $expectedText, $pb['html']['body'] );

		foreach ( $expectedHeaders as $key => $value ) {
			$this->assertArrayHasKey( $key, $pb['html']['headers'] );
			$this->assertSame( $value, $pb['html']['headers'][$key] );
		}
	}

	public function provideWt2html() {
		$profileVersion = '2.6.0';
		$htmlProfileUri = 'https://www.mediawiki.org/wiki/Specs/HTML/' . $profileVersion;
		$pbProfileUri = 'https://www.mediawiki.org/wiki/Specs/pagebundle/' . $profileVersion;
		$dpProfileUri = 'https://www.mediawiki.org/wiki/Specs/data-parsoid/' . $profileVersion;

		$htmlContentType = "text/html; charset=utf-8; profile=\"$htmlProfileUri\"";
		$pbContentType = "application/json; charset=utf-8; profile=\"$pbProfileUri\"";
		$dpContentType = "application/json; charset=utf-8; profile=\"$dpProfileUri\"";
		$lintContentType = "application/json";

		$htmlHeaders = [
			'content-type' => $htmlContentType,
		];

		$pbHeaders = [
			'content-type' => $pbContentType,
		];

		$lintHeaders = [
			'content-type' => $lintContentType,
		];

		// should get from a title and revision (html) ///////////////////////////////////
		$expectedText = [
			'>First Revision Content<',
			'<html', // full document
			'data-parsoid=' // annotated
		];

		$unexpectedText = [];

		$attribs = [
			'oldid' => 1, // will be replaced by a real revision id
		];
		yield 'should get from a title and revision (html)' => [
			$attribs,
			null,
			$expectedText,
			$unexpectedText,
			$htmlHeaders
		];

		// should get from a title and revision (pagebundle) ///////////////////////////////////
		$expectedText = [ // bits of json
			'"body":"<!DOCTYPE html>',
			'First Revision Content</p>',
			'contentmodel' => 'wikitext',
			'data-parsoid' => [
				'headers' => [
					'content-type' => $dpContentType,
				],
				'body' => [
					'counter' => 2,
					'ids' => [ // NOTE: match "First Revision Content"
						'mwAA' => [ 'dsr' => [ 0, 22, 0, 0 ] ],
						'mwAQ' => [],
						'mwAg' => [ 'dsr' => [ 0, 22, 0, 0 ] ],
					],
					'offsetType' => 'ucs2', // as provided in the input
				]
			],
		];

		$unexpectedText = [];

		$attribs = [
			'oldid' => 1, // will be replaced by a real revision id
			'opts' => [ 'format' => ParsoidFormatHelper::FORMAT_PAGEBUNDLE ],
			'envOptions' => [
				'offsetType' => 'ucs2', // make sure this is looped through to data-parsoid attribute
			]
		];
		yield 'should get from a title and revision (pagebundle)' => [
			$attribs,
			null,
			$expectedText,
			$unexpectedText,
			$pbHeaders
		];

		// should parse the given wikitext ///////////////////////////////////
		$wikitext = 'lorem ipsum';
		$expectedText = [
			'>lorem ipsum<',
			'<html', // full document
			'data-parsoid=' // annotated
		];

		$unexpectedText = [];

		$attribs = [];
		yield 'should parse the given wikitext' => [
			$attribs,
			$wikitext,
			$expectedText,
			$unexpectedText,
			$htmlHeaders
		];

		// should parse the given wikitext (body_only) ///////////////////////////////////
		$wikitext = 'lorem ipsum';
		$expectedText = [ '>lorem ipsum<' ];

		$unexpectedText = [ '<html' ];

		$attribs = [
			'body_only' => true
		];
		yield 'should parse the given wikitext (body_only)' => [
			$attribs,
			$wikitext,
			$expectedText,
			$unexpectedText,
			$htmlHeaders
		];

		// should lint the given wikitext ///////////////////////////////////
		$wikitext = "{|\nhi\n|ho\n|}";
		$expectedText = [
			'"type":"fostered"',
			'"dsr"'
		];

		$unexpectedText = [
			'<html'
		];

		$attribs = [
			'opts' => [ 'format' => ParsoidFormatHelper::FORMAT_LINT ]
		];

		yield 'should lint the given wikitext' => [
			$attribs,
			$wikitext,
			$expectedText,
			$unexpectedText,
			$lintHeaders
		];

		// should parse the given JSON ///////////////////////////////////
		$wikitext = '{ "color": "green" }';

		// should be rendered as table, not interpreted as wikitext
		$expectedText = [
			'>color</th>',
			'>green</td>',
			'<html',
		];

		$unexpectedText = [ '<p>' ];

		$attribs = [
			'opts' => [
				'contentmodel' => CONTENT_MODEL_JSON,
			]
		];
		yield 'should parse the given JSON' => [
			$attribs,
			$wikitext,
			$expectedText,
			$unexpectedText,
			$htmlHeaders
		];
	}

	/**
	 * @dataProvider provideWt2html
	 *
	 * @param array $attribs
	 * @param string|null $text
	 * @param string[] $expectedData
	 * @param string[] $unexpectedHtml
	 * @param string[] $expectedHeaders
	 */
	public function testWt2html(
		array $attribs,
		?string $text,
		array $expectedData,
		array $unexpectedHtml,
		array $expectedHeaders = []
	) {
		$htmlProfileUri = 'https://www.mediawiki.org/wiki/Specs/html/2.6.0';
		$expectedHeaders += [
			'content-type' => "text/x-wiki; charset=utf-8; profile=\"$htmlProfileUri\"",
		];

		$page = $this->getNonexistingTestPage( __METHOD__ );
		$status = $this->editPage( $page, 'First Revision Content' );
		$currentRev = $status->getNewRevision();

		$attribs += self::DEFAULT_ATTRIBS;
		$attribs['opts'] += self::DEFAULT_ATTRIBS['opts'];
		$attribs['opts']['from'] ??= 'wikitext';
		$attribs['opts']['format'] ??= 'html';
		$attribs['envOptions'] += self::DEFAULT_ATTRIBS['envOptions'];

		if ( $attribs['oldid'] ) {
			// Set the actual ID of an existing revision
			$attribs['oldid'] = $currentRev->getId();

			// Make sure we are testing against a non-current revision
			$this->editPage( $page, 'this is not the content you are looking for' );
		}

		$handler = $this->newParsoidHandler();

		$revTextOrId = $text ?? $attribs['oldid'] ?? null;
		$pageConfig = $this->getPageConfig( $page, $revTextOrId );
		$response = $handler->wt2html( $pageConfig, $attribs, $text );
		$body = $response->getBody();
		$body->rewind();
		$data = $body->getContents();

		foreach ( $expectedHeaders as $name => $value ) {
			$responseHeaderValue = $response->getHeaderLine( $name );
			if ( $name === 'content-type' ) {
				$this->assertTrue( $this->contentTypeMatcher( $value, $responseHeaderValue ) );
			} else {
				$this->assertSame( $value, $responseHeaderValue );
			}
		}

		// HACK: try to parse as json, just in case:
		$jsonData = json_decode( $data, JSON_OBJECT_AS_ARRAY );

		foreach ( $expectedData as $index => $exp ) {
			if ( is_int( $index ) ) {
				$this->assertStringContainsString( $exp, $data );
			} else {
				$this->assertArrayHasKey( $index, $jsonData );
				if ( $index === 'data-parsoid' ) {
					// FIXME: Assert headers as well
					$this->assertArrayHasKey( 'body', $jsonData[$index] );
					$this->assertSame( $exp['body'], $jsonData[$index]['body'] );
				} else {
					$this->assertSame( $exp, $jsonData[$index] );
				}
			}
		}

		foreach ( $unexpectedHtml as $exp ) {
			$this->assertStringNotContainsString( $exp, $data );
		}
	}

	public function testWt2html_ParserCache() {
		$page = $this->getExistingTestPage();
		$pageConfig = $this->getPageConfig( $page );

		$parserCache = $this->createNoOpMock( ParserCache::class, [ 'save', 'get' ] );

		// This is the critical assertion in this test case: the save() method should
		// be called exactly once!
		$parserCache->expects( $this->once() )->method( 'save' );
		$parserCache->method( 'get' )->willReturn( false );

		$parserCacheFactory = $this->createNoOpMock(
			ParserCacheFactory::class,
			[ 'getParserCache', 'getRevisionOutputCache' ]
		);
		$parserCacheFactory->method( 'getParserCache' )->willReturn( $parserCache );
		$parserCacheFactory->method( 'getRevisionOutputCache' )->willReturn(
			$this->createNoOpMock( RevisionOutputCache::class )
		);

		$this->setService( 'ParserCacheFactory', $parserCacheFactory );

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = 'wikitext';
		$attribs['opts']['format'] = 'html';

		$handler = $this->newParsoidHandler();

		// This should trigger a parser cache write, because we didn't set a write-ratio
		$handler->wt2html( $pageConfig, $attribs );

		$this->overrideConfigValue( 'TemporaryParsoidHandlerParserCacheWriteRatio', 0 );

		// This should not trigger a parser cache write, because we set the write-ration to 0
		$handler->wt2html( $pageConfig, $attribs );
	}

	public function testWt2html_BadContentModel() {
		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, new JavaScriptContent( '"not wikitext"' ) );
		$pageConfig = $this->getPageConfig( $page );

		$attribs = self::DEFAULT_ATTRIBS;
		$attribs['opts']['from'] = 'wikitext';
		// Asking for a 'pagebundle' here because of T325137.
		$attribs['opts']['format'] = 'pagebundle';

		$handler = $this->newParsoidHandler();
		$response = $handler->wt2html( $pageConfig, $attribs );

		$this->assertSame( 200, $response->getStatusCode() );

		$body = $response->getBody();
		$body->rewind();
		$data = $body->getContents();

		$jsonData = json_decode( $data, JSON_OBJECT_AS_ARRAY );

		$this->assertIsArray( $jsonData );
		$this->assertStringContainsString( "Dummy output", $jsonData['html']['body'] );
	}

	// TODO: test wt2html failure modes
	// TODO: test redlinks

	public function createLanguageMock( string $code ) {
		// Ensure that we always return the same object for a given code.
		static $seen = [];
		if ( !isset( $seen[$code] ) ) {
			$langMock = $this->createMock( Language::class );
			$langMock
				->method( 'getCode' )
				->willReturn( $code );
			$bcp47 = LanguageCode::bcp47( $code );
			$langMock
				->method( 'getHtmlCode' )
				->willReturn( $bcp47 );
			$langMock
				->method( 'toBcp47Code' )
				->willReturn( $bcp47 );
			$langMock
				->method( 'getDir' )
				->willReturn( 'ltr' );
			$seen[$code] = $langMock;
		}
		return $seen[$code];
	}
}

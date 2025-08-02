<?php

namespace MediaWiki\SyntaxHighlight;

use MediaWiki\Api\Hook\ApiFormatHighlightHook;
use MediaWiki\Config\Config;
use MediaWiki\Content\Content;
use MediaWiki\Content\Hook\ContentAlterParserOutputHook;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\SoftwareInfoHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Title\Title;

class Hooks implements
	ParserFirstCallInitHook,
	ContentAlterParserOutputHook,
	ResourceLoaderRegisterModulesHook,
	ApiFormatHighlightHook,
	SoftwareInfoHook
{
	/** @var array<string,string> Mapping of MIME-types to lexer names. */
	private static $mimeLexers = [
		'text/javascript'  => 'javascript',
		'application/json' => 'javascript',
		'text/xml'         => 'xml',
	];

	private Config $config;
	private ParserFactory $parserFactory;
	private SyntaxHighlight $syntaxHighlight;

	public function __construct(
		Config $config,
		ParserFactory $parserFactory,
		SyntaxHighlight $syntaxHighlight
	) {
		$this->config = $config;
		$this->parserFactory = $parserFactory;
		$this->syntaxHighlight = $syntaxHighlight;
	}

	/**
	 * Register parser hook
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'source', [ $this->syntaxHighlight, 'parserHookSource' ] );
		$parser->setHook( 'syntaxhighlight', [ $this->syntaxHighlight, 'parserHook' ] );
	}

	/**
	 * Hook to alter the parser output to provide syntax highlighting for
	 * script content.
	 *
	 * @param Content $content Content to render
	 * @param Title $title Title of the page, as context
	 * @param ParserOutput $parserOutput ParserOutput to manipulate
	 */
	public function onContentAlterParserOutput( $content, $title, $parserOutput ) {
		if ( !( $content instanceof TextContent ) ) {
			return;
		}

		// Determine the SyntaxHighlight language from the page's
		// content model. Extensions can extend the default CSS/JS
		// mapping by setting the SyntaxHighlightModels attribute.
		$extension = ExtensionRegistry::getInstance();
		$models = $extension->getAttribute( 'SyntaxHighlightModels' ) + [
			CONTENT_MODEL_CSS => 'css',
			CONTENT_MODEL_JAVASCRIPT => 'javascript',
		];
		$model = $content->getModel();
		if ( !isset( $models[$model] ) ) {
			// We don't care about this model, carry on.
			return;
		}
		$lexer = $models[$model];
		$text = $content->getText();

		$status = $this->syntaxHighlight->syntaxHighlight( $text, $lexer, [ 'line' => true, 'linelinks' => 'L' ] );
		if ( !$status->isOK() ) {
			return;
		}
		$out = $status->getValue();

		$parserOutput->addModuleStyles( SyntaxHighlight::getModuleStyles() );
		$parserOutput->addModules( [ 'ext.pygments.view' ] );
		$parserOutput->setRawText( $out );
	}

	/**
	 * Hook to provide syntax highlighting for API pretty-printed output
	 *
	 * @param IContextSource $context
	 * @param string $text
	 * @param string $mime
	 * @param string $format
	 * @since MW 1.24
	 * @return bool
	 */
	public function onApiFormatHighlight( $context, $text, $mime, $format ) {
		if ( !isset( self::$mimeLexers[$mime] ) ) {
			return true;
		}

		$lexer = self::$mimeLexers[$mime];
		$status = $this->syntaxHighlight->syntaxHighlight( $text, $lexer );
		if ( !$status->isOK() ) {
			return true;
		}

		$out = $status->getValue();
		if ( preg_match( '/^<pre([^>]*)>/i', $out, $m ) ) {
			$attrs = Sanitizer::decodeTagAttributes( $m[1] );
			$attrs['class'] .= ' api-pretty-content';
			$encodedAttrs = Sanitizer::safeEncodeTagAttributes( $attrs );
			$out = '<pre' . $encodedAttrs . '>' . substr( $out, strlen( $m[0] ) );
		}
		$output = $context->getOutput();
		$output->addModuleStyles( SyntaxHighlight::getModuleStyles() );
		$output->addHTML( '<div dir="ltr">' . $out . '</div>' );

		// Inform MediaWiki that we have parsed this page and it shouldn't mess with it.
		return false;
	}

	/**
	 * Hook to add Pygments version to Special:Version
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SoftwareInfo
	 * @param array &$software
	 */
	public function onSoftwareInfo( &$software ) {
		try {
			$software['[https://pygments.org/ Pygments]'] = Pygmentize::getVersion();
		} catch ( PygmentsException $e ) {
			// pass
		}
	}

	/**
	 * Hook to register ext.pygments.view module.
	 * @param ResourceLoader $rl
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		$rl->register( 'ext.pygments.view', [
			'localBasePath' => dirname( __DIR__ ) . '/modules',
			'remoteExtPath' => 'SyntaxHighlight_GeSHi/modules',
			'scripts' => array_merge( [
				'pygments.linenumbers.js',
				'pygments.links.js',
				'pygments.copy.js'
			], ExtensionRegistry::getInstance()->isLoaded( 'Scribunto' ) ? [
				'pygments.links.scribunto.js'
			] : [] ),
			'styles' => [
				'pygments.copy.less'
			],
			'messages' => [
				'syntaxhighlight-button-copy',
				'syntaxhighlight-button-copied'
			],
			'dependencies' => [
				'mediawiki.util',
				'mediawiki.Title'
			]
		] );
	}
}

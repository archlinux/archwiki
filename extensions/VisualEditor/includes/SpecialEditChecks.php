<?php
/**
 * Special page listing Edit Checks and their configuration.
 */

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Content\JsonContent;
use MediaWiki\Extension\VisualEditor\EditCheck\ResourceLoaderData;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\MessageWidget;

class SpecialEditChecks extends SpecialPage {
	private readonly Config $config;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		private readonly Config $coreConfig,
		ConfigFactory $configFactory
	) {
		parent::__construct( 'EditChecks' );

		$this->config = $configFactory->makeConfig( 'visualeditor' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @inheritDoc
	 */
	public function isListed() {
		return (bool)$this->getConfig()->get( 'VisualEditorEditCheck' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();
		if ( !$this->getConfig()->get( 'VisualEditorEditCheck' ) ) {
			$out->addHTML( Html::element( 'p', [], $this->msg( 'editcheck-specialeditchecks-disabled' )->text() ) );
			return;
		}
		$out->enableOOUI();
		$out->addModuleStyles( [
			'oojs-ui.styles.icons-interactions',
			'ext.visualEditor.editCheck.special',
			'mediawiki.content.json'
		] );

		$contentLang = $this->getContext()->getLanguage()->getCode();
		$dir = dirname( __DIR__ );
		$baseDir = $dir . '/editcheck/modules/editchecks';
		$checksDir = $baseDir . '/checks';
		$abstractClasses = [
			'BaseEditCheck.js',
			'AsyncTextCheck.js',
		];
		$onWikiConfig = ResourceLoaderData::getConfig( $this->getContext() );

		$out->addHtml( $this->msg( 'editcheck-specialeditchecks-info' )->parseAsBlock() );

		$abChecks = [];
		$unsupportedChecks = [];
		$defaultChecks = $this->collectChecks(
			$checksDir . '/*.js', $abstractClasses, false, true, null, $onWikiConfig
		);
		$disabledChecks = $this->collectChecks(
			$checksDir . '/*.js', $abstractClasses, false, false, false, $onWikiConfig
		);
		$experimentalEnabledChecks = $this->collectChecks(
			$checksDir . '/*.js', [], false, false, true, $onWikiConfig
		);
		$abTest = MediaWikiServices::getInstance()->getMainConfig()->get( 'VisualEditorEditCheckABTest' );
		if ( $abTest !== null ) {
			foreach ( [ &$defaultChecks, &$disabledChecks, &$experimentalEnabledChecks ] as &$checks ) {
				foreach ( $checks as $i => $check ) {
					// Extract AB test check
					if ( $check['name'] === (string)$abTest ) {
						$abChecks[] = $check;
						unset( $checks[$i] );
					}
					// Extract unsupported checks (not in allowedContentLanguages)
					if ( $check['allowedContentLanguages'] ) {
						if ( !in_array( $contentLang, $check['allowedContentLanguages'], true ) ) {
							$unsupportedChecks[] = $check;
							unset( $checks[$i] );
						}
					}
				}
			}
		}

		$out->addHTML( Html::element( 'h2', [], $this->msg( 'editcheck-specialeditchecks-header-default' )->text() ) );
		$out->addHTML( $this->buildTableHtml( $defaultChecks, $onWikiConfig ) );

		if ( $abChecks ) {
			$out->addHTML( Html::element( 'h2', [],
				$this->msg( 'editcheck-specialeditchecks-header-abtest' )->text() ) );
			$out->addHTML( $this->buildTableHtml( $abChecks, $onWikiConfig ) );
		}

		if ( $this->coreConfig->get( 'VisualEditorEnableEditCheckSuggestionsBeta' ) ) {
			// Split beta and experimental checks based on if they are enabled by default
			$out->addHTML( Html::rawElement( 'h2', [],
				Html::element( 'a', [
					'href' => $this->getTitleFor( 'Preferences' )->getLocalURL() . '#mw-prefsection-betafeatures',
				],
				$this->msg( 'editcheck-specialeditchecks-header-betafeatures' )->text() ) )
			);
			$out->addHTML( $this->buildTableHtml( $experimentalEnabledChecks, $onWikiConfig, true ) );

			$out->addHTML( Html::element( 'h2', [],
				$this->msg( 'editcheck-specialeditchecks-header-experimental' )->text() ) );
			$out->addHTML( $this->buildTableHtml( $disabledChecks, $onWikiConfig, true ) );
		} else {
			$allExperimentalChecks = array_merge( $experimentalEnabledChecks, $disabledChecks );
			// Sort checks by 'name' property
			usort( $allExperimentalChecks, static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			} );
			$out->addHTML( Html::element( 'h2', [],
				$this->msg( 'editcheck-specialeditchecks-header-experimental' )->text() ) );
			$out->addHTML( $this->buildTableHtml( $allExperimentalChecks, $onWikiConfig, true ) );
		}

		if ( $unsupportedChecks ) {
			$out->addHTML( Html::element( 'h2', [],
				$this->msg( 'editcheck-specialeditchecks-header-unsupported' )->text() ) );
			$out->addHTML( $this->buildTableHtml( $unsupportedChecks, $onWikiConfig ) );
		}

		$baseCheck = $this->collectChecks( $baseDir . '/BaseEditCheck.js', [], true );
		if ( isset( $baseCheck[0]['defaultConfig'] ) ) {
			$out->addHTML( Html::element( 'h2', [], $this->msg( 'editcheck-specialeditchecks-header-base' )->text() ) );
			$out->addHTML( $this->configDetails(
				$this->jsonTableFromObjectString( $baseCheck[ 0 ]['defaultConfig'] ),
				isset( $onWikiConfig['*'] ) ? $this->jsonTable( $onWikiConfig['*'] ) : ''
			) );
		}
	}

	/**
	 * Collect edit checks from the given directory.
	 *
	 * @param string $glob Glob pattern for files
	 * @param array $excludeFiles List of filenames to exclude
	 * @param bool $includeAbstract Whether to include abstract classes
	 * @param bool|null $showAsCheck If a boolean, only checks whose 'showAsCheck' value matches this
	 * @param bool|null $showAsSuggestion If a boolean, only checks whose 'showAsSuggestion' value matches this
	 * @return array List of edit checks with metadata
	 */
	private function collectChecks(
		string $glob, array $excludeFiles = [], bool $includeAbstract = false,
		?bool $showAsCheck = null, ?bool $showAsSuggestion = null, array $onWikiConfig = []
	): array {
		$checks = [];
		$files = glob( $glob ) ?: [];
		foreach ( $files as $file ) {
			if ( in_array( basename( $file ), $excludeFiles, true ) ) {
				continue;
			}
			$src = file_get_contents( $file );
			if ( $src === false ) {
				continue;
			}

			$name = $this->extractStaticValue( $src, 'name' );

			// Skip abstract classes (those without a name)
			if ( !$includeAbstract && $name === '' ) {
				continue;
			}

			$checkData = [
				'file' => $file,
				'name' => $name,
				'title' => $this->extractStaticValue( $src, 'title' ),
				'description' => $this->extractStaticValue( $src, 'description' ),
				'prompt' => $this->extractStaticValue( $src, 'prompt' ),
				'footer' => $this->extractStaticValue( $src, 'footer' ),
				'choices' => $this->extractStaticValue( $src, 'choices' ),
				'allowedContentLanguages' => $this->extractStaticValue( $src, 'allowedContentLanguages' ),
				'defaultConfig' => $this->extractDefaultConfig( $src ),
			];

			// Filter by showAsCheck value if requested
			if ( $showAsCheck !== null ) {
				$showAsCheckValue = $this->getConfigValueFromData( $checkData, $onWikiConfig, 'showAsCheck' ) ?? true;
				if ( $showAsCheckValue !== $showAsCheck ) {
					continue;
				}
			}

			// Filter by showAsSuggestion value if requested
			if ( $showAsSuggestion !== null ) {
				$showAsSuggestionValue =
					$this->getConfigValueFromData( $checkData, $onWikiConfig, 'showAsSuggestion' ) ?? true;
				if ( $showAsSuggestionValue !== $showAsSuggestion ) {
					continue;
				}
			}

			$checks[] = $checkData;
		}
		usort( $checks, static function ( $a, $b ) {
			return strcmp( basename( $a['file'] ), basename( $b['file'] ) );
		} );
		return $checks;
	}

	/**
	 * Build HTML table listing the given edit checks.
	 *
	 * @param array $checks List of edit checks
	 * @param array $onWikiConfig On-wiki configuration overrides
	 * @param bool $suggestions
	 * @return string
	 */
	private function buildTableHtml(
		array $checks, array $onWikiConfig, bool $suggestions = false
	): string {
		if ( !$checks ) {
			return Html::element( 'p', [], $this->msg( 'table_pager_empty' )->text() );
		}
		$html = Html::openElement( 'table', [ 'class' => 'wikitable mw-editchecks' ] );
		$html .= Html::rawElement( 'tr', [],
			Html::element( 'th', [ 'class' => 'mw-editchecks-name' ],
				$this->msg( 'editcheck-specialeditchecks-col-name' )->text() ) .
			Html::element( 'th', [ 'class' => 'mw-editchecks-appearance' ],
				$this->msg( 'editcheck-specialeditchecks-col-appearance' )->text() ) .
			Html::element( 'th', [ 'class' => 'mw-editchecks-config' ],
				$this->msg( 'editcheck-specialeditchecks-config-summary' )->text() )
		);
		foreach ( $checks as $checkData ) {
			$html .= $this->buildRowHtml( $checkData, $onWikiConfig, $suggestions );
			if ( $checkData['name'] === 'textMatch' ) {
				$matchItems = $this->getConfigValueFromData( $checkData, $onWikiConfig, 'matchItems' ) ?? [];
				foreach ( $matchItems as $name => $item ) {
					if ( isset( $item['import'] ) ) {
						$importTitle = Title::newFromText( $item['import'] );
						$item = json_decode( $this->msg( $importTitle->getText() )->inContentLanguage()->text(), true );
					}
					$mode = $item['mode'] ?? '';
					// Filter choices to ones containing the mode if requested
					$choices = array_filter(
						$checkData['choices'] ?? [],
						static function ( $choice ) use ( $mode ) {
							return in_array( $mode, $choice['modes'], true );
						}
					);
					$matchCheckData = [
						'file' => '',
						'name' => $checkData['name'] . " ($name)",
						'title' => $item['title'] ?? '',
						'description' => new \OOUI\HtmlSnippet( ( new RawMessage( $item['message'] ?? '' ) )->parse() ),
						'prompt' => $item['prompt'] ?? '',
						'footer' => $item['footer'] ?? '',
						'choices' => $choices,
						'allowedContentLanguages' => '',
						'defaultConfig' => json_encode( $item['config'] ?? '' ),
						'matchItem' => $item,
					];
					$html .= $this->buildRowHtml( $matchCheckData, $onWikiConfig, $suggestions );
				}
			}
		}
		$html .= Html::closeElement( 'table' );
		return $html;
	}

	/**
	 * Build HTML for a single edit check row.
	 *
	 * @param array $checkData Edit check data
	 * @param array $onWikiConfig On-wiki configuration overrides
	 * @param bool $suggestions
	 * @return string Row HTML or empty string if filtered out
	 */
	private function buildRowHtml(
		array $checkData, array $onWikiConfig, bool $suggestions = false
	): string {
		$html = '';
		$override = '';
		if ( isset( $onWikiConfig[$checkData['name']] ) ) {
			$override = $this->jsonTable( $onWikiConfig[$checkData['name']] );
		}
		$defaultConfig = '';
		if ( $checkData['defaultConfig'] ) {
			$defaultConfig = $this->jsonTableFromObjectString( $checkData['defaultConfig'] );
		}

		if ( empty( $checkData['title'] ) && empty( $checkData['description'] ) ) {
			$widget = '';
		} else {
			$widget = $this->buildEditCheckActionWidget( $checkData, $suggestions );
		}

		$html .= Html::rawElement( 'tr', [],
			Html::rawElement( 'td', [],
				Html::element( 'strong', [], $checkData['name'] ) .
				Html::element( 'div', [], basename( $checkData['file'] ) )
			) .
			Html::rawElement( 'td', [],
				Html::rawElement(
					'div',
					[ 'class' => 've-ui-editCheckDialog' ],
					$widget
				)
			) .
			Html::rawElement( 'td', [],
				( $defaultConfig !== '' || $override !== '' ?
					$this->configDetails( $defaultConfig, $override ) : ''
				) .
				( !empty( $checkData['matchItem'] ) ?
					$this->matchItemDetails( $checkData['matchItem'] ) : ''
				)
			)
		);
		return $html;
	}

	private function buildEditCheckActionWidget( array $checkData, bool $suggestion ): MessageWidget {
		$widget = new MessageWidget(
			[
				'type' => $suggestion ? 'progressive' : 'warning',
				'icon' => $suggestion ? 'lightbulb' : null,
				'label' => $checkData['title'] ?: "\u{00A0}",
				'classes' => [ 've-ui-editCheckActionWidget' ]
			]
		);
		if ( $suggestion ) {
			$widget->clearFlags()->setFlags( [ 'progressive' ] );
		}
		if ( $suggestion ) {
			$widget->addClasses( [ 've-ui-editCheckActionWidget-suggestion' ] );
		}
		$actions = new \OOUI\Tag( 'div' );
		$actions->addClasses( [ 've-ui-editCheckActionWidget-actions' ]	);
		if ( $checkData['prompt'] ) {
			$actions
				->addClasses( [ 've-ui-editCheckActionWidget-actions-prompted' ] )
				->appendContent(
					new \OOUI\LabelWidget( [
						'label' => $checkData['prompt'],
						'classes' => [ 've-ui-editCheckActionWidget-prompt' ]
					] ),
				);
		}
		$body = ( new \OOUI\Tag( 'div' ) )->addClasses( [ 've-ui-editCheckActionWidget-body' ] );
		$widget->appendContent(
			$body
				->appendContent( new \OOUI\LabelWidget( [ 'label' => $checkData['description'] ] ) )
				->appendContent( $actions )
		);
		if ( $checkData['footer'] ) {
			$body->appendContent(
				new \OOUI\LabelWidget( [
					'label' => $checkData['footer'],
					'classes' => [ 've-ui-editCheckActionWidget-footer' ]
				] ),
			);
		}

		if ( !empty( $checkData['choices'] ) ) {
			foreach ( $checkData['choices'] as $choice ) {
				$actionButton = new \OOUI\ButtonWidget( [
					'label' => $choice[ 'label' ],
					'flags' => $choice['flags'] ?? [],
					'icon' => $choice['icon'] ?? null,
					'classes' => [ 'oo-ui-actionWidget' ],
				] );
				$actions->appendContent( $actionButton );
			}
		}
		return $widget;
	}

	/**
	 * Get a configuration value for a given check from on-wiki config or default config.
	 *
	 * @param array $checkData Check metadata
	 * @param array $onWikiConfig On-wiki configuration overrides
	 * @param string $key Configuration key to retrieve
	 * @return mixed|null JSON encoded value or null if not found
	 */
	private function getConfigValueFromData( array $checkData, array $onWikiConfig, string $key ) {
		// Check on-wiki config first
		if ( isset( $onWikiConfig[$checkData['name']] ) &&
			is_array( $onWikiConfig[$checkData['name']] ) &&
			array_key_exists( $key, $onWikiConfig[$checkData['name']] )
		) {
			return $onWikiConfig[$checkData['name']][$key];
		} elseif ( $checkData['defaultConfig'] !== '' ) {
			// Fallback to default config
			$defaultConfig = $this->tryJsonDecodeObjectString( $checkData['defaultConfig'] );
			if ( is_array( $defaultConfig ) && array_key_exists( $key, $defaultConfig ) ) {
				return $defaultConfig[$key];
			}
		}
		return null;
	}

	/**
	 * Build the details element showing default and on-wiki configuration.
	 *
	 * @param string $defaultConfig Default configuration display
	 * @param string $override On-wiki override display
	 * @return string
	 */
	private function configDetails( string $defaultConfig, string $override ): string {
		return ( $defaultConfig !== '' ?
			Html::element( 'strong', [ 'class' => 'mw-editchecks-config-header' ],
				$this->msg( 'editcheck-specialeditchecks-config-default' )->text() ) .
			$defaultConfig
		: '' ) .
		( $override !== '' ?
			Html::rawElement( 'details', [],
				Html::rawElement( 'summary', [],
					Html::element( 'strong', [ 'class' => 'mw-editchecks-config-header' ],
						$this->msg( 'editcheck-specialeditchecks-config-onwiki' )->text() )
				) .
				$override
			)
			: ''
		);
	}

	/**
	 * Build the details element showing a textMatch matchItem configuration.
	 *
	 * @param array $matchItem Match item data
	 * @return string
	 */
	private function matchItemDetails( array $matchItem ): string {
		// Skip already displayed fields
		$matchItemFiltered = array_filter(
			$matchItem,
			static function ( $key ) {
				return !in_array( $key, [ 'config', 'title', 'message', 'prompt', 'footer' ], true );
			},
			ARRAY_FILTER_USE_KEY
		);

		return Html::rawElement( 'details', [],
			Html::rawElement( 'summary', [],
				Html::element( 'strong', [ 'class' => 'mw-editchecks-config-header' ],
					$this->msg( 'editcheck-specialeditchecks-config-matchitem' )->text() )
			) .
			$this->jsonTable( $matchItemFiltered )
		);
	}

	/**
	 * Build a regex for matching supported message-call expressions.
	 *
	 * @param bool $anchored Whether to anchor the pattern to the whole string
	 * @return string
	 */
	private function getMessageExpressionPattern( bool $anchored ): string {
		$start = $anchored ? '^' : '\b';
		$end = $anchored ? '$' : '';
		$pattern =
			'/' .
			$start .
			'(ve\.msg|ve\.htmlMsg|ve\.deferHtmlMsg|ve\.deferJQueryMsg|mw\.msg|OO\.ui\.deferMsg)' .
			"\\s*\\(\\s*(\"|')" .
			"([^\"']+)" .
			"\\2(.*?)\\)" .
			$end .
			'/s';
		return $pattern;
	}

	/**
	 * Parse a JS message expression and return the rendered message.
	 *
	 * @param string $expr
	 * @return string|\OOUI\HtmlSnippet Empty string if the expression doesn't match.
	 */
	private function parseMessage( string $expr ) {
		// Message calls:
		// - ve.msg(...)
		// - ve.htmlMsg(...)
		// - ve.deferHtmlMsg(...)
		// - ve.deferJQueryMsg(...)
		// - mw.msg(...)
		// - OO.ui.deferMsg(...)
		if ( preg_match( $this->getMessageExpressionPattern( true ), $expr, $mm ) ) {
			$argsStr = $mm[4];
			$args = [];
			if ( preg_match_all( '/,\s*([\"\\\'])(.*?)\1/', $argsStr, $am, PREG_SET_ORDER ) ) {
				foreach ( $am as $a ) {
					$args[] = $a[2];
				}
			}
			$msg = $this->getContext()->msg( $mm[3], ...$args );
			switch ( $mm[1] ) {
				case 've.htmlMsg':
				case 've.deferHtmlMsg':
				case 've.deferJQueryMsg':
					return new \OOUI\HtmlSnippet( $msg->parse() );
				default:
					return $msg->text();
			}
		}

		return '';
	}

	/**
	 * Extract a static property value.
	 *
	 * @param string $src Source code
	 * @param string $prop Property name
	 * @return string|array|null|\OOUI\HtmlSnippet
	 */
	private function extractStaticValue( string $src, string $prop ) {
		$expr = $this->extractStaticAssignmentExpression( $src, $prop );
		if ( $expr === null ) {
			return null;
		}

		// Literal
		if ( preg_match( '/^([\"\\\'])(.*?)\1$/', $expr, $mm ) ) {
			if ( $prop === 'name' ) {
				return $mm[2];
			} else {
				return new \OOUI\HtmlSnippet( $mm[2] );
			}
		}

		$message = $this->parseMessage( $expr );
		if ( $message !== '' ) {
			return $message;
		}

		// For non-literal, non-message values, only expose data we explicitly care about.
		// The common use-case here is extracting multi-line arrays/objects like `static.choices = [ ... ];`.
		if ( $prop === 'choices' || $prop === 'allowedContentLanguages' ) {
			return $this->tryJsonDecodeObjectString( $expr );
		}

		return null;
	}

	/**
	 * Extract the full RHS expression of a `static.<prop> = ...;` assignment.
	 *
	 * @param string $src Code
	 * @param string $prop Property name
	 * @return string|null RHS expression or null if not found
	 */
	private function extractStaticAssignmentExpression( string $src, string $prop ): ?string {
		$pattern = '/static\s*\.\s*' . preg_quote( $prop, '/' ) . '\s*=\s*/';
		if ( !preg_match( $pattern, $src, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$start = $m[ 0 ][ 1 ] + strlen( $m[ 0 ][ 0 ] );
		$end = strpos( $src, ';', $start );
		if ( $end === false ) {
			return trim( substr( $src, $start ) );
		}
		return trim( substr( $src, $start, $end - $start ) );
	}

	/**
	 * Extract the defaultConfig object literal from the source code.
	 */
	private function extractDefaultConfig( string $src ): string {
		// Capture object literal used as overrides in ve.extendObject(..., {...}) or a direct object.
		if (
			preg_match(
				'/static\s*\.\s*defaultConfig\s*=' .
				'\s*ve\.extendObject\s*\(\s*\{.*?\}\s*,\s*[^,]+,\s*(\{[\s\S]*?\})\s*\)\s*;/',
				$src, $m )
		) {
			return $m[ 1 ];
		}
		if ( preg_match( '/static\s*\.\s*defaultConfig\s*=\s*(\{[\s\S]*?\})\s*;/', $src, $m ) ) {
			return $m[ 1 ];
		}
		return '';
	}

	/**
	 * Attempt to convert a JS object literal to valid JSON for display.
	 * Returns pretty-printed JSON string or null on failure.
	 *
	 * @param string $js JS object literal
	 * @return mixed|null JSON decoded data or null on failure
	 */
	private function tryJsonDecodeObjectString( string $js ) {
		$src = trim( $js );
		// Remove comments
		$src = preg_replace( '/\/\/.*?(?=\n|$)/', '', $src );
		$src = preg_replace( '/\/\*[\s\S]*?\*\//', '', $src );
		// Remove trailing commas before } or ]
		$src = preg_replace( '/,\s*(\}|\])/', '$1', $src );
		// Replace undefined with null
		$src = preg_replace( '/\bundefined\b/', 'null', $src );
		// Quote unquoted keys: { key: ... } or , key: ...
		$src = preg_replace( '/([\{,]\s*)([A-Za-z_$][A-Za-z0-9_$]*)\s*:/', '$1"$2":', $src );
		// Message expressions, e.g. ve.msg('...'), OO.ui.deferMsg('...'), mw.msg('...')
		$messageExprPattern = $this->getMessageExpressionPattern( false );
		$src = preg_replace_callback(
			$messageExprPattern,
			function ( $mm ) {
				return json_encode( (string)$this->parseMessage( $mm[0] ) );
			},
			$src
		);

		$src = $this->convertSingleQuotedToDoubleQuoted( $src );

		// Try decode
		$data = json_decode( $src, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}
		return $data;
	}

	/**
	 * Attempt to convert a JS object literal to valid JSON for display.
	 * Returns pretty-printed JSON string or null on failure.
	 *
	 * @param string $js JS object literal
	 * @return string
	 */
	private function jsonTableFromObjectString( string $js ): string {
		$data = $this->tryJsonDecodeObjectString( $js );

		return $data === null ? $js : $this->jsonTable( $data );
	}

	/**
	 * Convert all JS single-quoted string literals to double-quoted, handling escapes.
	 */
	private function convertSingleQuotedToDoubleQuoted( string $code ): string {
		$out = '';
		$len = strlen( $code );
		$inSingle = false;
		$inDouble = false;
		$escape = false;
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $code[$i];
			if ( $escape ) {
				// Preserve escaped char, but if we are in a single-quoted string
				// and the escaped char is a single quote, drop the escape
				if ( $inSingle && $ch === '\'' ) {
					$out .= '\'';
				} else {
					$out .= '\\' . $ch;
				}
				$escape = false;
				continue;
			}
			if ( $ch === '\\' ) {
				$escape = true;
				continue;
			}
			if ( !$inDouble && $ch === '\'' ) {
				// Toggle single-quoted string; replace quote with double quote
				$inSingle = !$inSingle;
				$out .= '"';
				continue;
			}
			if ( !$inSingle && $ch === '"' ) {
				// Track double quotes to avoid interfering while inside
				$inDouble = !$inDouble;
				$out .= '"';
				continue;
			}
			// Inside single-quoted string: ensure double quotes are escaped
			if ( $inSingle && $ch === '"' ) {
				$out .= '\\"';
				continue;
			}
			$out .= $ch;
		}
		return $out;
	}

	/**
	 * Format a JSON value as a table.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function jsonTable( $value ): string {
		$json = json_encode( $value );
		$content = new JsonContent( $json );
		return $content->rootValueTable( $content->getData()->getValue() );
	}
}

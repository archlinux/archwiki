<?php
/**
 * Wikitext scripting infrastructure for MediaWiki: hooks.
 * Copyright (C) 2009-2012 Victor Vasiliev <vasilvv@gmail.com>
 * https://www.mediawiki.org/
 *
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
 */

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\Scribunto;

use Article;
use Content;
use EmptyBagOStuff;
use Html;
use IContextSource;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\EditPage__showReadOnlyForm_initialHook;
use MediaWiki\Hook\EditPage__showStandardInputs_optionsHook;
use MediaWiki\Hook\EditPageBeforeEditButtonsHook;
use MediaWiki\Hook\ParserClearStateHook;
use MediaWiki\Hook\ParserClonedHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserLimitReportFormatHook;
use MediaWiki\Hook\ParserLimitReportPrepareHook;
use MediaWiki\Hook\SoftwareInfoHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\ArticleViewHeaderHook;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use ObjectCache;
use OutputPage;
use Parser;
use ParserOutput;
use PPFrame;
use Status;
use User;
use UtfNormal\Validator;
use Wikimedia\PSquare;
use Xml;

/**
 * Hooks for the Scribunto extension.
 */
class Hooks implements
	SoftwareInfoHook,
	ParserFirstCallInitHook,
	ParserLimitReportPrepareHook,
	ParserLimitReportFormatHook,
	ParserClearStateHook,
	ParserClonedHook,
	EditPage__showStandardInputs_optionsHook,
	EditPage__showReadOnlyForm_initialHook,
	EditPageBeforeEditButtonsHook,
	EditFilterMergedContentHook,
	ArticleViewHeaderHook,
	ContentHandlerDefaultModelForHook
{
	/**
	 * Define content handler constant upon extension registration
	 */
	public static function onRegistration() {
		define( 'CONTENT_MODEL_SCRIBUNTO', 'Scribunto' );
	}

	/**
	 * Get software information for Special:Version
	 *
	 * @param array &$software
	 * @return bool
	 */
	public function onSoftwareInfo( &$software ) {
		$engine = Scribunto::newDefaultEngine();
		$engine->setTitle( Title::makeTitle( NS_SPECIAL, 'Version' ) );
		$engine->getSoftwareInfo( $software );
		return true;
	}

	/**
	 * Register parser hooks.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'invoke', [ self::class, 'invokeHook' ], Parser::SFH_OBJECT_ARGS );
		return true;
	}

	/**
	 * Called when the interpreter is to be reset.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public function onParserClearState( $parser ) {
		Scribunto::resetParserEngine( $parser );
		return true;
	}

	/**
	 * Called when the parser is cloned
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public function onParserCloned( $parser ) {
		$parser->scribunto_engine = null;
		return true;
	}

	/**
	 * Hook function for {{#invoke:module|func}}
	 *
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string
	 */
	public static function invokeHook( Parser $parser, PPFrame $frame, array $args ) {
		global $wgScribuntoGatherFunctionStats;

		try {
			if ( count( $args ) < 2 ) {
				throw new ScribuntoException( 'scribunto-common-nofunction' );
			}
			$moduleName = trim( $frame->expand( $args[0] ) );
			$engine = Scribunto::getParserEngine( $parser );

			$title = Title::makeTitleSafe( NS_MODULE, $moduleName );
			if ( !$title || !$title->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
				throw new ScribuntoException( 'scribunto-common-nosuchmodule',
					[ 'args' => [ $moduleName ] ] );
			}
			$module = $engine->fetchModuleFromParser( $title );
			if ( !$module ) {
				throw new ScribuntoException( 'scribunto-common-nosuchmodule',
					[ 'args' => [ $moduleName ] ] );
			}
			$functionName = trim( $frame->expand( $args[1] ) );

			$bits = $args[1]->splitArg();
			unset( $args[0] );
			unset( $args[1] );

			// If $bits['index'] is empty, then the function name was parsed as a
			// key=value pair (because of an equals sign in it), and since it didn't
			// have an index, we don't need the index offset.
			$childFrame = $frame->newChild( $args, $title, $bits['index'] === '' ? 0 : 1 );

			if ( $wgScribuntoGatherFunctionStats ) {
				$u0 = $engine->getResourceUsage( $engine::CPU_SECONDS );
				$result = $module->invoke( $functionName, $childFrame );
				$u1 = $engine->getResourceUsage( $engine::CPU_SECONDS );

				if ( $u1 > $u0 ) {
					$timingMs = (int)( 1000 * ( $u1 - $u0 ) );
					// Since the overhead of stats is worst when #invoke
					// calls are very short, don't process measurements <= 20ms.
					if ( $timingMs > 20 ) {
						self::reportTiming( $moduleName, $functionName, $timingMs );
					}
				}
			} else {
				$result = $module->invoke( $functionName, $childFrame );
			}

			return Validator::cleanUp( strval( $result ) );
		} catch ( ScribuntoException $e ) {
			$trace = $e->getScriptTraceHtml( [ 'msgOptions' => [ 'content' ] ] );
			$html = Html::element( 'p', [], $e->getMessage() );
			if ( $trace !== false ) {
				$html .= Html::element( 'p',
					[],
					wfMessage( 'scribunto-common-backtrace' )->inContentLanguage()->text()
				) . $trace;
			} else {
				$html .= Html::element( 'p',
					[],
					wfMessage( 'scribunto-common-no-details' )->inContentLanguage()->text()
				);
			}
			$parserOutput = $parser->getOutput();
			$errors = $parserOutput->getExtensionData( 'ScribuntoErrors' );
			if ( $errors === null ) {
				// On first hook use, set up error array and output
				$errors = [];
				$parser->addTrackingCategory( 'scribunto-common-error-category' );
				$parserOutput->addModules( [ 'ext.scribunto.errors' ] );
			}
			$errors[] = $html;
			$parserOutput->setExtensionData( 'ScribuntoErrors', $errors );
			$parserOutput->addJsConfigVars( 'ScribuntoErrors', $errors );
			$id = 'mw-scribunto-error-' . ( count( $errors ) - 1 );
			$parserError = htmlspecialchars( $e->getMessage() );

			// #iferror-compatible error element
			return "<strong class=\"error\"><span class=\"scribunto-error\" id=\"$id\">" .
				$parserError . "</span></strong>";
		}
	}

	/**
	 * Record stats on slow function calls.
	 *
	 * @param string $moduleName
	 * @param string $functionName
	 * @param int $timing Function execution time in milliseconds.
	 */
	public static function reportTiming( $moduleName, $functionName, $timing ) {
		global $wgScribuntoGatherFunctionStats, $wgScribuntoSlowFunctionThreshold;

		if ( !$wgScribuntoGatherFunctionStats ) {
			return;
		}

		$threshold = $wgScribuntoSlowFunctionThreshold;
		if ( !( is_float( $threshold ) && $threshold > 0 && $threshold < 1 ) ) {
			return;
		}

		static $cache;
		if ( !$cache ) {
			$cache = ObjectCache::getLocalServerInstance( CACHE_NONE );

		}

		// To control the sampling rate, we keep a compact histogram of
		// observations in APC, and extract the Nth percentile (specified
		// via $wgScribuntoSlowFunctionThreshold; defaults to 0.90).
		// We need a non-empty local server cache for that (e.g. php-apcu).
		if ( $cache instanceof EmptyBagOStuff ) {
			return;
		}

		$cacheVersion = '3';
		$key = $cache->makeGlobalKey( 'scribunto-stats', $cacheVersion, (string)$threshold );

		// This is a classic "read-update-write" critical section with no
		// mutual exclusion, but the only consequence is that some samples
		// will be dropped. We only need enough samples to estimate the
		// shape of the data, so that's fine.
		$ps = $cache->get( $key ) ?: new PSquare( $threshold );
		$ps->addObservation( $timing );
		$cache->set( $key, $ps, 60 );

		if ( $ps->getCount() < 1000 || $timing < $ps->getValue() ) {
			return;
		}

		static $stats;
		if ( !$stats ) {
			$stats = MediaWikiServices::getInstance()->getStatsdDataFactory();
		}

		$metricKey = sprintf( 'scribunto.traces.%s__%s__%s', WikiMap::getCurrentWikiId(), $moduleName, $functionName );
		$stats->timing( $metricKey, $timing );
	}

	/**
	 * Set the Scribunto content handler for modules
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return bool
	 */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( $model === 'sanitized-css' ) {
			// Let TemplateStyles override Scribunto
			return true;
		}
		if ( $title->getNamespace() === NS_MODULE ) {
			if ( str_ends_with( $title->getText(), '.json' ) ) {
				$model = CONTENT_MODEL_JSON;
			} elseif ( !Scribunto::isDocPage( $title ) ) {
				$model = CONTENT_MODEL_SCRIBUNTO;
			}
			return true;
		}
		return true;
	}

	/**
	 * Adds report of number of evaluations by the single wikitext page.
	 *
	 * @param Parser $parser
	 * @param ParserOutput $parserOutput
	 * @return bool
	 */
	public function onParserLimitReportPrepare( $parser, $parserOutput ) {
		if ( Scribunto::isParserEnginePresent( $parser ) ) {
			$engine = Scribunto::getParserEngine( $parser );
			$engine->reportLimitData( $parserOutput );
		}
		return true;
	}

	/**
	 * Formats the limit report data
	 *
	 * @param string $key
	 * @param mixed &$value
	 * @param string &$report
	 * @param bool $isHTML
	 * @param bool $localize
	 * @return bool
	 */
	public function onParserLimitReportFormat( $key, &$value, &$report, $isHTML, $localize ) {
		$engine = Scribunto::newDefaultEngine();
		return $engine->formatLimitData( $key, $value, $report, $isHTML, $localize );
	}

	/**
	 * EditPage::showStandardInputs:options hook
	 *
	 * @param EditPage $editor
	 * @param OutputPage $output
	 * @param int &$tab Current tabindex
	 * @return bool
	 */
	public function onEditPage__showStandardInputs_options( $editor, $output, &$tab ) {
		if ( $editor->getTitle()->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
			$output->addModules( 'ext.scribunto.edit' );
			$editor->editFormTextAfterTools .= '<div id="mw-scribunto-console"></div>';
		}
		return true;
	}

	/**
	 * EditPage::showReadOnlyForm:initial hook
	 *
	 * @param EditPage $editor
	 * @param OutputPage $output
	 * @return bool
	 */
	public function onEditPage__showReadOnlyForm_initial( $editor, $output ) {
		if ( $editor->getTitle()->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
			$output->addModules( 'ext.scribunto.edit' );
			$editor->editFormTextAfterContent .= '<div id="mw-scribunto-console"></div>';
		}
		return true;
	}

	/**
	 * EditPageBeforeEditButtons hook
	 *
	 * @param EditPage $editor
	 * @param array &$buttons Button array
	 * @param int &$tabindex Current tabindex
	 * @return bool
	 */
	public function onEditPageBeforeEditButtons( $editor, &$buttons, &$tabindex ) {
		if ( $editor->getTitle()->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
			unset( $buttons['preview'] );
		}
		return true;
	}

	/**
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @return bool
	 */
	public function onEditFilterMergedContent( IContextSource $context, Content $content,
		Status $status, $summary, User $user, $minoredit
	) {
		$title = $context->getTitle();

		if ( !$content instanceof ScribuntoContent ) {
			return true;
		}
		$contentHandlerFactory = MediaWikiServices::getInstance()->getContentHandlerFactory();
		$contentHandler = $contentHandlerFactory->getContentHandler( $content->getModel() );

		'@phan-var ScribuntoContentHandler $contentHandler';
		$validateStatus = $contentHandler->validate( $content, $title );
		if ( $validateStatus->isOK() ) {
			return true;
		}

		$status->merge( $validateStatus );

		if ( isset( $validateStatus->value->params['module'] ) ) {
			$module = $validateStatus->value->params['module'];
			$line = $validateStatus->value->params['line'];
			if ( $module === $title->getPrefixedDBkey() && preg_match( '/^\d+$/', $line ) ) {
				$out = $context->getOutput();
				$out->addInlineScript( 'window.location.hash = ' . Xml::encodeJsVar( "#mw-ce-l$line" ) );
			}
		}
		if ( !$status->isOK() ) {
			// @todo Remove this line after this extension do not support mediawiki version 1.36 and before
			$status->value = EditPage::AS_HOOK_ERROR_EXPECTED;
			return false;
		}

		return true;
	}

	/**
	 * @param Article $article
	 * @param bool|ParserOutput|null &$outputDone
	 * @param bool &$pcache
	 * @return bool
	 */
	public function onArticleViewHeader( $article, &$outputDone, &$pcache ) {
		$title = $article->getTitle();
		if ( Scribunto::isDocPage( $title, $forModule ) ) {
			$article->getContext()->getOutput()->addHTML(
				wfMessage( 'scribunto-doc-page-header', $forModule->getPrefixedText() )->parseAsBlock()
			);
		}
		return true;
	}
}

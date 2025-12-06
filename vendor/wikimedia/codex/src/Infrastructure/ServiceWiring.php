<?php
/**
 * ServiceWiring.php
 *
 * This file returns the array loaded by the CodexServices class
 * for use through `Wikimedia\CodexServices::getInstance()`. Each service is associated with a key (service name),
 * and its value is a closure that returns an instance of the service. This setup allows for
 * dependency injection, ensuring services are instantiated only when needed.
 *
 * Reminder:
 *  - ServiceWiring is NOT a cache for arbitrary singletons.
 *
 * Services include various UI component builders, renderers, and utilities such as the
 * Mustache template engine and Localization for localization. These services are integral to
 * the Codex design system for generating reusable, standardized components.
 *
 * @category Infrastructure
 * @package  Codex\Infrastructure
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

use GuzzleHttp\Psr7\ServerRequest;
use Krinkle\Intuition\Intuition;
use MediaWiki\Context\RequestContext;
use Wikimedia\Codex\Builder\AccordionBuilder;
use Wikimedia\Codex\Builder\ButtonBuilder;
use Wikimedia\Codex\Builder\CardBuilder;
use Wikimedia\Codex\Builder\CheckboxBuilder;
use Wikimedia\Codex\Builder\FieldBuilder;
use Wikimedia\Codex\Builder\HtmlSnippetBuilder;
use Wikimedia\Codex\Builder\InfoChipBuilder;
use Wikimedia\Codex\Builder\LabelBuilder;
use Wikimedia\Codex\Builder\MessageBuilder;
use Wikimedia\Codex\Builder\OptionBuilder;
use Wikimedia\Codex\Builder\PagerBuilder;
use Wikimedia\Codex\Builder\ProgressBarBuilder;
use Wikimedia\Codex\Builder\RadioBuilder;
use Wikimedia\Codex\Builder\SelectBuilder;
use Wikimedia\Codex\Builder\TabBuilder;
use Wikimedia\Codex\Builder\TableBuilder;
use Wikimedia\Codex\Builder\TabsBuilder;
use Wikimedia\Codex\Builder\TextAreaBuilder;
use Wikimedia\Codex\Builder\TextInputBuilder;
use Wikimedia\Codex\Builder\ThumbnailBuilder;
use Wikimedia\Codex\Builder\ToggleSwitchBuilder;
use Wikimedia\Codex\Contract\ILocalizer;
use Wikimedia\Codex\Localization\IntuitionLocalization;
use Wikimedia\Codex\Localization\MediaWikiLocalization;
use Wikimedia\Codex\ParamValidator\ParamValidator;
use Wikimedia\Codex\ParamValidator\ParamValidatorCallbacks;
use Wikimedia\Codex\Parser\TemplateParser;
use Wikimedia\Codex\Renderer\AccordionRenderer;
use Wikimedia\Codex\Renderer\ButtonRenderer;
use Wikimedia\Codex\Renderer\CardRenderer;
use Wikimedia\Codex\Renderer\CheckboxRenderer;
use Wikimedia\Codex\Renderer\FieldRenderer;
use Wikimedia\Codex\Renderer\InfoChipRenderer;
use Wikimedia\Codex\Renderer\LabelRenderer;
use Wikimedia\Codex\Renderer\MessageRenderer;
use Wikimedia\Codex\Renderer\PagerRenderer;
use Wikimedia\Codex\Renderer\ProgressBarRenderer;
use Wikimedia\Codex\Renderer\RadioRenderer;
use Wikimedia\Codex\Renderer\SelectRenderer;
use Wikimedia\Codex\Renderer\TableRenderer;
use Wikimedia\Codex\Renderer\TabsRenderer;
use Wikimedia\Codex\Renderer\TextAreaRenderer;
use Wikimedia\Codex\Renderer\TextInputRenderer;
use Wikimedia\Codex\Renderer\ThumbnailRenderer;
use Wikimedia\Codex\Renderer\ToggleSwitchRenderer;
use Wikimedia\Codex\Utility\Sanitizer;
use Wikimedia\Services\ServiceContainer;

/** @phpcs-require-sorted-array */
return [

	'AccordionBuilder' => static function ( ServiceContainer $services ) {
		return new AccordionBuilder( $services->getService( 'AccordionRenderer' ) );
	},

	'AccordionRenderer' => static function ( ServiceContainer $services ) {
		return new AccordionRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'ButtonBuilder' => static function ( ServiceContainer $services ) {
		return new ButtonBuilder( $services->getService( 'ButtonRenderer' ) );
	},

	'ButtonRenderer' => static function ( ServiceContainer $services ) {
		return new ButtonRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'CardBuilder' => static function ( ServiceContainer $services ) {
		return new CardBuilder( $services->getService( 'CardRenderer' ) );
	},

	'CardRenderer' => static function ( ServiceContainer $services ) {
		return new CardRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'CheckboxBuilder' => static function ( ServiceContainer $services ) {
		return new CheckboxBuilder( $services->getService( 'CheckboxRenderer' ) );
	},

	'CheckboxRenderer' => static function ( ServiceContainer $services ) {
		return new CheckboxRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' )
		);
	},

	'FieldBuilder' => static function ( ServiceContainer $services ) {
		return new FieldBuilder( $services->getService( 'FieldRenderer' ) );
	},

	'FieldRenderer' => static function ( ServiceContainer $services ) {
		return new FieldRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'HtmlSnippetBuilder' => static function () {
		return new HtmlSnippetBuilder();
	},

	'InfoChipBuilder' => static function ( ServiceContainer $services ) {
		return new InfoChipBuilder( $services->getService( 'InfoChipRenderer' ) );
	},

	'InfoChipRenderer' => static function ( ServiceContainer $services ) {
		return new InfoChipRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'LabelBuilder' => static function ( ServiceContainer $services ) {
		return new LabelBuilder( $services->getService( 'LabelRenderer' ) );
	},

	'LabelRenderer' => static function ( ServiceContainer $services ) {
		return new LabelRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' )
		);
	},

	'Localization' => static function (): ILocalizer {
		if ( defined( 'MW_INSTALL_PATH' ) ) {
			$messageLocalizer = RequestContext::getMain();
			return new MediaWikiLocalization( $messageLocalizer );
		} else {
			$intuition = new Intuition( 'codex' );
			$intuition->registerDomain( 'codex', __DIR__ . '/../../i18n' );
			return new IntuitionLocalization( $intuition );
		}
	},

	'MessageBuilder' => static function ( ServiceContainer $services ) {
		return new MessageBuilder( $services->getService( 'MessageRenderer' ) );
	},

	'MessageRenderer' => static function ( ServiceContainer $services ) {
		return new MessageRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'OptionBuilder' => static function () {
		return new OptionBuilder();
	},

	'PagerBuilder' => static function ( ServiceContainer $services ) {
		return new PagerBuilder( $services->getService( 'PagerRenderer' ) );
	},

	'PagerRenderer' => static function ( ServiceContainer $services ) {
		return new PagerRenderer(
			$services->getService( 'Sanitizer' ),
			$services->getService( 'TemplateParser' ),
			$services->getService( 'Localization' ),
			$services->getService( 'ParamValidator' ),
			$services->getService( 'ParamValidatorCallbacks' )
		);
	},

	'ParamValidator' => static function ( ServiceContainer $services ): ParamValidator{
		return new ParamValidator( $services->getService( 'ParamValidatorCallbacks' ) );
	},

	'ParamValidatorCallbacks' => static function (): ParamValidatorCallbacks {
		$request = ServerRequest::fromGlobals();
		return new ParamValidatorCallbacks( $request->getQueryParams() );
	},

	'ProgressBarBuilder' => static function ( ServiceContainer $services ) {
		return new ProgressBarBuilder( $services->getService( 'ProgressBarRenderer' ) );
	},

	'ProgressBarRenderer' => static function ( ServiceContainer $services ) {
		return new ProgressBarRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'RadioBuilder' => static function ( ServiceContainer $services ) {
		return new RadioBuilder( $services->getService( 'RadioRenderer' ) );
	},

	'RadioRenderer' => static function ( ServiceContainer $services ) {
		return new RadioRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' )
		);
	},

	'Sanitizer' => static function () {
		return new Sanitizer();
	},

	'SelectBuilder' => static function ( ServiceContainer $services ) {
		return new SelectBuilder( $services->getService( 'SelectRenderer' ) );
	},

	'SelectRenderer' => static function ( ServiceContainer $services ) {
		return new SelectRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'TabBuilder' => static function () {
		return new TabBuilder();
	},

	'TableBuilder' => static function ( ServiceContainer $services ) {
		return new TableBuilder( $services->getService( 'TableRenderer' ) );
	},

	'TableRenderer' => static function ( ServiceContainer $services ) {
		return new TableRenderer(
			$services->getService( 'Sanitizer' ),
			$services->getService( 'TemplateParser' ),
			$services->getService( 'ParamValidator' ),
			$services->getService( 'ParamValidatorCallbacks' )
		);
	},

	'TabsBuilder' => static function ( ServiceContainer $services ) {
		return new TabsBuilder( $services->getService( 'TabsRenderer' ) );
	},

	'TabsRenderer' => static function ( ServiceContainer $services ) {
		return new TabsRenderer(
			$services->getService( 'Sanitizer' ),
			$services->getService( 'TemplateParser' ),
			$services->getService( 'ParamValidator' ),
			$services->getService( 'ParamValidatorCallbacks' )
		);
	},

	'TemplateParser' => static function ( ServiceContainer $services ) {
		$templatePath = __DIR__ . '/../../resources/templates';
		$localization = $services->getService( 'Localization' );

		return new TemplateParser( $templatePath, $localization );
	},

	'TextAreaBuilder' => static function ( ServiceContainer $services ) {
		return new TextAreaBuilder( $services->getService( 'TextAreaRenderer' ) );
	},

	'TextAreaRenderer' => static function ( ServiceContainer $services ) {
		return new TextAreaRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' )
		);
	},

	'TextInputBuilder' => static function ( ServiceContainer $services ) {
		return new TextInputBuilder( $services->getService( 'TextInputRenderer' ) );
	},

	'TextInputRenderer' => static function ( ServiceContainer $services ) {
		return new TextInputRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'ThumbnailBuilder' => static function ( ServiceContainer $services ) {
		return new ThumbnailBuilder( $services->getService( 'ThumbnailRenderer' ) );
	},

	'ThumbnailRenderer' => static function ( ServiceContainer $services ) {
		return new ThumbnailRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},

	'ToggleSwitchBuilder' => static function ( ServiceContainer $services ) {
		return new ToggleSwitchBuilder( $services->getService( 'ToggleSwitchRenderer' ) );
	},

	'ToggleSwitchRenderer' => static function ( ServiceContainer $services ) {
		return new ToggleSwitchRenderer(
			$services->getService( 'Sanitizer' ), $services->getService( 'TemplateParser' ),
		);
	},
];

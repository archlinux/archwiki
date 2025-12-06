<?php
/**
 * @file
 * @license https://opensource.org/licenses/Apache-2.0 Apache-2.0
 */

namespace Wikimedia\CSS\Sanitizer;

use Wikimedia\CSS\Grammar\Alternative;
use Wikimedia\CSS\Grammar\BlockMatcher;
use Wikimedia\CSS\Grammar\DelimMatcher;
use Wikimedia\CSS\Grammar\FunctionMatcher;
use Wikimedia\CSS\Grammar\Juxtaposition;
use Wikimedia\CSS\Grammar\KeywordMatcher;
use Wikimedia\CSS\Grammar\Matcher;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Grammar\Quantifier;
use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Grammar\UnorderedGroup;
use Wikimedia\CSS\Objects\Token;

/**
 * Sanitizes a Declaration representing a CSS style property
 * @note This intentionally doesn't support
 *  [cascading variables](https://www.w3.org/TR/css-variables/) since that
 *  seems impossible to securely sanitize.
 */
class StylePropertySanitizer extends PropertySanitizer {

	/** @var mixed[] */
	protected $cache = [];

	/**
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 */
	public function __construct( MatcherFactory $matcherFactory ) {
		parent::__construct( [], $matcherFactory->cssWideKeywords() );

		$this->addKnownProperties( [
			// https://www.w3.org/TR/2022/CR-css-cascade-4-20220113/#all-shorthand
			'all' => $matcherFactory->cssWideKeywords(),

			// https://www.w3.org/TR/2019/REC-pointerevents2-20190404/#the-touch-action-css-property
			'touch-action' => new Alternative( [
				new KeywordMatcher( [ 'auto', 'none', 'manipulation' ] ),
				UnorderedGroup::someOf( [
					new KeywordMatcher( 'pan-x' ),
					new KeywordMatcher( 'pan-y' ),
				] ),
			] ),

			// https://www.w3.org/TR/2023/WD-css-page-3-20230914/#using-named-pages
			'page' => $matcherFactory->ident(),
		] );
		$this->addKnownProperties( $this->css2( $matcherFactory ) );
		$this->addKnownProperties( $this->cssDisplay3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssPosition3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssColor3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssBackgrounds3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssImages3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssFonts3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssMulticol( $matcherFactory ) );
		$this->addKnownProperties( $this->cssOverflow4( $matcherFactory ) );
		$this->addKnownProperties( $this->cssUI4( $matcherFactory ) );
		$this->addKnownProperties( $this->cssCompositing1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssWritingModes4( $matcherFactory ) );
		$this->addKnownProperties( $this->cssTransitions( $matcherFactory ) );
		$this->addKnownProperties( $this->cssAnimations( $matcherFactory ) );
		$this->addKnownProperties( $this->cssFlexbox3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssTransforms2( $matcherFactory ) );
		$this->addKnownProperties( $this->cssText3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssTextDecor3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssAlign3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssBreak3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssGrid1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssFilter1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssShapes1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssMasking1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssSizing4( $matcherFactory ) );
		$this->addKnownProperties( $this->cssLogical1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssRuby1( $matcherFactory ) );
		$this->addKnownProperties( $this->cssLists3( $matcherFactory ) );
		$this->addKnownProperties( $this->cssScrollSnap1( $matcherFactory ) );
	}

	/**
	 * Properties from CSS 2.1
	 * @see https://www.w3.org/TR/2011/REC-CSS2-20110607/
	 * @note Omits properties that have been replaced by a CSS3 module
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function css2( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$none = new KeywordMatcher( 'none' );
		$auto = new KeywordMatcher( 'auto' );
		$autoLength = new Alternative( [ $auto, $matcherFactory->length() ] );
		$autoLengthPct = new Alternative( [ $auto, $matcherFactory->lengthPercentage() ] );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/box.html
		$props['margin-top'] = $autoLengthPct;
		$props['margin-bottom'] = $autoLengthPct;
		$props['margin-left'] = $autoLengthPct;
		$props['margin-right'] = $autoLengthPct;
		$props['margin'] = Quantifier::count( $autoLengthPct, 1, 4 );
		$props['padding-top'] = $matcherFactory->lengthPercentage();
		$props['padding-bottom'] = $matcherFactory->lengthPercentage();
		$props['padding-left'] = $matcherFactory->lengthPercentage();
		$props['padding-right'] = $matcherFactory->lengthPercentage();
		$props['padding'] = Quantifier::count( $matcherFactory->lengthPercentage(), 1, 4 );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/visuren.html
		// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#directional-keywords
		$props['z-index'] = new Alternative( [ $auto, $matcherFactory->integer() ] );
		$props['float'] = new KeywordMatcher( [ 'left', 'right', 'inline-start', 'inline-end', 'none' ] );
		$props['clear'] = new KeywordMatcher( [ 'none', 'left', 'right', 'both', 'inline-start', 'inline-end' ] );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/visudet.html
		$props['line-height'] = new Alternative( [
			new KeywordMatcher( 'normal' ),
			$matcherFactory->length(),
			$matcherFactory->numberPercentage(),
		] );
		$props['vertical-align'] = new Alternative( [
			new KeywordMatcher( [
				'baseline', 'sub', 'super', 'top', 'text-top', 'middle', 'bottom', 'text-bottom'
			] ),
			$matcherFactory->lengthPercentage(),
		] );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/visufx.html
		$props['clip'] = new Alternative( [
			$auto, new FunctionMatcher( 'rect', Quantifier::hash( $autoLength, 4, 4 ) ),
		] );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/generate.html
		$props['content'] = new Alternative( [
			new KeywordMatcher( [ 'normal', 'none' ] ),
			Quantifier::plus( new Alternative( [
				$matcherFactory->string(),
				// Replaces <url> per https://www.w3.org/TR/css-images-3/#placement
				$matcherFactory->image(),
				// Updated by https://www.w3.org/TR/2020/WD-css-lists-3-20201117/#counter-functions
				new FunctionMatcher( 'counter', new Juxtaposition( [
					$matcherFactory->ident(),
					Quantifier::optional( $matcherFactory->counterStyle() ),
				], true ) ),
				new FunctionMatcher( 'counters', new Juxtaposition( [
					$matcherFactory->ident(),
					$matcherFactory->string(),
					Quantifier::optional( $matcherFactory->counterStyle() ),
				], true ) ),
				new FunctionMatcher( 'attr', $matcherFactory->ident() ),
				new KeywordMatcher( [ 'open-quote', 'close-quote', 'no-open-quote', 'no-close-quote' ] ),
			] ) )
		] );
		$props['quotes'] = new Alternative( [
			$none, Quantifier::plus( new Juxtaposition( [
				$matcherFactory->string(), $matcherFactory->string()
			] ) ),
		] );

		// https://www.w3.org/TR/2011/REC-CSS2-20110607/tables.html
		$props['caption-side'] = new KeywordMatcher( [ 'top', 'bottom' ] );
		$props['table-layout'] = new KeywordMatcher( [ 'auto', 'fixed' ] );
		$props['border-collapse'] = new KeywordMatcher( [ 'collapse', 'separate' ] );
		$props['border-spacing'] = Quantifier::count( $matcherFactory->length(), 1, 2 );
		$props['empty-cells'] = new KeywordMatcher( [ 'show', 'hide' ] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Display Module Level 3
	 * @see https://www.w3.org/TR/2023/CR-css-display-3-20230330/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssDisplay3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$displayOutside = new KeywordMatcher( [ 'block', 'inline', 'run-in' ] );

		$props['display'] = new Alternative( [
			// <display-outside> || <display-inside>
			UnorderedGroup::someOf( [
				$displayOutside,
				new KeywordMatcher( [ 'flow', 'flow-root', 'table', 'flex', 'grid', 'ruby' ] ),
			] ),
			// <display-listitem>
			UnorderedGroup::allOf( [
				Quantifier::optional( $displayOutside ),
				Quantifier::optional( new KeywordMatcher( [ 'flow', 'flow-root' ] ) ),
				new KeywordMatcher( 'list-item' ),
			] ),
			new KeywordMatcher( [
				// <display-internal>
				'table-row-group', 'table-header-group', 'table-footer-group', 'table-row', 'table-cell',
				'table-column-group', 'table-column', 'table-caption', 'ruby-base', 'ruby-text',
				'ruby-base-container', 'ruby-text-container',
				// <display-box>
				'contents', 'none',
				// <display-legacy>
				'inline-block', 'inline-table', 'inline-flex', 'inline-grid',
			] ),
		] );

		$props['visibility'] = new KeywordMatcher( [ 'visible', 'hidden', 'collapse' ] );

		$props['order'] = $matcherFactory->integer();

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Positioned Layout Module Level 3
	 * @see https://www.w3.org/TR/2025/WD-css-position-3-20250311/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssPosition3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$auto = new KeywordMatcher( 'auto' );
		$autoLengthPct = new Alternative( [ $auto, $matcherFactory->lengthPercentage() ] );

		$props = [];

		$props['position'] = new KeywordMatcher( [
			'static', 'relative', 'absolute', 'sticky', 'fixed'
		] );
		$props['top'] = $autoLengthPct;
		$props['right'] = $autoLengthPct;
		$props['bottom'] = $autoLengthPct;
		$props['left'] = $autoLengthPct;
		$props['inset-block-start'] = $autoLengthPct;
		$props['inset-inline-start'] = $autoLengthPct;
		$props['inset-block-end'] = $autoLengthPct;
		$props['inset-inline-end'] = $autoLengthPct;
		$props['inset-block'] = Quantifier::count( $autoLengthPct, 1, 2 );
		$props['inset-inline'] = $props['inset-block'];
		$props['inset'] = Quantifier::count( $autoLengthPct, 1, 4 );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Color Module Level 3
	 * @see https://www.w3.org/TR/2018/REC-css-color-3-20180619/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssColor3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$props['color'] = $matcherFactory->color();
		$props['opacity'] = $matcherFactory->number();

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Data types for backgrounds
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return array
	 */
	protected function backgroundTypes( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$types = [];

		$types['bgrepeat'] = new Alternative( [
			new KeywordMatcher( [ 'repeat-x', 'repeat-y' ] ),
			Quantifier::count( new KeywordMatcher( [ 'repeat', 'space', 'round', 'no-repeat' ] ), 1, 2 ),
		] );
		$types['bgsize'] = new Alternative( [
			Quantifier::count( new Alternative( [
				$matcherFactory->lengthPercentage(),
				new KeywordMatcher( 'auto' )
			] ), 1, 2 ),
			new KeywordMatcher( [ 'cover', 'contain' ] )
		] );
		$types['boxKeywords'] = [ 'border-box', 'padding-box', 'content-box' ];

		$this->cache[__METHOD__] = $types;
		return $types;
	}

	/**
	 * Keywords for the <box> production and its subsets
	 * @see https://www.w3.org/TR/2024/WD-css-box-4-20240804/#keywords
	 * @return array<string,string[]>
	 */
	protected function boxEdgeKeywords() {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd
		$kws = [];
		$kws['visual-box'] = [ 'content-box', 'padding-box', 'border-box' ];
		$kws['layout-box'] = [ ...$kws['visual-box'], 'margin-box' ];
		$kws['paint-box'] = [ ...$kws['visual-box'], 'fill-box', 'stroke-box' ];
		$kws['coord-box'] = [ ...$kws['paint-box'], 'view-box' ];
		$kws['box'] = [ 'content-box', 'padding-box', 'border-box', 'margin-box', 'fill-box',
			'stroke-box', 'view-box' ];
		return $kws;
	}

	/**
	 * Properties for CSS Backgrounds and Borders Module Level 3
	 * @see https://www.w3.org/TR/2024/CRD-css-backgrounds-3-20240311/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssBackgrounds3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$types = $this->backgroundTypes( $matcherFactory );
		$slash = new DelimMatcher( '/' );
		$bgimage = new Alternative( [ new KeywordMatcher( 'none' ), $matcherFactory->image() ] );
		$bgrepeat = $types['bgrepeat'];
		$bgattach = new KeywordMatcher( [ 'scroll', 'fixed', 'local' ] );
		$position = $matcherFactory->bgPosition();
		$boxKeywords = $this->boxEdgeKeywords();
		$visualBox = new KeywordMatcher( $boxKeywords['visual-box'] );
		$bgsize = $types['bgsize'];
		$bglayer = UnorderedGroup::someOf( [
			$bgimage,
			new Juxtaposition( [
				$position, Quantifier::optional( new Juxtaposition( [ $slash, $bgsize ] ) )
			] ),
			$bgrepeat,
			$bgattach,
			$visualBox,
			$visualBox,
		] );
		$finalBglayer = UnorderedGroup::someOf( [
			$bgimage,
			new Juxtaposition( [
				$position, Quantifier::optional( new Juxtaposition( [ $slash, $bgsize ] ) )
			] ),
			$bgrepeat,
			$bgattach,
			$visualBox,
			$visualBox,
			$matcherFactory->color(),
		] );

		$props['background-color'] = $matcherFactory->color();
		$props['background-image'] = Quantifier::hash( $bgimage );
		$props['background-repeat'] = Quantifier::hash( $bgrepeat );
		$props['background-attachment'] = Quantifier::hash( $bgattach );
		$props['background-position'] = Quantifier::hash( $position );
		$props['background-clip'] = Quantifier::hash( $visualBox );
		$props['background-origin'] = $props['background-clip'];
		$props['background-size'] = Quantifier::hash( $bgsize );
		$props['background'] = new Juxtaposition(
			[ Quantifier::hash( $bglayer, 0, INF ), $finalBglayer ], true
		);

		$lineStyle = new KeywordMatcher( [
			'none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'
		] );
		$lineWidth = new Alternative( [
			new KeywordMatcher( [ 'thin', 'medium', 'thick' ] ), $matcherFactory->length(),
		] );
		$borderCombo = UnorderedGroup::someOf( [ $lineWidth, $lineStyle, $matcherFactory->color() ] );
		$radius = Quantifier::count( $matcherFactory->lengthPercentage(), 1, 2 );
		$radius4 = Quantifier::count( $matcherFactory->lengthPercentage(), 1, 4 );

		$props['border-top-color'] = $matcherFactory->color();
		$props['border-right-color'] = $matcherFactory->color();
		$props['border-bottom-color'] = $matcherFactory->color();
		$props['border-left-color'] = $matcherFactory->color();
		// Because this property allows concatenation of color values, don't
		// allow var(...) expressions here out of an abundance of caution.
		$props['border-color'] = Quantifier::count( $matcherFactory->safeColor(), 1, 4 );
		$props['border-top-style'] = $lineStyle;
		$props['border-right-style'] = $lineStyle;
		$props['border-bottom-style'] = $lineStyle;
		$props['border-left-style'] = $lineStyle;
		$props['border-style'] = Quantifier::count( $lineStyle, 1, 4 );
		$props['border-top-width'] = $lineWidth;
		$props['border-right-width'] = $lineWidth;
		$props['border-bottom-width'] = $lineWidth;
		$props['border-left-width'] = $lineWidth;
		$props['border-width'] = Quantifier::count( $lineWidth, 1, 4 );
		$props['border-top'] = $borderCombo;
		$props['border-right'] = $borderCombo;
		$props['border-bottom'] = $borderCombo;
		$props['border-left'] = $borderCombo;
		$props['border'] = $borderCombo;
		$props['border-top-left-radius'] = $radius;
		$props['border-top-right-radius'] = $radius;
		$props['border-bottom-left-radius'] = $radius;
		$props['border-bottom-right-radius'] = $radius;
		$props['border-radius'] = new Juxtaposition( [
			$radius4, Quantifier::optional( new Juxtaposition( [ $slash, $radius4 ] ) )
		] );
		$props['border-image-source'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			$matcherFactory->image()
		] );
		$props['border-image-slice'] = UnorderedGroup::allOf( [
			Quantifier::count( $matcherFactory->numberPercentage(), 1, 4 ),
			Quantifier::optional( new KeywordMatcher( 'fill' ) ),
		] );
		$props['border-image-width'] = Quantifier::count( new Alternative( [
			$matcherFactory->length(),
			$matcherFactory->percentage(),
			$matcherFactory->number(),
			new KeywordMatcher( 'auto' ),
		] ), 1, 4 );
		$props['border-image-outset'] = Quantifier::count( new Alternative( [
			$matcherFactory->length(),
			$matcherFactory->number(),
		] ), 1, 4 );
		$props['border-image-repeat'] = Quantifier::count( new KeywordMatcher( [
			'stretch', 'repeat', 'round', 'space'
		] ), 1, 2 );
		$props['border-image'] = UnorderedGroup::someOf( [
			$props['border-image-source'],
			new Juxtaposition( [
				$props['border-image-slice'],
				Quantifier::optional( new Alternative( [
					new Juxtaposition( [ $slash, $props['border-image-width'] ] ),
					new Juxtaposition( [
						$slash,
						Quantifier::optional( $props['border-image-width'] ),
						$slash,
						$props['border-image-outset']
					] )
				] ) )
			] ),
			$props['border-image-repeat']
		] );

		$props['box-shadow'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::hash( UnorderedGroup::allOf( [
				Quantifier::optional( $matcherFactory->color() ),
				Quantifier::count( $matcherFactory->length(), 2, 4 ),
				Quantifier::optional( new KeywordMatcher( 'inset' ) ),
			] ) )
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Images Module Level 3
	 * @see https://www.w3.org/TR/2023/CRD-css-images-3-20231218/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssImages3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$props['object-fit'] = new KeywordMatcher( [ 'fill', 'contain', 'cover', 'none', 'scale-down' ] );
		$props['object-position'] = $matcherFactory->position();

		// Allow bare zero per legacy note at https://www.w3.org/TR/2024/WD-css-values-4-20240312/#angles
		$a = new Alternative( [
			$matcherFactory->zero(),
			$matcherFactory->angle(),
		] );
		$props['image-orientation'] = new Alternative( [
			new KeywordMatcher( [ 'from-image', 'none' ] ),
			UnorderedGroup::someOf( [
				$a,
				new KeywordMatcher( [ 'flip' ] ),
			] ),
		] );

		$props['image-rendering'] = new KeywordMatcher( [
			'auto', 'smooth', 'high-quality', 'crisp-edges', 'pixelated'
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Fonts Module Level 3
	 * @see https://www.w3.org/TR/2018/REC-css-fonts-3-20180920/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssFonts3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$css2 = $this->css2( $matcherFactory );
		$props = [];

		$matchData = FontFaceAtRuleSanitizer::fontMatchData( $matcherFactory );

		// Note: <generic-family> is syntactically a subset of <family-name>,
		// so no point in separately listing it.
		$props['font-family'] = Quantifier::hash( $matchData['familyName'] );
		$props['font-weight'] = new Alternative( [
			new KeywordMatcher( [ 'normal', 'bold', 'bolder', 'lighter' ] ),
			$matchData['numWeight'],
		] );
		$props['font-stretch'] = $matchData['font-stretch'];
		$props['font-style'] = $matchData['font-style'];
		$props['font-size'] = new Alternative( [
			new KeywordMatcher( [
				'xx-small', 'x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'larger', 'smaller'
			] ),
			$matcherFactory->lengthPercentage(),
		] );
		$props['font-size-adjust'] = new Alternative( [
			new KeywordMatcher( 'none' ), $matcherFactory->number()
		] );
		$props['font'] = new Alternative( [
			new Juxtaposition( [
				Quantifier::optional( UnorderedGroup::someOf( [
					$props['font-style'],
					new KeywordMatcher( [ 'normal', 'small-caps' ] ),
					$props['font-weight'],
					$props['font-stretch'],
				] ) ),
				$props['font-size'],
				Quantifier::optional( new Juxtaposition( [
					new DelimMatcher( '/' ),
					$css2['line-height'],
				] ) ),
				$props['font-family'],
			] ),
			new KeywordMatcher( [ 'caption', 'icon', 'menu', 'message-box', 'small-caption', 'status-bar' ] )
		] );
		$props['font-synthesis'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( 'weight' ),
				new KeywordMatcher( 'style' ),
			] )
		] );
		$props['font-kerning'] = new KeywordMatcher( [ 'auto', 'normal', 'none' ] );
		$props['font-variant-ligatures'] = new Alternative( [
			new KeywordMatcher( [ 'normal', 'none' ] ),
			UnorderedGroup::someOf( $matchData['ligatures'] )
		] );
		$props['font-variant-position'] = new KeywordMatcher(
			array_merge( [ 'normal' ], $matchData['positionKeywords'] )
		);
		$props['font-variant-caps'] = new KeywordMatcher(
			array_merge( [ 'normal' ], $matchData['capsKeywords'] )
		);
		$props['font-variant-numeric'] = new Alternative( [
			new KeywordMatcher( 'normal' ),
			UnorderedGroup::someOf( $matchData['numeric'] )
		] );
		$props['font-variant-east-asian'] = new Alternative( [
			new KeywordMatcher( 'normal' ),
			UnorderedGroup::someOf( $matchData['eastAsian'] )
		] );
		$props['font-variant'] = $matchData['font-variant'];
		$props['font-feature-settings'] = $matchData['font-feature-settings'];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Multi-column Layout Module
	 * @see https://www.w3.org/TR/2024/CR-css-multicol-1-20240516/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssMulticol( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$borders = $this->cssBackgrounds3( $matcherFactory );
		$props = [];

		$auto = new KeywordMatcher( 'auto' );

		$props['column-width'] = new Alternative( array_merge(
			[ $matcherFactory->length(), $auto ],
			// Additional values from https://www.w3.org/TR/2021/WD-css-sizing-3-20211217/
			// Note! This adds support for a now invalid `column-width: min-width`.
			// Should probably be removed once new CSS specifications are released.
			$this->getSizingAdditions3( $matcherFactory )
		) );
		$props['column-count'] = new Alternative( [ $matcherFactory->integer(), $auto ] );
		$props['columns'] = UnorderedGroup::someOf( [ $props['column-width'], $props['column-count'] ] );
		// Copy these from similar items in the Border module
		$props['column-rule-color'] = $borders['border-right-color'];
		$props['column-rule-style'] = $borders['border-right-style'];
		$props['column-rule-width'] = $borders['border-right-width'];
		$props['column-rule'] = $borders['border-right'];
		$props['column-span'] = new KeywordMatcher( [ 'none', 'all' ] );
		$props['column-fill'] = new KeywordMatcher( [ 'auto', 'balance', 'balance-all' ] );

		// Copy these from cssBreak3(), the duplication is allowed as long as
		// they're the identical Matcher object.
		$breaks = $this->cssBreak3( $matcherFactory );
		$props['break-before'] = $breaks['break-before'];
		$props['break-after'] = $breaks['break-after'];
		$props['break-inside'] = $breaks['break-inside'];

		// And one from cssAlign3
		$props['column-gap'] = $this->cssAlign3( $matcherFactory )['column-gap'];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Overflow Module Level 3
	 * @see https://www.w3.org/TR/2023/WD-css-overflow-3-20230329/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssOverflow3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$overflow = new KeywordMatcher( [ 'visible', 'hidden', 'clip', 'scroll', 'auto' ] );
		$props['overflow'] = Quantifier::count( $overflow, 1, 2 );
		$props['overflow-x'] = $overflow;
		$props['overflow-y'] = $overflow;
		$props['overflow-inline'] = $overflow;
		$props['overflow-block'] = $overflow;
		$props['overflow-clip-margin'] = UnorderedGroup::someOf( [
			new KeywordMatcher( [ 'content-box', 'padding-box', 'border-box' ] ),
			$matcherFactory->length()
		] );

		$props['text-overflow'] = new KeywordMatcher( [ 'clip', 'ellipsis' ] );

		$props['scroll-behavior'] = new KeywordMatcher( [ 'auto', 'smooth' ] );
		$props['scrollbar-gutter'] = new Alternative( [
			new KeywordMatcher( 'auto' ),
			UnorderedGroup::allOf( [
				new KeywordMatcher( 'stable' ),
				Quantifier::optional( new KeywordMatcher( 'both-edges' ) )
			] )
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Overflow Module Level 4
	 * @see https://www.w3.org/TR/2023/WD-css-overflow-4-20230321/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssOverflow4( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd
		$props = $this->cssOverflow3( $matcherFactory );
		$props['-webkit-line-clamp'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			$matcherFactory->integer()
		] );
		$props['block-ellipsis'] = new Alternative( [
			new KeywordMatcher( [ 'none', 'auto' ] ),
			$matcherFactory->string()
		] );
		$props['continue'] = new KeywordMatcher( [ 'auto', 'discard' ] );
		$props['line-clamp'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			new Juxtaposition( [
				$matcherFactory->integer(),
				Quantifier::optional( $props['block-ellipsis'] ),
			] ),
		] );
		$props['max-lines'] = new Alternative( [
			new KeywordMatcher( 'none' ), $matcherFactory->integer()
		] );
		$clipMargin = $props['overflow-clip-margin'];
		$props['overflow-clip-margin-block'] = $clipMargin;
		$props['overflow-clip-margin-block-end'] = $clipMargin;
		$props['overflow-clip-margin-block-start'] = $clipMargin;
		$props['overflow-clip-margin-bottom'] = $clipMargin;
		$props['overflow-clip-margin-inline'] = $clipMargin;
		$props['overflow-clip-margin-inline-end'] = $clipMargin;
		$props['overflow-clip-margin-inline-start'] = $clipMargin;
		$props['overflow-clip-margin-inline-left'] = $clipMargin;
		$props['overflow-clip-margin-left'] = $clipMargin;
		$props['overflow-clip-margin-right'] = $clipMargin;
		$props['overflow-clip-margin-top'] = $clipMargin;
		$props['text-overflow'] = Quantifier::count(
			new Alternative( [
				new KeywordMatcher( [ 'clip', 'ellipsis' ] ),
				$matcherFactory->string(),
				new KeywordMatcher( 'fade' ),
				new FunctionMatcher(
					'fade',
					new Alternative( [ $matcherFactory->length(), $matcherFactory->percentage() ] )
				)
			] ),
			1, 2
		);

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Basic User Interface Module Level 4
	 * @see https://www.w3.org/TR/2018/REC-css-ui-3-20180621/
	 * @see https://www.w3.org/TR/2021/WD-css-ui-4-20210316/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssUI4( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$border = $this->cssBackgrounds3( $matcherFactory );
		$props = [];

		// Copy these from similar border properties
		$props['outline-width'] = $border['border-top-width'];
		$props['outline-style'] = new Alternative( [
			new KeywordMatcher( 'auto' ), $border['border-top-style']
		] );
		$props['outline-color'] = new Alternative( [
			new KeywordMatcher( 'invert' ), $matcherFactory->color()
		] );
		$props['outline'] = UnorderedGroup::someOf( [
			$props['outline-width'], $props['outline-style'], $props['outline-color']
		] );
		$props['outline-offset'] = $matcherFactory->length();
		$props['resize'] = new KeywordMatcher( [
			'none', 'both', 'horizontal', 'vertical', 'block', 'inline',
		] );
		$props['cursor'] = new Juxtaposition( [
			Quantifier::star( new Juxtaposition( [
				$matcherFactory->image(),
				Quantifier::optional( new Juxtaposition( [
					$matcherFactory->number(), $matcherFactory->number()
				] ) ),
				$matcherFactory->comma(),
			] ) ),
			new KeywordMatcher( [
				'auto', 'default', 'none', 'context-menu', 'help', 'pointer', 'progress', 'wait', 'cell',
				'crosshair', 'text', 'vertical-text', 'alias', 'copy', 'move', 'no-drop', 'not-allowed', 'grab',
				'grabbing', 'e-resize', 'n-resize', 'ne-resize', 'nw-resize', 's-resize', 'se-resize',
				'sw-resize', 'w-resize', 'ew-resize', 'ns-resize', 'nesw-resize', 'nwse-resize', 'col-resize',
				'row-resize', 'all-scroll', 'zoom-in', 'zoom-out',
			] ),
		] );
		$props['caret-color'] = new Alternative( [
			new KeywordMatcher( 'auto' ), $matcherFactory->color()
		] );
		$props['caret-shape'] = new KeywordMatcher( [ 'auto', 'bar', 'block', 'underscore' ] );
		$props['caret'] = UnorderedGroup::someOf( [ $props['caret-color'], $props['caret-shape'] ] );
		$props['nav-up'] = new Alternative( [
			new KeywordMatcher( 'auto' ),
			new Juxtaposition( [
				$matcherFactory->cssID(),
				Quantifier::optional( new Alternative( [
					new KeywordMatcher( [ 'current', 'root' ] ),
					$matcherFactory->string(),
				] ) )
			] )
		] );
		$props['nav-right'] = $props['nav-up'];
		$props['nav-down'] = $props['nav-up'];
		$props['nav-left'] = $props['nav-up'];

		$props['user-select'] = new KeywordMatcher( [ 'auto', 'text', 'none', 'contain', 'all' ] );
		// Seems potentially useful enough to let the prefixed versions work.
		$props['-moz-user-select'] = $props['user-select'];
		$props['-ms-user-select'] = $props['user-select'];
		$props['-webkit-user-select'] = $props['user-select'];

		$props['accent-color'] = new Alternative( [
			new KeywordMatcher( 'auto' ),
			$matcherFactory->color(),
		] );

		$props['appearance'] = new KeywordMatcher( [
			'none', 'auto', 'button', 'textfield', 'menulist-button',
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Compositing and Blending Level 1
	 * @see https://www.w3.org/TR/2024/CRD-compositing-1-20240321/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssCompositing1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$props['mix-blend-mode'] = new KeywordMatcher( [
			'normal', 'darken', 'multiply', 'color-burn', 'lighten', 'screen', 'color-dodge', 'overlay',
			'soft-light', 'hard-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'
		] );
		$props['isolation'] = new KeywordMatcher( [ 'auto', 'isolate' ] );

		// The linked spec incorrectly has this without the hash, despite the
		// textual description and examples showing it as such. The draft has it fixed.
		$props['background-blend-mode'] = Quantifier::hash( $props['mix-blend-mode'] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Writing Modes Level 4
	 * @see https://www.w3.org/TR/2019/CR-css-writing-modes-4-20190730/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssWritingModes4( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$props['direction'] = new KeywordMatcher( [ 'ltr', 'rtl' ] );
		$props['unicode-bidi'] = new KeywordMatcher( [
			'normal', 'embed', 'isolate', 'bidi-override', 'isolate-override', 'plaintext'
		] );
		$props['writing-mode'] = new KeywordMatcher( [
			'horizontal-tb', 'vertical-rl', 'vertical-lr', 'sideways-rl', 'sideways-lr',
		] );
		$props['text-orientation'] = new KeywordMatcher( [ 'mixed', 'upright', 'sideways' ] );
		$props['text-combine-upright'] = new Alternative( [
			new KeywordMatcher( [ 'none', 'all' ] ),
			new Juxtaposition( [
				new KeywordMatcher( [ 'digits' ] ),
				Quantifier::optional( $matcherFactory->integer() ),
			] ),
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Transitions
	 * @see https://www.w3.org/TR/2018/WD-css-transitions-1-20181011/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssTransitions( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$property = new Alternative( [
			new KeywordMatcher( [ 'all' ] ),
			$matcherFactory->customIdent( [ 'none' ] ),
		] );
		$none = new KeywordMatcher( 'none' );
		$singleEasingFunction = $matcherFactory->cssSingleEasingFunction();

		$props['transition-property'] = new Alternative( [
			$none, Quantifier::hash( $property )
		] );
		$props['transition-duration'] = Quantifier::hash( $matcherFactory->time() );
		$props['transition-timing-function'] = Quantifier::hash( $singleEasingFunction );
		$props['transition-delay'] = Quantifier::hash( $matcherFactory->time() );
		$props['transition'] = Quantifier::hash( UnorderedGroup::someOf( [
			new Alternative( [ $none, $property ] ),
			$matcherFactory->time(),
			$singleEasingFunction,
			$matcherFactory->time(),
		] ) );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Animations
	 * @see https://www.w3.org/TR/2023/WD-css-animations-1-20230302/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssAnimations( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$name = new Alternative( [
			new KeywordMatcher( [ 'none' ] ),
			$matcherFactory->customIdent( [ 'none' ] ),
			$matcherFactory->string(),
		] );
		$singleEasingFunction = $matcherFactory->cssSingleEasingFunction();
		$count = new Alternative( [
			new KeywordMatcher( 'infinite' ),
			$matcherFactory->number()
		] );
		$direction = new KeywordMatcher( [ 'normal', 'reverse', 'alternate', 'alternate-reverse' ] );
		$playState = new KeywordMatcher( [ 'running', 'paused' ] );
		$fillMode = new KeywordMatcher( [ 'none', 'forwards', 'backwards', 'both' ] );

		$props['animation-name'] = Quantifier::hash( $name );
		$props['animation-duration'] = Quantifier::hash( $matcherFactory->time() );
		$props['animation-timing-function'] = Quantifier::hash( $singleEasingFunction );
		$props['animation-iteration-count'] = Quantifier::hash( $count );
		$props['animation-direction'] = Quantifier::hash( $direction );
		$props['animation-play-state'] = Quantifier::hash( $playState );
		$props['animation-delay'] = Quantifier::hash( $matcherFactory->time() );
		$props['animation-fill-mode'] = Quantifier::hash( $fillMode );
		$props['animation'] = Quantifier::hash( UnorderedGroup::someOf( [
			$matcherFactory->time(),
			$singleEasingFunction,
			$matcherFactory->time(),
			$count,
			$direction,
			$fillMode,
			$playState,
			$name,
		] ) );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Flexible Box Layout Module Level 1
	 * @see https://www.w3.org/TR/2018/CR-css-flexbox-1-20181119/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssFlexbox3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$props['flex-direction'] = new KeywordMatcher( [
			'row', 'row-reverse', 'column', 'column-reverse'
		] );
		$props['flex-wrap'] = new KeywordMatcher( [ 'nowrap', 'wrap', 'wrap-reverse' ] );
		$props['flex-flow'] = UnorderedGroup::someOf( [ $props['flex-direction'], $props['flex-wrap'] ] );
		$props['flex-grow'] = $matcherFactory->number();
		$props['flex-shrink'] = $matcherFactory->number();
		$props['flex-basis'] = new Alternative( [
			new KeywordMatcher( [ 'content' ] ),
			$this->cssSizing4( $matcherFactory )['width']
		] );
		$props['flex'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				new Juxtaposition( [ $props['flex-grow'], Quantifier::optional( $props['flex-shrink'] ) ] ),
				$props['flex-basis'],
			] )
		] );

		// The alignment module supersedes the ones in flexbox. Copying is ok as long as
		// it's the identical object.
		$align = $this->cssAlign3( $matcherFactory );
		$props['justify-content'] = $align['justify-content'];
		$props['align-items'] = $align['align-items'];
		$props['align-self'] = $align['align-self'];
		$props['align-content'] = $align['align-content'];

		// 'order' was copied into display-3 in CR 2023-03-30
		// Removed from flexbox in the ED as of 2025-03-10, it can be removed
		// here once we update our flexbox version.
		$props['order'] = $this->cssDisplay3( $matcherFactory )['order'];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Get a matcher for any transform function for a given level of the
	 * Transforms module.
	 *
	 * @see https://www.w3.org/TR/2019/CR-css-transforms-1-20190214/
	 * @see https://www.w3.org/TR/2021/WD-css-transforms-2-20211109/
	 *
	 * @param MatcherFactory $matcherFactory
	 * @param int $level
	 * @return Alternative
	 */
	protected function transformFunc( MatcherFactory $matcherFactory, int $level ) {
		$a = $matcherFactory->angle();
		$az = new Alternative( [
			$matcherFactory->zero(),
			$a,
		] );
		$n = $matcherFactory->number();
		$l = $matcherFactory->length();
		$lp = $matcherFactory->lengthPercentage();
		$np = new Alternative( [ $n, $matcherFactory->percentage() ] );

		$level1 = [
			'matrix' => Quantifier::hash( $n, 6, 6 ),
			'translate' => Quantifier::hash( $lp, 1, 2 ),
			'translateX' => $lp,
			'translateY' => $lp,
			'scale' => Quantifier::hash( $n, 1, 2 ),
			'scaleX' => $n,
			'scaleY' => $n,
			'rotate' => $az,
			'skew' => Quantifier::hash( $az, 1, 2 ),
			'skewX' => $az,
			'skewY' => $az,
		];

		$level2 = [
			'scale' => Quantifier::hash( $np, 1, 2 ),
			'scaleX' => $np,
			'scaleY' => $np,
			'matrix3d' => Quantifier::hash( $n, 16, 16 ),
			'translate3d' => new Juxtaposition( [ $lp, $lp, $l ], true ),
			'translateZ' => $l,
			'scale3d' => Quantifier::hash( $np, 3, 3 ),
			'scaleZ' => $np,
			'rotate3d' => new Juxtaposition( [ $n, $n, $n, $az ], true ),
			'rotateX' => $az,
			'rotateY' => $az,
			'rotateZ' => $az,
			'perspective' => new Alternative( [ $l, new KeywordMatcher( 'none' ) ] ),
		];

		if ( $level === 1 ) {
			$funcs = $level1;
		} else {
			$funcs = $level2 + $level1;
		}
		$funcMatchers = [];
		foreach ( $funcs as $name => $contents ) {
			$funcMatchers[] = new FunctionMatcher( $name, $contents );
		}
		return new Alternative( $funcMatchers );
	}

	/**
	 * Properties for CSS Transforms Module Level 1
	 *
	 * @see https://www.w3.org/TR/2019/CR-css-transforms-1-20190214/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssTransforms1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$l = $matcherFactory->length();
		$ol = Quantifier::optional( $l );
		$lp = $matcherFactory->lengthPercentage();
		$center = new KeywordMatcher( 'center' );
		$leftRight = new KeywordMatcher( [ 'left', 'right' ] );
		$topBottom = new KeywordMatcher( [ 'top', 'bottom' ] );

		$props['transform'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::plus( $this->transformFunc( $matcherFactory, 1 ) )
		] );

		$props['transform-origin'] = new Alternative( [
			new Alternative( [ $center, $leftRight, $topBottom, $lp ] ),
			new Juxtaposition( [
				new Alternative( [ $center, $leftRight, $lp ] ),
				new Alternative( [ $center, $topBottom, $lp ] ),
				$ol
			] ),
			new Juxtaposition( [
				UnorderedGroup::allOf( [
					new Alternative( [ $center, $leftRight ] ),
					new Alternative( [ $center, $topBottom ] ),
				] ),
				$ol,
			] )
		] );
		$props['transform-box'] = new KeywordMatcher( [
			'content-box', 'border-box', 'fill-box', 'stroke-box', 'view-box'
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Transforms Module Levels 1 and 2
	 *
	 * @see https://www.w3.org/TR/2019/CR-css-transforms-1-20190214/
	 * @see https://www.w3.org/TR/2021/WD-css-transforms-2-20211109/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssTransforms2( MatcherFactory $matcherFactory ) {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$none = new KeywordMatcher( 'none' );

			$this->cache[__METHOD__] = [
				'transform' => new Alternative( [
					new KeywordMatcher( 'none' ),
					Quantifier::plus( $this->transformFunc( $matcherFactory, 2 ) )
				] ),
				'backface-visibility' => new KeywordMatcher( [ 'visible', 'hidden' ] ),
				'perspective' => new Alternative( [
					$none,
					$matcherFactory->length()
				] ),
				'perspective-origin' => $matcherFactory->position(),
				'rotate' => new Alternative( [
					$none,
					$matcherFactory->angle(),
					UnorderedGroup::allOf( [
						new Alternative( [
							new KeywordMatcher( [ 'x', 'y', 'z' ] ),
							Quantifier::count( $matcherFactory->number(), 3, 3 )
						] ),
						$matcherFactory->angle()
					] )
				] ),
				'scale' => new Alternative( [
					$none,
					Quantifier::count(
						new Alternative( [
							$matcherFactory->number(),
							$matcherFactory->percentage()
						] ),
						1, 3
					)
				] ),
				'transform-style' => new KeywordMatcher( [ 'flat', 'preserve-3d' ] ),
				'translate' => new Alternative( [
					$none,
					new Juxtaposition( [
						$matcherFactory->lengthPercentage(),
						Quantifier::optional(
							new Juxtaposition( [
								$matcherFactory->lengthPercentage(),
								Quantifier::optional( $matcherFactory->length() )
							] )
						)
					] )
				] )
			] + $this->cssTransforms1( $matcherFactory );
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Properties for CSS Text Module Level 3
	 * @see https://www.w3.org/TR/2024/CRD-css-text-3-20240930/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssText3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$props['text-transform'] = new Alternative( [
			new KeywordMatcher( [ 'none' ] ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( [ 'capitalize', 'uppercase', 'lowercase', 'full-width' ] ),
				new KeywordMatcher( [ 'full-width' ] ),
				new KeywordMatcher( [ 'full-size-kana' ] ),
			] ),
		] );
		$props['white-space'] = new KeywordMatcher( [
			'normal', 'pre', 'nowrap', 'pre-wrap', 'break-spaces', 'pre-line'
		] );
		$props['tab-size'] = new Alternative( [ $matcherFactory->number(), $matcherFactory->length() ] );
		$props['line-break'] = new KeywordMatcher( [ 'auto', 'loose', 'normal', 'strict', 'anywhere' ] );
		$props['word-break'] = new KeywordMatcher( [ 'normal', 'keep-all', 'break-all', 'break-word' ] );
		$props['hyphens'] = new KeywordMatcher( [ 'none', 'manual', 'auto' ] );
		$props['word-wrap'] = new KeywordMatcher( [ 'normal', 'break-word', 'anywhere' ] );
		$props['overflow-wrap'] = $props['word-wrap'];
		$props['text-align'] = new KeywordMatcher( [
			'start', 'end', 'left', 'right', 'center', 'justify', 'match-parent', 'justify-all'
		] );
		$props['text-align-all'] = new KeywordMatcher( [
			'start', 'end', 'left', 'right', 'center', 'justify', 'match-parent'
		] );
		$props['text-align-last'] = new KeywordMatcher( [
			'auto', 'start', 'end', 'left', 'right', 'center', 'justify', 'match-parent'
		] );
		$props['text-justify'] = new KeywordMatcher( [
			'auto', 'none', 'inter-word', 'inter-character'
		] );
		$props['word-spacing'] = new Alternative( [
			new KeywordMatcher( 'normal' ),
			$matcherFactory->length()
		] );
		$props['letter-spacing'] = new Alternative( [
			new KeywordMatcher( 'normal' ),
			$matcherFactory->length()
		] );
		$props['text-indent'] = UnorderedGroup::allOf( [
			$matcherFactory->lengthPercentage(),
			Quantifier::optional( new KeywordMatcher( 'hanging' ) ),
			Quantifier::optional( new KeywordMatcher( 'each-line' ) ),
		] );
		$props['hanging-punctuation'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( 'first' ),
				new KeywordMatcher( [ 'force-end', 'allow-end' ] ),
				new KeywordMatcher( 'last' ),
			] )
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Text Decoration Module Level 3
	 * @see https://www.w3.org/TR/2022/CRD-css-text-decor-3-20220505/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssTextDecor3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];

		$props['text-decoration-line'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( 'underline' ),
				new KeywordMatcher( 'overline' ),
				new KeywordMatcher( 'line-through' ),
				// new KeywordMatcher( 'blink' ), // NOOO!!!
			] )
		] );
		$props['text-decoration-color'] = $matcherFactory->color();
		$props['text-decoration-style'] = new KeywordMatcher( [
			'solid', 'double', 'dotted', 'dashed', 'wavy'
		] );
		$props['text-decoration'] = UnorderedGroup::someOf( [
			$props['text-decoration-line'],
			$props['text-decoration-style'],
			$props['text-decoration-color'],
		] );
		$props['text-underline-position'] = new Alternative( [
			new KeywordMatcher( 'auto' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( 'under' ),
				new KeywordMatcher( [ 'left', 'right' ] ),
			] )
		] );
		$props['text-emphasis-style'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( [ 'filled', 'open' ] ),
				new KeywordMatcher( [ 'dot', 'circle', 'double-circle', 'triangle', 'sesame' ] )
			] ),
			$matcherFactory->string(),
		] );
		$props['text-emphasis-color'] = $matcherFactory->color();
		$props['text-emphasis'] = UnorderedGroup::someOf( [
			$props['text-emphasis-style'],
			$props['text-emphasis-color'],
		] );
		$props['text-emphasis-position'] = UnorderedGroup::allOf( [
			new KeywordMatcher( [ 'over', 'under' ] ),
			Quantifier::optional( new KeywordMatcher( [ 'right', 'left' ] ) ),
		] );
		$props['text-shadow'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::hash( UnorderedGroup::allOf( [
				Quantifier::count( $matcherFactory->length(), 2, 3 ),
				Quantifier::optional( $matcherFactory->color() ),
			] ) )
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Box Alignment Module Level 3
	 * @see https://www.w3.org/TR/2025/WD-css-align-3-20250311/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssAlign3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$normal = new KeywordMatcher( 'normal' );
		$normalStretch = new KeywordMatcher( [ 'normal', 'stretch' ] );
		$autoNormalStretch = new KeywordMatcher( [ 'auto', 'normal', 'stretch' ] );
		$overflowPosition = Quantifier::optional( new KeywordMatcher( [ 'safe', 'unsafe' ] ) );
		$baselinePosition = UnorderedGroup::allOf( [
			Quantifier::optional( new KeywordMatcher( [ 'first', 'last' ] ) ),
			new KeywordMatcher( 'baseline' )
		] );
		$contentDistribution = new KeywordMatcher( [
			'space-between', 'space-around', 'space-evenly', 'stretch'
		] );
		$overflowAndSelfPosition = new Juxtaposition( [
			$overflowPosition,
			new KeywordMatcher( [
				'center', 'start', 'end', 'self-start', 'self-end', 'flex-start', 'flex-end',
			] ),
		] );
		$overflowAndSelfPositionLR = new Juxtaposition( [
			$overflowPosition,
			new KeywordMatcher( [
				'center', 'start', 'end', 'self-start', 'self-end', 'flex-start', 'flex-end', 'left', 'right',
			] ),
		] );
		$overflowAndContentPos = new Juxtaposition( [
			$overflowPosition,
			new KeywordMatcher( [ 'center', 'start', 'end', 'flex-start', 'flex-end' ] ),
		] );
		$overflowAndContentPosLR = new Juxtaposition( [
			$overflowPosition,
			new KeywordMatcher( [ 'center', 'start', 'end', 'flex-start', 'flex-end', 'left', 'right' ] ),
		] );

		$props['align-content'] = new Alternative( [
			$normal,
			$baselinePosition,
			$contentDistribution,
			$overflowAndContentPos,
		] );
		$props['justify-content'] = new Alternative( [
			$normal,
			$contentDistribution,
			$overflowAndContentPosLR,
		] );
		$props['place-content'] = new Juxtaposition( [
			$props['align-content'], Quantifier::optional( $props['justify-content'] )
		] );
		$props['align-self'] = new Alternative( [
			$autoNormalStretch,
			$baselinePosition,
			$overflowAndSelfPosition,
		] );
		$props['justify-self'] = new Alternative( [
			$autoNormalStretch,
			$baselinePosition,
			$overflowAndSelfPositionLR,
		] );
		$props['place-self'] = new Juxtaposition( [
			$props['align-self'], Quantifier::optional( $props['justify-self'] )
		] );
		$props['align-items'] = new Alternative( [
			$normalStretch,
			$baselinePosition,
			$overflowAndSelfPosition,
		] );
		$props['justify-items'] = new Alternative( [
			$normalStretch,
			$baselinePosition,
			$overflowAndSelfPositionLR,
			new KeywordMatcher( 'legacy' ),
			UnorderedGroup::allOf( [
				new KeywordMatcher( 'legacy' ),
				new KeywordMatcher( [ 'left', 'right', 'center' ] ),
			] ),
		] );
		$props['place-items'] = new Juxtaposition( [
			$props['align-items'], Quantifier::optional( $props['justify-items'] )
		] );
		$props['row-gap'] = new Alternative( [ $normal, $matcherFactory->lengthPercentage() ] );
		$props['column-gap'] = $props['row-gap'];
		$props['gap'] = new Juxtaposition( [
			$props['row-gap'], Quantifier::optional( $props['column-gap'] )
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Fragmentation Module Level 3
	 * @see https://www.w3.org/TR/2018/CR-css-break-3-20181204/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssBreak3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$props['break-before'] = new KeywordMatcher( [
			'auto', 'avoid', 'avoid-page', 'page', 'left', 'right', 'recto', 'verso', 'avoid-column',
			'column', 'avoid-region', 'region'
		] );
		$props['break-after'] = $props['break-before'];
		$props['break-inside'] = new KeywordMatcher( [
			'auto', 'avoid', 'avoid-page', 'avoid-column', 'avoid-region'
		] );
		$props['orphans'] = $matcherFactory->integer();
		$props['widows'] = $matcherFactory->integer();
		$props['box-decoration-break'] = new KeywordMatcher( [ 'slice', 'clone' ] );
		$props['page-break-before'] = new KeywordMatcher( [
			'auto', 'always', 'avoid', 'left', 'right'
		] );
		$props['page-break-after'] = $props['page-break-before'];
		$props['page-break-inside'] = new KeywordMatcher( [ 'auto', 'avoid' ] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Grid Layout Module Level 1
	 * @see https://www.w3.org/TR/2025/CRD-css-grid-1-20250326/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssGrid1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$comma = $matcherFactory->comma();
		$slash = new DelimMatcher( '/' );
		$customIdent = $matcherFactory->customIdent( [ 'span' ] );
		$lineNamesO = Quantifier::optional( new BlockMatcher(
			Token::T_LEFT_BRACKET, Quantifier::star( $customIdent )
		) );
		$trackBreadth = new Alternative( [
			$matcherFactory->lengthPercentage(),
			new TokenMatcher( Token::T_DIMENSION, static function ( Token $t ) {
				return $t->value() >= 0 && !strcasecmp( $t->unit(), 'fr' );
			} ),
			new KeywordMatcher( [ 'min-content', 'max-content', 'auto' ] )
		] );
		$inflexibleBreadth = new Alternative( [
			$matcherFactory->lengthPercentage(),
			new KeywordMatcher( [ 'min-content', 'max-content', 'auto' ] )
		] );
		$fixedBreadth = $matcherFactory->lengthPercentage();
		$trackSize = new Alternative( [
			$trackBreadth,
			new FunctionMatcher( 'minmax',
				new Juxtaposition( [ $inflexibleBreadth, $trackBreadth ], true )
			),
			new FunctionMatcher( 'fit-content', $matcherFactory->lengthPercentage() )
		] );
		$fixedSize = new Alternative( [
			$fixedBreadth,
			new FunctionMatcher( 'minmax', new Juxtaposition( [ $fixedBreadth, $trackBreadth ], true ) ),
			new FunctionMatcher( 'minmax',
				new Juxtaposition( [ $inflexibleBreadth, $fixedBreadth ], true )
			),
		] );
		$trackRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			$matcherFactory->integer(),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $trackSize ] ) ),
			$lineNamesO
		] ) );
		$autoRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			new KeywordMatcher( [ 'auto-fill', 'auto-fit' ] ),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $fixedSize ] ) ),
			$lineNamesO
		] ) );
		$fixedRepeat = new FunctionMatcher( 'repeat', new Juxtaposition( [
			$matcherFactory->integer(),
			$comma,
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $fixedSize ] ) ),
			$lineNamesO
		] ) );
		$trackList = new Juxtaposition( [
			Quantifier::plus( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $trackSize, $trackRepeat ] )
			] ) ),
			$lineNamesO
		] );
		$autoTrackList = new Juxtaposition( [
			Quantifier::star( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $fixedSize, $fixedRepeat ] )
			] ) ),
			$lineNamesO,
			$autoRepeat,
			Quantifier::star( new Juxtaposition( [
				$lineNamesO, new Alternative( [ $fixedSize, $fixedRepeat ] )
			] ) ),
			$lineNamesO,
		] );
		$explicitTrackList = new Juxtaposition( [
			Quantifier::plus( new Juxtaposition( [ $lineNamesO, $trackSize ] ) ),
			$lineNamesO
		] );
		$autoDense = UnorderedGroup::allOf( [
			new KeywordMatcher( 'auto-flow' ),
			Quantifier::optional( new KeywordMatcher( 'dense' ) )
		] );

		$props['grid-template-columns'] = new Alternative( [
			new KeywordMatcher( 'none' ), $trackList, $autoTrackList
		] );
		$props['grid-template-rows'] = $props['grid-template-columns'];
		$props['grid-template-areas'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::plus( $matcherFactory->string() ),
		] );
		$props['grid-template'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			new Juxtaposition( [ $props['grid-template-rows'], $slash, $props['grid-template-columns'] ] ),
			new Juxtaposition( [
				Quantifier::plus( new Juxtaposition( [
					$lineNamesO, $matcherFactory->string(), Quantifier::optional( $trackSize ), $lineNamesO
				] ) ),
				Quantifier::optional( new Juxtaposition( [ $slash, $explicitTrackList ] ) ),
			] )
		] );
		$props['grid-auto-columns'] = Quantifier::plus( $trackSize );
		$props['grid-auto-rows'] = $props['grid-auto-columns'];
		$props['grid-auto-flow'] = UnorderedGroup::someOf( [
			new KeywordMatcher( [ 'row', 'column' ] ),
			new KeywordMatcher( 'dense' )
		] );
		$props['grid'] = new Alternative( [
			$props['grid-template'],
			new Juxtaposition( [
				$props['grid-template-rows'],
				$slash,
				$autoDense,
				Quantifier::optional( $props['grid-auto-columns'] ),
			] ),
			new Juxtaposition( [
				$autoDense,
				Quantifier::optional( $props['grid-auto-rows'] ),
				$slash,
				$props['grid-template-columns'],
			] )
		] );

		$gridLine = new Alternative( [
			new KeywordMatcher( 'auto' ),
			$customIdent,
			UnorderedGroup::allOf( [
				$matcherFactory->integer(),
				Quantifier::optional( $customIdent )
			] ),
			UnorderedGroup::allOf( [
				new KeywordMatcher( 'span' ),
				UnorderedGroup::someOf( [
					$matcherFactory->integer(),
					$customIdent,
				] )
			] )
		] );
		$props['grid-row-start'] = $gridLine;
		$props['grid-column-start'] = $gridLine;
		$props['grid-row-end'] = $gridLine;
		$props['grid-column-end'] = $gridLine;
		$props['grid-row'] = new Juxtaposition( [
			$gridLine, Quantifier::optional( new Juxtaposition( [ $slash, $gridLine ] ) )
		] );
		$props['grid-column'] = $props['grid-row'];
		$props['grid-area'] = new Juxtaposition( [
			$gridLine, Quantifier::count( new Juxtaposition( [ $slash, $gridLine ] ), 0, 3 )
		] );

		// Replaced by the alignment module
		$align = $this->cssAlign3( $matcherFactory );
		$props['grid-row-gap'] = $align['row-gap'];
		$props['grid-column-gap'] = $align['column-gap'];
		$props['grid-gap'] = $align['gap'];

		// Also, these are copied from the alignment module. Copying is ok as long as
		// it's the identical object.
		$props['row-gap'] = $align['row-gap'];
		$props['column-gap'] = $align['column-gap'];
		$props['gap'] = $align['gap'];
		$props['justify-self'] = $align['justify-self'];
		$props['justify-items'] = $align['justify-items'];
		$props['align-self'] = $align['align-self'];
		$props['align-items'] = $align['align-items'];
		$props['justify-content'] = $align['justify-content'];
		$props['align-content'] = $align['align-content'];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Filter Effects Module Level 1
	 * @see https://www.w3.org/TR/2018/WD-filter-effects-1-20181218/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssFilter1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$onp = Quantifier::optional( $matcherFactory->numberPercentage() );

		$props = [];

		$props['filter'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			Quantifier::plus( new Alternative( [
				new FunctionMatcher( 'blur', Quantifier::optional( $matcherFactory->length() ) ),
				new FunctionMatcher( 'brightness', $onp ),
				new FunctionMatcher( 'contrast', $onp ),
				new FunctionMatcher( 'drop-shadow', UnorderedGroup::allOf( [
					Quantifier::optional( $matcherFactory->color() ),
					Quantifier::count( $matcherFactory->length(), 2, 3 ),
				] ) ),
				new FunctionMatcher( 'grayscale', $onp ),
				new FunctionMatcher( 'hue-rotate', Quantifier::optional( new Alternative( [
					$matcherFactory->zero(),
					$matcherFactory->angle(),
				] ) ) ),
				new FunctionMatcher( 'invert', $onp ),
				new FunctionMatcher( 'opacity', $onp ),
				new FunctionMatcher( 'saturate', $onp ),
				new FunctionMatcher( 'sepia', $onp ),
				$matcherFactory->url( 'svg' ),
			] ) )
		] );
		$props['flood-color'] = $matcherFactory->color();
		$props['flood-opacity'] = $matcherFactory->numberPercentage();
		$props['color-interpolation-filters'] = new KeywordMatcher( [ 'auto', 'sRGB', 'linearRGB' ] );
		$props['lighting-color'] = $matcherFactory->color();

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Shapes and masking share these basic shapes
	 * @see https://www.w3.org/TR/2022/CRD-css-shapes-1-20221115/#basic-shape-functions
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher
	 */
	protected function basicShapes( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$border = $this->cssBackgrounds3( $matcherFactory );
		$lp = $matcherFactory->lengthPercentage();
		$sr = new Alternative( [
			$lp,
			new KeywordMatcher( [ 'closest-side', 'farthest-side' ] ),
		] );
		$optRound = Quantifier::optional( new Juxtaposition( [
			new KeywordMatcher( 'round' ), $border['border-radius']
		] ) );
		$fillRule = new KeywordMatcher( [ 'nonzero', 'evenodd' ] );

		$basicShape = new Alternative( [
			new FunctionMatcher( 'inset', new Juxtaposition( [
				Quantifier::count( $lp, 1, 4 ),
				$optRound,
			] ) ),
			new FunctionMatcher( 'xywh', new Juxtaposition( [
				Quantifier::count( $lp, 2, 2 ),
				Quantifier::count( $lp, 2, 2 ),
				$optRound,
			] ) ),
			new FunctionMatcher( 'rect', new Juxtaposition( [
				Quantifier::count(
					new Alternative( [ $lp, new KeywordMatcher( 'auto' ) ] ),
					4, 4
				),
				$optRound,
			] ) ),
			new FunctionMatcher( 'circle', new Juxtaposition( [
				Quantifier::optional( $sr ),
				Quantifier::optional( new Juxtaposition( [
					new KeywordMatcher( 'at' ), $matcherFactory->position()
				] ) )
			] ) ),
			new FunctionMatcher( 'ellipse', new Juxtaposition( [
				Quantifier::optional( Quantifier::count( $sr, 2, 2 ) ),
				Quantifier::optional( new Juxtaposition( [
					new KeywordMatcher( 'at' ), $matcherFactory->position()
				] ) )
			] ) ),
			new FunctionMatcher( 'polygon', new Juxtaposition( [
				Quantifier::optional( $fillRule ),
				Quantifier::hash( Quantifier::count( $lp, 2, 2 ) ),
			], true ) ),
			new FunctionMatcher( 'path', new Juxtaposition( [
				Quantifier::optional( $fillRule ),
				$matcherFactory->string(),
			], true ) ),
		] );

		$this->cache[__METHOD__] = $basicShape;
		return $basicShape;
	}

	/**
	 * Properties for CSS Shapes Module Level 1
	 * @see https://www.w3.org/TR/2022/CRD-css-shapes-1-20221115/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssShapes1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$shapeBoxKW = $this->backgroundTypes( $matcherFactory )['boxKeywords'];
		$shapeBoxKW[] = 'margin-box';

		$props = [];

		$props['shape-outside'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			UnorderedGroup::someOf( [
				$this->basicShapes( $matcherFactory ),
				new KeywordMatcher( $shapeBoxKW ),
			] ),
			$matcherFactory->url( 'image' ),
		] );
		$props['shape-image-threshold'] = $matcherFactory->numberPercentage();
		$props['shape-margin'] = $matcherFactory->lengthPercentage();

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Masking Module Level 1
	 * @see https://www.w3.org/TR/2021/CRD-css-masking-1-20210805/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssMasking1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$slash = new DelimMatcher( '/' );
		$bgtypes = $this->backgroundTypes( $matcherFactory );
		$bg = $this->cssBackgrounds3( $matcherFactory );

		// <geometry-box> = <shape-box> | fill-box | stroke-box | view-box
		// <shape-box> = <box> | margin-box
		// The changelog says margin-box was removed from the mask-origin and
		// mask-clip properties, but the grammar still allows it, so we will
		// allow it. <shape-box> in Shapes 1 refers to <box> in Backgrounds 3,
		// but it's not there anymore, <box> has moved to Box 4 which is where
		// we're getting it from. <box> now allows all four of these extra
		// keywords so this complexity is redundant. It's just a <box>.
		$geometryBoxKeywords = $this->boxEdgeKeywords()['box'];
		$geometryBox = new KeywordMatcher( $geometryBoxKeywords );
		$maskRef = new Alternative( [
			new KeywordMatcher( 'none' ),
			$matcherFactory->image(),
			$matcherFactory->url( 'svg' ),
		] );
		$maskMode = new KeywordMatcher( [ 'alpha', 'luminance', 'match-source' ] );
		$maskClip = new KeywordMatcher( array_merge( $geometryBoxKeywords, [ 'no-clip' ] ) );
		$maskComposite = new KeywordMatcher( [ 'add', 'subtract', 'intersect', 'exclude' ] );

		$props = [];

		$props['clip-path'] = new Alternative( [
			$matcherFactory->url( 'svg' ),
			UnorderedGroup::someOf( [
				$this->basicShapes( $matcherFactory ),
				$geometryBox,
			] ),
			new KeywordMatcher( 'none' ),
		] );
		$props['clip-rule'] = new KeywordMatcher( [ 'nonzero', 'evenodd' ] );
		$props['mask-image'] = Quantifier::hash( $maskRef );
		$props['mask-mode'] = Quantifier::hash( $maskMode );
		$props['mask-repeat'] = $bg['background-repeat'];
		$props['mask-position'] = Quantifier::hash( $matcherFactory->position() );
		$props['mask-clip'] = Quantifier::hash( $maskClip );
		$props['mask-origin'] = Quantifier::hash( $geometryBox );
		$props['mask-size'] = $bg['background-size'];
		$props['mask-composite'] = Quantifier::hash( $maskComposite );
		$props['mask'] = Quantifier::hash( UnorderedGroup::someOf( [
			$maskRef,
			new Juxtaposition( [
				$matcherFactory->position(),
				Quantifier::optional( new Juxtaposition( [ $slash, $bgtypes['bgsize'] ] ) ),
			] ),
			$bgtypes['bgrepeat'],
			$geometryBox,
			$maskClip,
			$maskComposite,
			$maskMode
		] ) );
		$props['mask-border-source'] = new Alternative( [
			new KeywordMatcher( 'none' ),
			$matcherFactory->image(),
		] );
		$props['mask-border-mode'] = new KeywordMatcher( [ 'luminance', 'alpha' ] );
		// Different from border-image-slice, sigh
		$props['mask-border-slice'] = new Juxtaposition( [
			Quantifier::count( $matcherFactory->numberPercentage(), 1, 4 ),
			Quantifier::optional( new KeywordMatcher( 'fill' ) ),
		] );
		$props['mask-border-width'] = $bg['border-image-width'];
		$props['mask-border-outset'] = $bg['border-image-outset'];
		$props['mask-border-repeat'] = $bg['border-image-repeat'];
		$props['mask-border'] = UnorderedGroup::someOf( [
			$props['mask-border-source'],
			new Juxtaposition( [
				$props['mask-border-slice'],
				Quantifier::optional( new Juxtaposition( [
					$slash,
					Quantifier::optional( $props['mask-border-width'] ),
					Quantifier::optional( new Juxtaposition( [
						$slash,
						$props['mask-border-outset'],
					] ) ),
				] ) ),
			] ),
			$props['mask-border-repeat'],
			$props['mask-border-mode'],
		] );
		$props['mask-type'] = new KeywordMatcher( [ 'luminance', 'alpha' ] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Additional keywords and functions from CSS Box Sizing Level 3
	 * @see https://www.w3.org/TR/2021/WD-css-sizing-3-20211217/#column-sizing
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array of matchers
	 */
	protected function getSizingAdditions3( MatcherFactory $matcherFactory ) {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$lengthPct = $matcherFactory->lengthPercentage();
			$this->cache[__METHOD__] = [
				new KeywordMatcher( [
					'max-content', 'min-content',
				] ),
				new FunctionMatcher( 'fit-content', $lengthPct ),
				// Browser-prefixed versions of the function, needed by Firefox as of January 2020
				new FunctionMatcher( '-moz-fit-content', $lengthPct ),
			];
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Additional keywords and functions from CSS Box Sizing Level 3 and 4
	 * @see https://www.w3.org/TR/css-sizing-3/#sizing-values
	 * @see https://www.w3.org/TR/css-sizing-4/#sizing-values
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array of matchers
	 */
	protected function getSizingAdditions( MatcherFactory $matcherFactory ) {
		if ( !isset( $this->cache[__METHOD__] ) ) {
			$lengthPct = $matcherFactory->lengthPercentage();
			$this->cache[__METHOD__] = [
				new KeywordMatcher( [
					'max-content', 'min-content', 'stretch', 'fit-content', 'contain'
				] ),
				// fit-content() as a function https://developer.mozilla.org/en-US/docs/Web/CSS/fit-content_function
				new FunctionMatcher( 'fit-content', $lengthPct ),
				// Prefixed for FF v3-v93 (until 2021) https://caniuse.com/?search=fit-content
				new FunctionMatcher( '-moz-fit-content', $lengthPct ),
			];
		}
		return $this->cache[__METHOD__];
	}

	/**
	 * Properties for CSS Box Sizing Level 3 and 4
	 * @see https://www.w3.org/TR/css-sizing-3/#sizing-values
	 * @see https://www.w3.org/TR/css-sizing-4/#sizing-values
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssSizing4( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$none = new KeywordMatcher( 'none' );
		$auto = new KeywordMatcher( 'auto' );
		$lengthPct = $matcherFactory->lengthPercentage();
		$sizingValues = array_merge( [ $lengthPct ], $this->getSizingAdditions( $matcherFactory ) );

		$props = [];
		$props['width'] = new Alternative( array_merge( [ $auto ], $sizingValues ) );
		$props['min-width'] = $props['width'];
		$props['max-width'] = new Alternative( array_merge( [ $none ], $sizingValues ) );
		$props['height'] = $props['width'];
		$props['min-height'] = $props['min-width'];
		$props['max-height'] = $props['max-width'];

		$props['box-sizing'] = new KeywordMatcher( [ 'content-box', 'border-box' ] );
		// https://developer.mozilla.org/en-US/docs/Web/CSS/aspect-ratio
		// auto || <ratio>
		$props['aspect-ratio'] = UnorderedGroup::someOf( [ $auto, $matcherFactory->ratio() ] );
		// https://developer.mozilla.org/en-US/docs/Web/CSS/contain-intrinsic-size
		// auto? [ none | <length> ]
		$containIntrinsic = new Juxtaposition( [
			Quantifier::optional( $auto ),
			new Alternative( [
				$none,
				$lengthPct,
			] ),
		] );
		$props['contain-intrinsic-width'] = $containIntrinsic;
		$props['contain-intrinsic-height'] = $containIntrinsic;
		$props['contain-intrinsic-block-size'] = $containIntrinsic;
		$props['contain-intrinsic-inline-size'] = $containIntrinsic;
		$props['contain-intrinsic-size'] = Quantifier::count( $containIntrinsic, 1, 2 );
		// https://drafts.csswg.org/css-sizing-4/#intrinsic-contribution-override
		// legacy | zero-if-scroll || zero-if-extrinsic
		$props['min-intrinsic-sizing'] = new Alternative( [
			new KeywordMatcher( 'legacy' ),
			UnorderedGroup::someOf( [
				new KeywordMatcher( 'zero-if-scroll' ),
				new KeywordMatcher( 'zero-if-extrinsic' ),
			] ),
		] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Logical 1
	 * @see https://www.w3.org/TR/2018/WD-css-logical-1-20180827/
	 * @param MatcherFactory $matcherFactory Factory for Matchers
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssLogical1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$cssSizing4 = $this->cssSizing4( $matcherFactory );
		$css2 = $this->css2( $matcherFactory );
		$cssBorderBackground3 = $this->cssBackgrounds3( $matcherFactory );
		$borderCombo = UnorderedGroup::someOf( [
			$cssBorderBackground3['border-top-width'],
			$cssBorderBackground3['border-top-style'],
			$matcherFactory->color(),
		] );

		$props = [
			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#dimension-properties
			'block-size' => $cssSizing4['width'],
			'inline-size' => $cssSizing4['width'],
			'min-block-size' => $cssSizing4['min-width'],
			'min-inline-size' => $cssSizing4['min-width'],
			'max-block-size' => $cssSizing4['max-width'],
			'max-inline-size' => $cssSizing4['max-width'],

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#margin-properties
			'margin-block-start' => $css2['margin-top'],
			'margin-block-end' => $css2['margin-top'],
			'margin-inline-start' => $css2['margin-top'],
			'margin-inline-end' => $css2['margin-top'],
			'margin-block' => Quantifier::count( $css2['margin-top'], 1, 2 ),
			'margin-inline' => Quantifier::count( $css2['margin-top'], 1, 2 ),

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#inset-properties
			// Superseded by Position 3

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#padding-properties
			'padding-block-start' => $css2['padding-top'],
			'padding-block-end' => $css2['padding-top'],
			'padding-inline-start' => $css2['padding-top'],
			'padding-inline-end' => $css2['padding-top'],
			'padding-block' => Quantifier::count( $css2['padding-top'], 1, 2 ),
			'padding-inline' => Quantifier::count( $css2['padding-top'], 1, 2 ),

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#border-width
			'border-block-start-width' => $cssBorderBackground3['border-top-width'],
			'border-block-end-width' => $cssBorderBackground3['border-top-width'],
			'border-inline-start-width' => $cssBorderBackground3['border-top-width'],
			'border-inline-end-width' => $cssBorderBackground3['border-top-width'],
			'border-block-width' => Quantifier::count( $cssBorderBackground3['border-top-width'], 1, 2 ),
			'border-inline-width' => Quantifier::count( $cssBorderBackground3['border-top-width'], 1, 2 ),

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#border-style
			'border-block-start-style' => $cssBorderBackground3['border-top-style'],
			'border-block-end-style' => $cssBorderBackground3['border-top-style'],
			'border-inline-start-style' => $cssBorderBackground3['border-top-style'],
			'border-inline-end-style' => $cssBorderBackground3['border-top-style'],
			'border-block-style' => Quantifier::count( $cssBorderBackground3['border-top-style'], 1, 2 ),
			'border-inline-style' => Quantifier::count( $cssBorderBackground3['border-top-style'], 1, 2 ),

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#border-color
			'border-block-start-color' => $cssBorderBackground3['border-top-color'],
			'border-block-end-color' => $cssBorderBackground3['border-top-color'],
			'border-inline-start-color' => $cssBorderBackground3['border-top-color'],
			'border-inline-end-color' => $cssBorderBackground3['border-top-color'],
			'border-block-color' => Quantifier::count( $cssBorderBackground3['border-top-color'], 1, 2 ),
			'border-inline-color' => Quantifier::count( $cssBorderBackground3['border-top-color'], 1, 2 ),

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#border-shorthands
			'border-block-start' => $borderCombo,
			'border-block-end' => $borderCombo,
			'border-inline-start' => $borderCombo,
			'border-inline-end' => $borderCombo,
			// both are equivalent to 'border-block-start' per the spec
			'border-block' => $borderCombo,
			'border-inline' => $borderCombo,

			// https://www.w3.org/TR/2018/WD-css-logical-1-20180827/#border-radius-shorthands
			'border-start-start-radius' => $cssBorderBackground3['border-top-left-radius'],
			'border-start-end-radius' => $cssBorderBackground3['border-top-left-radius'],
			'border-end-start-radius' => $cssBorderBackground3['border-top-left-radius'],
			'border-end-end-radius' => $cssBorderBackground3['border-top-left-radius'],
		];

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Ruby Annotation Layout Module Level 1
	 * @see https://www.w3.org/TR/2022/WD-css-ruby-1-20221231/
	 * @param MatcherFactory $matcherFactory
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssRuby1( $matcherFactory ) {
		return $this->cache[__METHOD__] ??= [
			'ruby-position' => new Alternative( [
				UnorderedGroup::someOf( [
					new KeywordMatcher( 'alternate' ),
					new KeywordMatcher( [ 'over', 'under' ] ),
				] ),
				new KeywordMatcher( [ 'inter-character' ] )
			] ),
			'ruby-merge' => new KeywordMatcher( [ 'separate', 'merge', 'auto' ] ),
			'ruby-align' => new KeywordMatcher( [
				'start', 'center', 'space-between', 'space-around'
			] ),
			'ruby-overhang' => new KeywordMatcher( [ 'auto', 'none' ] ),
		];
	}

	/**
	 * CSS Lists and Counters Module Level 3
	 * @see https://www.w3.org/TR/2020/WD-css-lists-3-20201117/
	 *
	 * @param MatcherFactory $matcherFactory
	 * @return Matcher[]
	 */
	protected function cssLists3( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd
		$none = new KeywordMatcher( 'none' );
		$props = [];

		$props['counter-increment'] = $props['counter-reset'] = $props['counter-set'] =
			new Alternative( [
				Quantifier::plus( new Juxtaposition( [
					$matcherFactory->customIdent( [ 'none' ] ),
					Quantifier::optional( $matcherFactory->integer() )
				] ) ),
				$none
			] );

		$props['list-style-image'] = new Alternative( [
			$matcherFactory->image(),
			$none
		] );

		$props['list-style-position'] = new KeywordMatcher( [ 'inside', 'outside' ] );

		$props['list-style-type'] = new Alternative( [
			$matcherFactory->counterStyle(),
			$matcherFactory->string(),
			$none
		] );

		$props['list-style'] = UnorderedGroup::someOf( [
			$props['list-style-position'], $props['list-style-image'], $props['list-style-type']
		] );

		$props['marker-side'] = new KeywordMatcher( [ 'match-self', 'match-parent' ] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}

	/**
	 * Properties for CSS Scroll Snap Module Level 1
	 * @see https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/
	 * @param MatcherFactory $matcherFactory
	 * @return Matcher[] Array mapping declaration names (lowercase) to Matchers for the values
	 */
	protected function cssScrollSnap1( MatcherFactory $matcherFactory ) {
		// @codeCoverageIgnoreStart
		if ( isset( $this->cache[__METHOD__] ) ) {
			return $this->cache[__METHOD__];
		}
		// @codeCoverageIgnoreEnd

		$props = [];
		$none = new KeywordMatcher( 'none' );
		$auto = new KeywordMatcher( 'auto' );
		$length = $matcherFactory->length();
		$lp = $matcherFactory->lengthPercentage();

		// https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/#scroll-snap-type
		$props['scroll-snap-type'] = new Alternative( [
			$none,
			new Juxtaposition( [
				new KeywordMatcher( [ 'x', 'y', 'block', 'inline', 'both' ] ),
				Quantifier::optional( new KeywordMatcher( [ 'mandatory', 'proximity' ] ) ),
			] ),
		] );

		// https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/#scroll-padding
		$props['scroll-padding'] = Quantifier::count( new Alternative( [ $auto, $lp ] ), 1, 4 );

		$props['scroll-padding-top'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-right'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-bottom'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-left'] = new Alternative( [ $auto, $lp ] );

		$props['scroll-padding-inline'] = Quantifier::count( new Alternative( [ $auto, $lp ] ), 1, 2 );
		$props['scroll-padding-inline-start'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-inline-end'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-block'] = Quantifier::count( new Alternative( [ $auto, $lp ] ), 1, 2 );
		$props['scroll-padding-block-start'] = new Alternative( [ $auto, $lp ] );
		$props['scroll-padding-block-end'] = new Alternative( [ $auto, $lp ] );

		// https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/#scroll-margin
		$props['scroll-margin'] = Quantifier::count( $length, 1, 4 );

		$props['scroll-margin-top'] = $length;
		$props['scroll-margin-right'] = $length;
		$props['scroll-margin-bottom'] = $length;
		$props['scroll-margin-left'] = $length;

		$props['scroll-margin-inline'] = Quantifier::count( $length, 1, 2 );
		$props['scroll-margin-inline-start'] = $length;
		$props['scroll-margin-inline-end'] = $length;
		$props['scroll-margin-block'] = Quantifier::count( $length, 1, 2 );
		$props['scroll-margin-block-start'] = $length;
		$props['scroll-margin-block-end'] = $length;

		// https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/#scroll-snap-align
		$props['scroll-snap-align'] = Quantifier::count( new KeywordMatcher( [ 'none', 'start', 'end', 'center' ] ),
			1, 2 );

		// https://www.w3.org/TR/2021/CR-css-scroll-snap-1-20210311/#scroll-snap-stop
		$props['scroll-snap-stop'] = new KeywordMatcher( [ 'normal', 'always' ] );

		$this->cache[__METHOD__] = $props;
		return $props;
	}
}

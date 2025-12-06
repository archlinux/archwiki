<template>
	<div class="ext-checkuser-CSparkline-container">
		<svg
			:id="`sparkline-${ id }`"
			xmlns="http://www.w3.org/2000/svg"
			class="ext-checkuser-CSparkline"
			@mousemove="handleMouseMove"
			@mouseleave="handleMouseLeave"
		>
			<title>
				{{ title }}
			</title>
		</svg>
		<div
			v-if="tooltipData.visible"
			class="ext-checkuser-CSparkline__tooltip"
			:style="{
				left: tooltipData.x + 'px',
				top: tooltipData.y + 'px'
			}"
		>
			{{ tooltipLabel }}
		</div>
	</div>
</template>

<script>
const { onMounted, reactive, computed } = require( 'vue' );
const DateFormatter = require( 'mediawiki.DateFormatter' );
const d3 = require( '../lib/d3/d3.min.js' );
let chart, sparkline, area, hoverDot = null;
let xScale, yScale = null;

// @vue/component
module.exports = exports = {
	compilerOptions: { whitespace: 'condense' },
	props: {
		title: {
			type: String,
			required: true
		},
		id: {
			type: String,
			required: true
		},
		data: {
			type: Object,
			required: true
		},
		dimensions: {
			type: Object,
			required: true
		},
		xAccessor: {
			type: String,
			required: true
		},
		yAccessor: {
			type: String,
			required: true
		}
	},
	setup( props ) {
		const tooltipData = reactive( {
			visible: false,
			date: '',
			edits: 0,
			x: 0,
			y: 0
		} );
		const tooltipLabel = computed( () => mw.msg(
			'checkuser-userinfocard-chart-tooltip-label',
			tooltipData.date,
			tooltipData.edits
		) );
		// Create accessor functions from property names
		const getXValue = ( d ) => d[ props.xAccessor ];
		const getYValue = ( d ) => d[ props.yAccessor ];

		/**
			* Find the closest data point to the given x-value
			*
			* @param {Object|null} d0 - Previous data point
			* @param {Object|null} d1 - Next data point
			* @param {number} xValue - Target x-value to find closest point to
			* @param {Function} xValueAccessor - Function to extract x-value from data point
			* @return {Object|null} The closest data point or null if none available
			*/
		const findClosestDataPoint = ( d0, d1, xValue, xValueAccessor ) => {
			// If only one data point exists, return it
			if ( !d0 && d1 ) {
				return d1;
			}
			if ( d0 && !d1 ) {
				return d0;
			}

			// Calculate distances to both points
			const distanceToD0 = Math.abs( xValue - xValueAccessor( d0 ) );
			const distanceToD1 = Math.abs( xValueAccessor( d1 ) - xValue );

			// Return the closest point
			return distanceToD1 < distanceToD0 ? d1 : d0;
		};

		const handleMouseMove = ( event ) => {
			const [ mouseX ] = d3.pointer( event );
			const xValue = xScale.invert( mouseX );
			const bisector = d3.bisector( getXValue ).left;
			const index = bisector( props.data, xValue, 1 );
			const d0 = props.data[ index - 1 ];
			const d1 = props.data[ index ];

			if ( !d0 && !d1 ) {
				return;
			}

			const d = findClosestDataPoint( d0, d1, xValue, getXValue );
			const x = xScale( getXValue( d ) );
			const y = yScale( getYValue( d ) );
			hoverDot
				.attr( 'cx', x )
				.attr( 'cy', y )
				.style( 'display', 'block' );

			tooltipData.visible = !!getYValue( d );
			tooltipData.date = DateFormatter.formatDate( getXValue( d ) );
			tooltipData.edits = getYValue( d );

			const svgElement = chart.node();
			const container = svgElement.parentElement;

			// Use requestAnimationFrame to position after Vue renders the tooltip, to ensure
			// the tooltip is positioned within the parent container
			requestAnimationFrame( () => {
				const tooltip = container.querySelector( '.ext-checkuser-CSparkline__tooltip' );
				if ( !tooltip ) {
					return;
				}

				const svgRect = svgElement.getBoundingClientRect();
				const containerRect = container.getBoundingClientRect();
				const tooltipRect = tooltip.getBoundingClientRect();

				const pointX = ( svgRect.left - containerRect.left ) +
					( x / props.dimensions.width ) *
					svgRect.width;
				const pointY = ( svgRect.top - containerRect.top ) +
					( y / props.dimensions.height ) *
					svgRect.height;

				const halfWidth = tooltipRect.width / 2;
				const tooltipX = Math.max(
					0, Math.min( pointX - halfWidth, containerRect.width - tooltipRect.width )
				);
				const tooltipY = pointY - tooltipRect.height - 10;

				tooltipData.x = tooltipX;
				tooltipData.y = tooltipY;
			} );
		};

		const handleMouseLeave = () => {
			hoverDot.style( 'display', 'none' );
			tooltipData.visible = false;
		};
		const plot = () => {
			chart.attr( 'viewBox', `0 0 ${ props.dimensions.width } ${ props.dimensions.height }` );

			const xDomain = d3.extent( props.data, getXValue );
			xScale = d3.scaleTime()
				.domain( xDomain )
				.range( [ 0, props.dimensions.width ] );
			// Get the maximum value from the Y data, or use 10 if all values are 0
			// This ensures the chart displays an accurate representation of the data
			// so the line doesn't stay in the middle of the graph
			const maxY = d3.max( props.data, getYValue ) || 10;
			// Use -10% on the lower yDomain so we see 0 value "lines" in the chart
			// This issue happens in Safari
			const yDomain = [ -0.1 * maxY, maxY ];

			yScale = d3.scaleLinear()
				.domain( yDomain )
				// Flip svg Y-axis coordinate system and add a pixel on top to avoid cutting
				// off anti-aliasing pixels. Do not add a pixel on the bottom, that would make the
				// graph non-0-based, and it's rare for the pageviews to be 0.
				.range( [ props.dimensions.height, 1 ] );

			const lineGenerator = d3.line()
				.x( ( d ) => xScale( getXValue( d ) ) )
				.y( ( d ) => yScale( getYValue( d ) ) );

			const areaGenerator = d3.area()
				.x( ( d ) => xScale( getXValue( d ) ) )
				.y1( ( d ) => yScale( getYValue( d ) ) )
				.y0( props.dimensions.height );

			sparkline
				.data( [ props.data ] )
				.attr( 'd', lineGenerator )
				.attr( 'stroke-width', 1 )
				.attr( 'stroke-linejoin', 'round' )
				.attr( 'fill', 'none' );
			area
				.data( [ props.data ] )
				.attr( 'd', areaGenerator );
		};

		onMounted( () => {
			chart = d3.select( `#sparkline-${ props.id }` );
			// Append order is relevant. Render the line over the area
			area = chart.append( 'path' ).attr( 'class', 'ext-checkuser-CSparkline__area' );
			sparkline = chart.append( 'path' ).attr( 'class', 'ext-checkuser-CSparkline__line' );

			// Add hover dot (initially hidden)
			hoverDot = chart.append( 'circle' )
				.attr( 'class', 'ext-checkuser-CSparkline__hover-dot' )
				.attr( 'r', 3 )
				.style( 'display', 'none' );

			plot();
		} );

		return {
			tooltipData,
			tooltipLabel,
			handleMouseMove,
			handleMouseLeave
		};
	}

};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-checkuser-CSparkline-container {
	position: relative;
}

.ext-checkuser-CSparkline {
	padding: @spacing-0 @spacing-25 @spacing-0 @spacing-12;
	overflow: visible;

	&__line {
		stroke: @background-color-progressive--focus;
	}

	&__area {
		fill: @background-color-progressive-subtle;
	}

	&__hover-dot {
		fill: @background-color-progressive--focus;
		stroke: @background-color-progressive--focus;
		stroke-width: 1px;
	}

	// The following CSS was copied from Codex (https://gerrit.wikimedia.org/r/plugins/gitiles/design/codex/+/refs/heads/main/packages/codex/src/components/tooltip/Tooltip.less)
	&__tooltip {
		pointer-events: none;
		white-space: nowrap;
		background-color: @background-color-inverted;
		color: @color-inverted;
		position: absolute;
		z-index: @z-index-tooltip;
		width: @size-content-max;
		max-width: @size-1600;
		border-radius: @border-radius-base;
		padding: @spacing-12 @spacing-35;
		font-family: @font-family-system-sans;
		font-size: @font-size-x-small;
		font-weight: @font-weight-normal;
		line-height: @line-height-small;
	}
}

</style>

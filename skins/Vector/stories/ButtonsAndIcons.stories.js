import { CdxIcon, CdxButton } from '@wikimedia/codex';
import '../node_modules/@wikimedia/codex/dist/codex.style.css';
import { h, createApp } from 'vue';
import buttonTemplate from '!!raw-loader!../includes/templates/Button.mustache';
import mustache from 'mustache';
import { cdxIconAdd } from '@wikimedia/codex-icons';

export default {
	title: 'Icon and Buttons'
};

/**
 *
 * @typedef {Object} ButtonProps
 * @property {string} type
 * @property {string} action
 */
/**
 * @param {ButtonProps} props
 * @param {string} label
 * @return {string}
 */
function makeButtonLegacy( props, label ) {
	let typeClass = '';
	let iconClass = 'mw-ui-icon-add';
	switch ( props.action ) {
		case 'progressive':
			typeClass += ' mw-ui-progressive';
			iconClass += '-progressive';
			break;
		case 'destructive':
			typeClass += ' mw-ui-destructive';
			iconClass += '-destructive';
			break;
	}
	if ( props.type === 'primary' ) {
		iconClass = 'mw-ui-icon-add-invert';
	}
	return mustache.render( buttonTemplate, {
		label,
		class: typeClass,
		'is-quiet': props.type === 'quiet',
		'html-vector-button-icon': `<span class="mw-ui-icon ${iconClass}"></span>`
	} );
}

/**
 * @param {ButtonProps} props
 * @param {string} text
 * @param {string} icon
 * @return {string}
 */
function makeButton( props, text, icon ) {
	const el = document.createElement( 'div' );
	const vm = createApp( {
		render: function () {
			// @ts-ignore
			return h( CdxButton, props, [
				h( CdxIcon, { icon } ),
				text
			] );
		}
	} );
	vm.mount( el );
	return `
	<tr>
		<td>${makeButtonLegacy( props, text )}</td>
		<td>${el.outerHTML}</td>
	</tr>`;
}

/**
 * @return {string}
 */
function makeIcon() {
	const el = document.createElement( 'div' );
	const vm = createApp( {
		render: function () {
			// @ts-ignore
			return h( CdxButton, {
				'aria-label': 'add'
			}, [
				h( CdxIcon, { icon: cdxIconAdd } )
			] );
		}
	} );
	vm.mount( el );
	const elQuiet = document.createElement( 'div' );
	const vmQuiet = createApp( {
		render: function () {
			// @ts-ignore
			return h( CdxButton, {
				type: 'quiet',
				'aria-label': 'add'
			}, [
				h( CdxIcon, { icon: cdxIconAdd } )
			] );
		}
	} );
	vmQuiet.mount( elQuiet );
	return `
	<tr>
		<td>
			<h6>Regular icon</h6>
		${
	mustache.render( buttonTemplate, {
		label: 'Normal Icon button',
		icon: 'add'
	} )
}
		</td>
		<td>
			<h6>Regular icon</h6>
		${el.outerHTML}
		</td>
	</tr>
	<tr>
		<td>
		<h6>Small icon button</h6>
		${
	mustache.render( buttonTemplate, {
		label: 'Small icon button',
		class: 'mw-ui-icon-small',
		icon: 'add'
	} )
}
		</td>
		<td>
			<h6>Small icon button</h6>
			${el.outerHTML}</td>
	</tr>
	<tr>
		<td>
		<h6>Quiet icon button</h6>
		${
	mustache.render( buttonTemplate, {
		label: 'Quiet Icon',
		'is-quiet': true,
		icon: 'add'
	} )
}
		</td>
		<td>
		<h6>Quiet icon button</h6>
		${elQuiet.outerHTML}</td>
	</tr>`;
}

/**
 *
 * @param {string[]} btns
 * @return {string}
 */
function makeButtons( btns ) {
	return `<table class="vector-storybook-example-table">
	<tbody>
		<tr>
			<th>Legacy</th>
			<th>Codex</th>
		</tr>
		${btns.join( '\n' )}
	</tbody>
</table>`;
}

/**
 * @return {string}
 */
export const Button = () => makeButtons( [
	makeButton( {
		action: 'default',
		type: 'quiet'
	}, 'Quiet button', cdxIconAdd ),
	makeButton( {
		action: 'progressive',
		type: 'quiet'
	}, 'Quiet progressive', cdxIconAdd ),
	makeButton( {
		action: 'destructive',
		type: 'quiet'
	}, 'Quiet destructive', cdxIconAdd ),
	makeButton( {
		action: 'default',
		type: 'normal'
	}, 'Normal', cdxIconAdd ),
	makeButton( {
		type: 'primary',
		action: 'progressive'
	}, 'Progressive primary', cdxIconAdd ),
	makeButton( {
		type: 'primary',
		action: 'destructive'
	}, 'Destructive primary', cdxIconAdd ),
	makeIcon()
] );

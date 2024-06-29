<?php
namespace MediaWiki\Tests\Integration\HTMLForm\Field;

use MediaWiki\Tests\Integration\HTMLForm\HTMLFormFieldTestCase;

/**
 * @covers MediaWiki\HTMLForm\Field\HTMLCheckField
 */
class HTMLCheckFieldTest extends HTMLFormFieldTestCase {
	protected $className = 'HTMLCheckField';

	public static function provideInputCodex() {
		yield 'Basic checkbox' => [
			[
				'label' => 'Check me',
			],
			false,
			false,
			<<<HTML
			<div class="cdx-checkbox">
				<input name="testfield" type="checkbox" value="1" id="mw-input-testfield" class=" cdx-checkbox__input" />
				<span class="cdx-checkbox__icon">\u{00A0}</span>
				<label for="mw-input-testfield" class="cdx-checkbox__label">Check me</label>
			</div>
			HTML
		];

		yield 'Checked checkbox with CSS class' => [
			[
				'label' => 'Check me',
				'cssclass' => 'my-checkbox'
			],
			'1',
			false,
			<<<HTML
			<div class="cdx-checkbox">
				<input name="testfield" type="checkbox" value="1" checked="checked" id="mw-input-testfield" class="my-checkbox cdx-checkbox__input" />
				<span class="cdx-checkbox__icon">\u{00A0}</span>
				<label for="mw-input-testfield" class="cdx-checkbox__label">Check me</label>
			</div>
			HTML
		];

		yield 'Inverted checkbox' => [
			[
				'label' => 'Check me',
				'invert' => true
			],
			false,
			false,
			<<<HTML
			<div class="cdx-checkbox">
				<input name="testfield" type="checkbox" value="1" checked="checked" id="mw-input-testfield" class=" cdx-checkbox__input" />
				<span class="cdx-checkbox__icon">\u{00A0}</span>
				<label for="mw-input-testfield" class="cdx-checkbox__label">Check me</label>
			</div>
			HTML
		];

		yield 'Disabled checkbox with error state' => [
			[
				'label' => 'Check me',
				'disabled' => true,
			],
			false,
			true,
			<<<HTML
			<div class="cdx-checkbox cdx-checkbox--status-error">
				<input name="testfield" type="checkbox" value="1" id="mw-input-testfield" disabled="" class=" cdx-checkbox__input" />
				<span class="cdx-checkbox__icon">\u{00A0}</span>
				<label for="mw-input-testfield" class="cdx-checkbox__label">Check me</label>
			</div>
			HTML
		];

		yield 'Checkbox with tooltip and accesskey' => [
			[
				'label' => 'Watch',
				'tooltip' => 'watch'
			],
			false,
			false,
			<<<HTML
			<div class="cdx-checkbox" title="Add this page to your watchlist [w]">
				<input name="testfield" type="checkbox" value="1" title="Add this page to your watchlist [w]" accesskey="w" id="mw-input-testfield" class=" cdx-checkbox__input" />
				<span class="cdx-checkbox__icon">\u{00A0}</span>
				<label for="mw-input-testfield" class="cdx-checkbox__label">Watch</label>
			</div>
			HTML
		];
	}
}

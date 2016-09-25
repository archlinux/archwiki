<?php
if ( !defined( 'OOUI_DEMOS' ) ) {
	header( 'Location: ../demos.php' );
	exit;
}

$demoContainer = new OOUI\PanelLayout( [
	'expanded' => false,
	'padded' => true,
	'framed' => true,
] );
$demoContainer->addClasses( [ 'oo-ui-demo-container' ] );

$styles = [
	[],
	[
		'flags' => [ 'progressive' ],
	],
	[
		'flags' => [ 'constructive' ],
	],
	[
		'flags' => [ 'destructive' ],
	],
	[
		'flags' => [ 'primary', 'progressive' ],
	],
	[
		'flags' => [ 'primary', 'constructive' ],
	],
	[
		'flags' => [ 'primary', 'destructive' ],
	],
];
$states = [
	[
		'label' => 'Button',
	],
	[
		'label' => 'Button',
		'icon' => 'tag',
	],
	[
		'label' => 'Button',
		'icon' => 'tag',
		'indicator' => 'down',
	],
	[
		'icon' => 'tag',
		'title' => "Title text",
	],
	[
		'indicator' => 'down',
	],
	[
		'icon' => 'tag',
		'indicator' => 'down',
	],
	[
		'label' => 'Button',
		'disabled' => true,
	],
	[
		'icon' => 'tag',
		'title' => "Title text",
		'disabled' => true,
	],
	[
		'indicator' => 'down',
		'disabled' => true,
	],
];
$buttonStyleShowcaseWidget = new OOUI\Widget();
$table = new OOUI\Tag( 'table' );
foreach ( $styles as $style ) {
	$tableRow = new OOUI\Tag( 'tr' );
	foreach ( $states as $state ) {
		$tableCell = new OOUI\Tag( 'td' );
		$tableCell->appendContent(
			new OOUI\ButtonWidget( array_merge( $style, $state, [ 'infusable' => true ] ) )
		);
		$tableRow->appendContent( $tableCell );
	}
	$table->appendContent( $tableRow );
}
$buttonStyleShowcaseWidget->appendContent( $table );

$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Simple buttons',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [ 'label' => 'Normal' ] ),
			[
				'label' => "ButtonWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Progressive',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Constructive',
				'flags' => [ 'constructive' ]
			] ),
			[
				'label' => "ButtonWidget (constructive, deprecated)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Destructive',
				'flags' => [ 'destructive' ]
			] ),
			[
				'label' => "ButtonWidget (destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Primary progressive',
				'flags' => [ 'primary', 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (primary, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Primary constructive',
				'flags' => [ 'primary', 'constructive' ]
			] ),
			[
				'label' => "ButtonWidget (primary, constructive, deprecated)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Primary destructive',
				'flags' => [ 'primary', 'destructive' ]
			] ),
			[
				'label' => "ButtonWidget (primary, destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Progressive',
				'flags' => [ 'progressive' ],
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (progressive, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Progressive',
				'icon' => 'tag',
				'flags' => [ 'progressive' ],
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (progressive, icon, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Icon',
				'icon' => 'tag'
			] ),
			[
				'label' => "ButtonWidget (icon)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Icon',
				'icon' => 'tag',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (icon, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Indicator',
				'indicator' => 'down'
			] ),
			[
				'label' => "ButtonWidget (indicator)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Indicator',
				'indicator' => 'down',
				'flags' => [ 'progressive' ]
			] ),
			[
				'label' => "ButtonWidget (indicator, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'help',
				'title' => 'Icon only'
			] ),
			[
				'label' => "ButtonWidget (icon only)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'tag',
				'label' => 'Labeled'
			] ),
			[
				'label' => "ButtonWidget (frameless)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'progressive' ],
				'icon' => 'check',
				'label' => 'Progressive'
			] ),
			[
				'label' => "ButtonWidget (frameless, progressive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'destructive' ],
				'icon' => 'remove',
				'label' => 'Destructive'
			] ),
			[
				'label' => "ButtonWidget (frameless, destructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'constructive' ],
				'icon' => 'add',
				'label' => 'Constructive'
			] ),
			[
				'label' => "ButtonWidget (frameless, constructive)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'icon' => 'tag',
				'label' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (frameless, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'framed' => false,
				'flags' => [ 'constructive' ],
				'icon' => 'tag',
				'label' => 'Constructive',
				'disabled' => true
			] ),
			[
				'label' => "ButtonWidget (frameless, constructive, disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'AccessKeyed',
				'accessKey' => 'k',
			] ),
			[
				'label' => "ButtonWidget (with accesskey k)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Button sets',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonGroupWidget( [
				'items' => [
					new OOUI\ButtonWidget( [
						'icon' => 'tag',
						'label' => 'One'
					] ),
					new OOUI\ButtonWidget( [
						'label' => 'Two'
					] ),
					new OOUI\ButtonWidget( [
						'indicator' => 'required',
						'label' => 'Three'
					] )
				]
			] ),
			[
				'label' => 'ButtonGroupWidget',
				'align' => 'top'
			]
		)
	]
] ) );
# Note that $buttonStyleShowcaseWidget is not infusable,
# because the contents would not be preserved -- we assume
# that widgets will manage their own contents by default,
# but here we've manually appended content to the widget.
# If we embed it in an infusable FieldsetLayout, it will be
# (recursively) made infusable.  We protect the FieldLayout
# by wrapping it with a new <div> Tag, so that it won't get
# rebuilt during infusion.
$wrappedFieldLayout = ( new OOUI\Tag( 'div' ) )
	->appendContent(
		new OOUI\FieldLayout(
			$buttonStyleShowcaseWidget,
			[
				'align' => 'top'
			]
		)
	);
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Button style showcase',
	'items' => [ $wrappedFieldLayout ],
] ) );
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Form widgets',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [
				'selected' => true
			] ),
			[
				'align' => 'inline',
				'label' => 'CheckboxInputWidget'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\CheckboxInputWidget( [
				'selected' => true,
				'disabled' => true
			] ),
			[
				'align' => 'inline',
				'label' => "CheckboxInputWidget (disabled)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'name' => 'oojs-ui-radio-demo'
			] ),
			[
				'align' => 'inline',
				'label' => 'Connected RadioInputWidget #1'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'name' => 'oojs-ui-radio-demo',
				'selected' => true
			] ),
			[
				'align' => 'inline',
				'label' => 'Connected RadioInputWidget #2'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioInputWidget( [
				'selected' => true,
				'disabled' => true
			] ),
			[
				'align' => 'inline',
				'label' => "RadioInputWidget (disabled)\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\RadioSelectInputWidget( [
				'value' => 'dog',
				'options' => [
					[
						'data' => 'cat',
						'label' => 'Cat'
					],
					[
						'data' => 'dog',
						'label' => 'Dog'
					],
					[
						'data' => 'goldfish',
						'label' => 'Goldfish'
					],
				]
			] ),
			[
				'align' => 'top',
				'label' => 'RadioSelectInputWidget',
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'value' => 'Text input' ] ),
			[
				'label' => "TextInputWidget\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'icon' => 'search' ] ),
			[
				'label' => "TextInputWidget (icon)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'required' => true
			] ),
			[
				'label' => "TextInputWidget (required)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'placeholder' => 'Placeholder' ] ),
			[
				'label' => "TextInputWidget (placeholder)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'type' => 'search' ] ),
			[
				'label' => "TextInputWidget (type=search)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [ 'type' => 'number' ] ),
			[
				'label' => "TextInputWidget (type=number)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Readonly',
				'readOnly' => true
			] ),
			[
				'label' => "TextInputWidget (readonly)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Disabled',
				'disabled' => true
			] ),
			[
				'label' => "TextInputWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Accesskey A',
				'accessKey' => 'a'
			] ),
			[
				'label' => "TextInputWidget (with Accesskey)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Title attribute',
				'title' => 'Title attribute with more information about me.'
			] ),
			[
				'label' => "TextInputWidget (with title)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'multiline' => true,
				'value' => "Multiline\nMultiline"
			] ),
			[
				'label' => "TextInputWidget (multiline)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'multiline' => true,
				'rows' => 15,
				'value' => "Multiline\nMultiline"
			] ),
			[
				'label' => "TextInputWidget (multiline, rows=15)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'multiline' => true,
				'value' => "Multiline\nMultiline",
				'icon' => 'tag',
				'indicator' => 'required'
			] ),
			[
				'label' => "TextInputWidget (multiline, icon, indicator)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'options' => [
					[
						'data' => 'a',
						'label' => 'First'
					],
					[
						'data' => 'b',
						'label' => 'Second'
					],
					[
						'data' => 'c',
						'label' => 'Third'
					]
				],
				'value' => 'b',
				'title' => 'Select an item'
			] ),
			[
				'label' => 'DropdownInputWidget',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\DropdownInputWidget( [
				'options' => [
					[ 'data' => 'sq', 'label' => 'Albanian' ],
					[ 'data' => 'frp', 'label' => 'Arpitan' ],
					[ 'data' => 'ba', 'label' => 'Bashkir' ],
					[ 'data' => 'pt-br', 'label' => 'Brazilian Portuguese' ],
					[ 'data' => 'tzm', 'label' => 'Central Atlas Tamazight' ],
					[ 'data' => 'zh', 'label' => 'Chinese' ],
					[ 'data' => 'co', 'label' => 'Corsican' ],
					[ 'data' => 'del', 'label' => 'Delaware' ],
					[ 'data' => 'eml', 'label' => 'Emiliano-Romagnolo' ],
					[ 'data' => 'en', 'label' => 'English' ],
					[ 'data' => 'fi', 'label' => 'Finnish' ],
					[ 'data' => 'aln', 'label' => 'Gheg Albanian' ],
					[ 'data' => 'he', 'label' => 'Hebrew' ],
					[ 'data' => 'ilo', 'label' => 'Iloko' ],
					[ 'data' => 'kbd', 'label' => 'Kabardian' ],
					[ 'data' => 'csb', 'label' => 'Kashubian' ],
					[ 'data' => 'avk', 'label' => 'Kotava' ],
					[ 'data' => 'lez', 'label' => 'Lezghian' ],
					[ 'data' => 'nds-nl', 'label' => 'Low Saxon' ],
					[ 'data' => 'ml', 'label' => 'Malayalam' ],
					[ 'data' => 'dum', 'label' => 'Middle Dutch' ],
					[ 'data' => 'ary', 'label' => 'Moroccan Arabic' ],
					[ 'data' => 'pih', 'label' => 'Norfuk / Pitkern' ],
					[ 'data' => 'ny', 'label' => 'Nyanja' ],
					[ 'data' => 'ang', 'label' => 'Old English' ],
					[ 'data' => 'non', 'label' => 'Old Norse' ],
					[ 'data' => 'pau', 'label' => 'Palauan' ],
					[ 'data' => 'pdt', 'label' => 'Plautdietsch' ],
					[ 'data' => 'ru', 'label' => 'Russian' ],
					[ 'data' => 'stq', 'label' => 'Saterland Frisian' ],
					[ 'data' => 'ii', 'label' => 'Sichuan Yi' ],
					[ 'data' => 'bcc', 'label' => 'Southern Balochi' ],
					[ 'data' => 'shi', 'label' => 'Tachelhit' ],
					[ 'data' => 'th', 'label' => 'Thai' ],
					[ 'data' => 'tr', 'label' => 'Turkish' ],
					[ 'data' => 'fiu-vro', 'label' => 'Võro' ],
					[ 'data' => 'vls', 'label' => 'West Flemish' ],
					[ 'data' => 'zea', 'label' => 'Zeelandic' ],
				],
				'value' => 'en',
			] ),
			[
				'label' => "DropdownInputWidget (long)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget( [
				'options' => [
					[ 'data' => 'asd', 'label' => 'Label for asd' ],
					[ 'data' => 'fgh', 'label' => 'Label for fgh' ],
					[ 'data' => 'jkl', 'label' => 'Label for jkl' ],
					[ 'data' => 'zxc', 'label' => 'Label for zxc' ],
					[ 'data' => 'vbn', 'label' => 'Label for vbn' ],
				]
			] ),
			[
				'label' => 'ComboBoxInputWidget',
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget( [
				'disabled' => true,
				'options' => [
					[ 'data' => 'asd', 'label' => 'Label for asd' ],
					[ 'data' => 'fgh', 'label' => 'Label for fgh' ],
					[ 'data' => 'jkl', 'label' => 'Label for jkl' ],
					[ 'data' => 'zxc', 'label' => 'Label for zxc' ],
					[ 'data' => 'vbn', 'label' => 'Label for vbn' ],
				]
			] ),
			[
				'label' => "ComboBoxInputWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ComboBoxInputWidget(),
			[
				'label' => "ComboBoxInputWidget (empty)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'label' => 'Submit the form',
				'type' => 'submit'
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget\xE2\x80\x8E"
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonInputWidget( [
				'label' => 'Submit the form',
				'type' => 'submit',
				'useInputTag' => true
			] ),
			[
				'align' => 'top',
				'label' => "ButtonInputWidget (using <input/>)\xE2\x80\x8E"
			]
		)
	]
] ) );
// We can't make the outer FieldsetLayout infusable, because the Widget in its FieldLayout
// is added with 'content', which is not preserved after infusion. But we need the Widget
// to wrap the HorizontalLayout. Need to think about this at some point.
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => false,
	'label' => 'HorizontalLayout',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\Widget( [
				'content' => new OOUI\HorizontalLayout( [
					'infusable' => true,
					'items' => [
						new OOUI\ButtonWidget( [ 'label' => 'Button' ] ),
						new OOUI\ButtonGroupWidget( [ 'items' => [
							new OOUI\ButtonWidget( [ 'label' => 'A' ] ),
							new OOUI\ButtonWidget( [ 'label' => 'B' ] )
						] ] ),
						new OOUI\ButtonInputWidget( [ 'label' => 'ButtonInput' ] ),
						new OOUI\TextInputWidget( [ 'value' => 'TextInput' ] ),
						new OOUI\DropdownInputWidget( [ 'options' => [
							[
								'label' => 'DropdownInput',
								'data' => null
							]
						] ] ),
						new OOUI\CheckboxInputWidget( [ 'selected' => true ] ),
						new OOUI\RadioInputWidget( [ 'selected' => true ] ),
						new OOUI\LabelWidget( [ 'label' => 'Label' ] )
					],
				] ),
			] ),
			[
				'label' => 'Multiple widgets shown as a single line, ' .
					'as used in compact forms or in parts of a bigger widget.',
				'align' => 'top'
			]
		),
	],
] ) );
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Other widgets',
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'search',
				'title' => 'Search icon'
			] ),
			[
				'label' => "IconWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'remove',
				'flags' => 'destructive',
				'title' => 'Remove icon'
			] ),
			[
				'label' => "IconWidget (flagged)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IconWidget( [
				'icon' => 'search',
				'title' => 'Search icon',
				'disabled' => true
			] ),
			[
				'label' => "IconWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IndicatorWidget( [
				'indicator' => 'required',
				'title' => 'Required indicator'
			] ),
			[
				'label' => "IndicatorWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\IndicatorWidget( [
				'indicator' => 'required',
				'title' => 'Required indicator',
				'disabled' => true
			] ),
			[
				'label' => "IndicatorWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => 'Label'
			] ),
			[
				'label' => "LabelWidget (normal)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => 'Label',
				'disabled' => true,
			] ),
			[
				'label' => "LabelWidget (disabled)\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\LabelWidget( [
				'label' => new OOUI\HtmlSnippet( '<b>Fancy</b> <i>text</i> <u>formatting</u>!' ),
			] ),
			[
				'label' => "LabelWidget (with html)\xE2\x80\x8E",
				'align' => 'top'
			]
		)
	]
] ) );
$demoContainer->appendContent( new OOUI\FieldsetLayout( [
	'infusable' => true,
	'label' => 'Field layouts',
	'help' => 'I am an additional, helpful information. Lorem ipsum dolor sit amet, cibo pri ' .
		"in, duo ex inimicus perpetua complectitur, mel periculis similique at.\xE2\x80\x8E",
	'items' => [
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with help',
				'help' => 'I am an additional, helpful information. Lorem ipsum dolor sit amet, cibo pri ' .
					"in, duo ex inimicus perpetua complectitur, mel periculis similique at.\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with HTML help',
				'help' => new OOUI\HtmlSnippet( '<b>Bold text</b> is helpful!' ),
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'FieldLayout with title',
				'title' => 'Field title text',
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned left',
				'align' => 'left'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned inline',
				'align' => 'inline'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned right',
				'align' => 'right'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned top',
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' => 'ActionFieldLayout aligned top with help',
				'help' => 'I am an additional, helpful information. Lorem ipsum dolor sit amet, cibo pri ' .
					"in, duo ex inimicus perpetua complectitur, mel periculis similique at.\xE2\x80\x8E",
				'align' => 'top'
			]
		),
		new OOUI\ActionFieldLayout(
			new OOUI\TextInputWidget(),
			new OOUI\ButtonWidget( [
				'label' => 'Button'
			] ),
			[
				'label' =>
					new OOUI\HtmlSnippet( '<i>ActionFieldLayout aligned top with rich text label</i>' ),
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => ''
			] ),
			[
				'label' => 'FieldLayout with notice',
				'notices' => [ 'Please input a number.' ],
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Foo'
			] ),
			[
				'label' => 'FieldLayout with error message',
				'errors' => [ 'The value must be a number.' ],
				'align' => 'top'
			]
		),
		new OOUI\FieldLayout(
			new OOUI\TextInputWidget( [
				'value' => 'Foo'
			] ),
			[
				'label' => 'FieldLayout with notice and error message',
				'notices' => [ 'Please input a number.' ],
				'errors' => [ 'The value must be a number.' ],
				'align' => 'top'
			]
		),
	]
] ) );

$demoContainer->appendContent( new OOUI\FormLayout( [
	'infusable' => true,
	'method' => 'GET',
	'action' => 'demos.php',
	'items' => [
		new OOUI\FieldsetLayout( [
			'label' => 'Form layout',
			'items' => [
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'username',
					] ),
					[
						'label' => 'User name',
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\TextInputWidget( [
						'name' => 'password',
						'type' => 'password',
					] ),
					[
						'label' => 'Password',
						'align' => 'top',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\CheckboxInputWidget( [
						'name' => 'rememberme',
						'selected' => true,
					] ),
					[
						'label' => 'Remember me',
						'align' => 'inline',
					]
				),
				new OOUI\FieldLayout(
					new OOUI\ButtonInputWidget( [
						'name' => 'login',
						'label' => 'Log in',
						'type' => 'submit',
						'flags' => [ 'primary', 'progressive' ],
						'icon' => 'check',
					] ),
					[
						'label' => null,
						'align' => 'top',
					]
				),
			]
		] )
	]
] ) );

echo $demoContainer;

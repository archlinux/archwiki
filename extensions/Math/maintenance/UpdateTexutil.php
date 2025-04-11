<?php

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLutil;

class UpdateTexutil extends Maintenance {

	private const LEGACY_CONCEPTS = [
		'Apricot' => '#FBB982',
		'Aquamarine' => '#00B5BE',
		'Bittersweet' => '#C04F17',
		'Black' => '#221E1F',
		'Blue' => '#2D2F92',
		'BlueGreen' => '#00B3B8',
		'BlueViolet' => '#473992',
		'BrickRed' => '#B6321C',
		'Brown' => '#792500',
		'BurntOrange' => '#F7921D',
		'CadetBlue' => '#74729A',
		'CarnationPink' => '#F282B4',
		'Cerulean' => '#00A2E3',
		'CornflowerBlue' => '#41B0E4',
		'Cyan' => '#00AEEF',
		'Dandelion' => '#FDBC42',
		'DarkOrchid' => '#A4538A',
		'Emerald' => '#00A99D',
		'ForestGreen' => '#009B55',
		'Fuchsia' => '#8C368C',
		'Goldenrod' => '#FFDF42',
		'Gray' => '#949698',
		'Green' => '#00A64F',
		'GreenYellow' => '#DFE674',
		'JungleGreen' => '#00A99A',
		'Lavender' => '#F49EC4',
		'LimeGreen' => '#8DC73E',
		'Magenta' => '#EC008C',
		'Mahogany' => '#A9341F',
		'Maroon' => '#AF3235',
		'Melon' => '#F89E7B',
		'MidnightBlue' => '#006795',
		'Mulberry' => '#A93C93',
		'NavyBlue' => '#006EB8',
		'OliveGreen' => '#3C8031',
		'Orange' => '#F58137',
		'OrangeRed' => '#ED135A',
		'Orchid' => '#AF72B0',
		'Peach' => '#F7965A',
		'Periwinkle' => '#7977B8',
		'PineGreen' => '#008B72',
		'Plum' => '#92268F',
		'ProcessBlue' => '#00B0F0',
		'Purple' => '#99479B',
		'RawSienna' => '#974006',
		'Red' => '#ED1B23',
		'RedOrange' => '#F26035',
		'RedViolet' => '#A1246B',
		'Rhodamine' => '#EF559F',
		'RoyalBlue' => '#0071BC',
		'RoyalPurple' => '#613F99',
		'RubineRed' => '#ED017D',
		'Salmon' => '#F69289',
		'SeaGreen' => '#3FBC9D',
		'Sepia' => '#671800',
		'SkyBlue' => '#46C5DD',
		'SpringGreen' => '#C6DC67',
		'Tan' => '#DA9D76',
		'TealBlue' => '#00AEB3',
		'Thistle' => '#D883B7',
		'Turquoise' => '#00B4CE',
		'Violet' => '#58429B',
		'VioletRed' => '#EF58A0',
		'White' => '#FFFFFF',
		'WildStrawberry' => '#EE2967',
		'Yellow' => '#FFF200',
		'YellowGreen' => '#98CC70',
		'YellowOrange' => '#FAA21A',
	];

	public function execute() {
		$jsonFilePath = './src/WikiTexVC/texutil.json';

		$jsonContent = json_decode( file_get_contents( $jsonFilePath ), true );

		if ( $jsonContent === null ) {
			die( "Failed to decode texutil.json. Please check the file format.\n" );
		}

		foreach ( self::LEGACY_CONCEPTS as $key => $value ) {

			$value[0] = MMLutil::uc2xNotation( $value[0] );
			// Remove the texClass from the array
			// Check if the entry already exists in texutil.json
			if ( isset( $jsonContent["$key"] ) ) {
				// Preserve existing attributes and only add or update the identifier
				$jsonContent["$key"]['color'] = $value;
			} else {
				// Create a new entry if it doesn't exist
				$jsonContent["$key"] = [
					'color' => $value
				];
			}

			// Sort the entry alphabetically
			ksort( $jsonContent["$key"] );
		}
		// Sort the entire file
		ksort( $jsonContent );
		$jsonString = json_encode( $jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$jsonStringWithTabs = preg_replace_callback( '/^( +)/m', static function ( $matches ) {
				// Convert spaces to tabs (assuming 4 spaces per tab level)
				return str_repeat( "\t", strlen( $matches[1] ) / 4 );
		}, $jsonString ) . "\n";
		// prevent eslint error  Unnecessary escape character: \/  no-useless-escape
		$jsonStringWithTabs = str_replace( '\/', '/', $jsonStringWithTabs );

		file_put_contents( $jsonFilePath, $jsonStringWithTabs );

		echo "texutil.json successfully updated.\n";
	}
}
$maintClass = UpdateTexutil::class;
require_once RUN_MAINTENANCE_IF_MAIN;

<?php
	define( 'OOUI_DEMOS', true );

	$autoload = __DIR__ . '/vendor/autoload.php';
	if ( !file_exists( $autoload ) ) {
		echo '<p>Did you forget to run <code>composer install</code>?</p>';
		exit();
	}
	require_once $autoload;
	require_once 'classes/ButtonStyleShowcaseWidget.php';

	$themes = [
		'mediawiki' => 'MediaWiki', // Do not change this line or you'll break `grunt add-theme`
		'apex' => 'Apex',
	];
	$theme = ( isset( $_GET['theme'] ) && isset( $themes[ $_GET['theme'] ] ) )
		? $_GET['theme'] : 'mediawiki';
	$themeClass = 'OOUI\\' . $themes[ $theme ] . 'Theme';
	OOUI\Theme::setSingleton( new $themeClass() );

	$direction = ( isset( $_GET['direction'] ) && $_GET['direction'] === 'rtl' ) ? 'rtl' : 'ltr';
	$directionSuffix = $direction === 'rtl' ? '.rtl' : '';
	OOUI\Element::setDefaultDir( $direction );

	// We will require_once a file by this name later, so this validation is important
	$pages = [ 'widgets' ];
	$page = ( isset( $_GET['page'] ) && in_array( $_GET['page'], $pages ) )
		? $_GET['page'] : 'widgets';

	$query = [
		'theme' => $theme,
		'direction' => $direction,
		'page' => $page,
	];
	// E.g. oojs-ui-core-apex.rtl.css
	$styleFileName = "oojs-ui-core-$theme$directionSuffix.css";
	// E.g. oojs-ui-images-apex.rtl.css
	$styleFileNameImages = "oojs-ui-images-$theme$directionSuffix.css";
	// E.g. oojs-ui-apex-icons-content.rtl.css
	$styleFileNameExtraIcons = "oojs-ui-$theme-icons-content$directionSuffix.css";
?>
<!DOCTYPE html>
<html lang="en" dir="<?php echo $direction; ?>">
<head>
	<meta charset="UTF-8">
	<title>OOjs UI Widget Demo</title>
	<link rel="stylesheet" href="dist/<?php echo $styleFileName; ?>">
	<link rel="stylesheet" href="dist/<?php echo $styleFileNameImages; ?>">
	<link rel="stylesheet" href="dist/<?php echo $styleFileNameExtraIcons; ?>">
	<link rel="stylesheet" href="styles/demo<?php echo $directionSuffix; ?>.css">
	<link rel="stylesheet" href="classes/ButtonStyleShowcaseWidget.css">
</head>
<body class="oo-ui-<?php echo $direction; ?>">
	<div class="demo">
		<div class="demo-menu">
			<?php
				echo new OOUI\ButtonGroupWidget( [
					'infusable' => true,
					'items' => array_map( function ( $theme, $themeLabel ) use ( $query ) {
						return new OOUI\ButtonWidget( [
							'label' => $themeLabel,
							'href' => '?' . http_build_query( array_merge( $query, [ 'theme' => $theme ] ) ),
							'active' => $query['theme'] === $theme,
						] );
					}, array_keys( $themes ), array_values( $themes ) ),
				] );
				echo new OOUI\ButtonGroupWidget( [
					'infusable' => true,
					'items' => [
						new OOUI\ButtonWidget( [
							'label' => 'LTR',
							'href' => '?' . http_build_query( array_merge( $query, [ 'direction' => 'ltr' ] ) ),
							'active' => $query['direction'] === 'ltr',
						] ),
						new OOUI\ButtonWidget( [
							'label' => 'RTL',
							'href' => '?' . http_build_query( array_merge( $query, [ 'direction' => 'rtl' ] ) ),
							'active' => $query['direction'] === 'rtl',
						] ),
					]
				] );
				echo new OOUI\ButtonGroupWidget( [
					'infusable' => true,
					'id' => 'demo-menu-infuse',
					'items' => [
						new OOUI\ButtonWidget( [
							'label' => 'JS',
							'href' => ".#$page-$theme-$direction",
							'active' => false,
						] ),
						new OOUI\ButtonWidget( [
							'label' => 'PHP',
							'href' => '?' . http_build_query( $query ),
							'active' => true,
						] ),
					]
				] );

				echo new OOUI\ButtonWidget( [
					'label' => 'Docs',
					'icon' => 'journal',
					'href' => '../php/',
					'flags' => [ 'progressive' ],
				] );
			?>
		</div>
		<?php
			// $page is validated above
			require_once "pages/$page.php";
		?>
	</div>

	<!-- Demonstrate JavaScript "infusion" of PHP widgets -->
	<script src="node_modules/jquery/dist/jquery.js"></script>
	<script src="node_modules/oojs/dist/oojs.jquery.js"></script>
	<script src="dist/oojs-ui-core.js"></script>
	<script src="dist/oojs-ui-<?php echo $theme; ?>.js"></script>
	<script>window.Demo = {};</script>
	<script src="classes/ButtonStyleShowcaseWidget.js"></script>
	<script src="infusion.js"></script>
</body>
</html>

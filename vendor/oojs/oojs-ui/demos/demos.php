<?php
	// This file should remain syntax-compatible with PHP 5.3, so that we can execute this check.
	// No short arrays please.
	if ( version_compare( PHP_VERSION, '5.5.9', '<=' ) ) {
		echo '<p>Sorry, the PHP demo requires PHP 5.5.9+, which is not yet available on this server. ' .
			'Please see <a href="https://phabricator.wikimedia.org/T127504">T127504</a> ' .
			'for more details.</p>';
		exit;
	}

	define( 'OOUI_DEMOS', true );

	$autoload = __DIR__ . '/vendor/autoload.php';
	if ( !file_exists( $autoload ) ) {
		echo '<p>Did you forget to run <code>composer install</code>?</p>';
		exit();
	}
	require_once $autoload;

	$theme = ( isset( $_GET['theme'] ) && $_GET['theme'] === 'apex' ) ? 'apex' : 'mediawiki';
	$themeClass = 'OOUI\\' . ( $theme === 'apex' ? 'Apex' : 'MediaWiki' ) . 'Theme';
	OOUI\Theme::setSingleton( new $themeClass() );

	$direction = ( isset( $_GET['direction'] ) && $_GET['direction'] === 'rtl' ) ? 'rtl' : 'ltr';
	$directionSuffix = $direction === 'rtl' ? '.rtl' : '';
	OOUI\Element::setDefaultDir( $direction );

	// We will require_once a file by this name later, so this validation is important
	$ok = array( 'widgets' );
	$page = ( isset( $_GET['page'] ) && in_array( $_GET['page'], $ok ) ) ? $_GET['page'] : 'widgets';

	$query = array(
		'theme' => $theme,
		'direction' => $direction,
		'page' => $page,
	);
	$styleFileName = "oojs-ui-core-$theme$directionSuffix.css";
	$styleFileNameImages = "oojs-ui-images-$theme$directionSuffix.css";
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta charset="UTF-8">
	<title>OOjs UI Widget Demo</title>
	<link rel="stylesheet" href="dist/<?php echo $styleFileName; ?>">
	<link rel="stylesheet" href="dist/<?php echo $styleFileNameImages; ?>">
	<link rel="stylesheet" href="styles/demo<?php echo $directionSuffix; ?>.css">
</head>
<body class="oo-ui-<?php echo $direction; ?>">
	<div class="oo-ui-demo">
		<div class="oo-ui-demo-menu">
			<?php
				echo new OOUI\ButtonGroupWidget( array(
					'infusable' => true,
					'items' => array(
						new OOUI\ButtonWidget( array(
							'label' => 'MediaWiki',
							'href' => '?' . http_build_query( array_merge( $query, array( 'theme' => 'mediawiki' ) ) ),
							'active' => $query['theme'] === 'mediawiki',
						) ),
						new OOUI\ButtonWidget( array(
							'label' => 'Apex',
							'href' => '?' . http_build_query( array_merge( $query, array( 'theme' => 'apex' ) ) ),
							'active' => $query['theme'] === 'apex',
						) ),
					)
				) );
				echo new OOUI\ButtonGroupWidget( array(
					'infusable' => true,
					'items' => array(
						new OOUI\ButtonWidget( array(
							'label' => 'LTR',
							'href' => '?' . http_build_query( array_merge( $query, array( 'direction' => 'ltr' ) ) ),
							'active' => $query['direction'] === 'ltr',
						) ),
						new OOUI\ButtonWidget( array(
							'label' => 'RTL',
							'href' => '?' . http_build_query( array_merge( $query, array( 'direction' => 'rtl' ) ) ),
							'active' => $query['direction'] === 'rtl',
						) ),
					)
				) );
				echo new OOUI\ButtonGroupWidget( array(
					'infusable' => true,
					'id' => 'oo-ui-demo-menu-infuse',
					'items' => array(
						new OOUI\ButtonWidget( array(
							'label' => 'JS',
							'href' => ".#$page-$theme-$direction",
							'active' => false,
						) ),
						new OOUI\ButtonWidget( array(
							'label' => 'PHP',
							'href' => '?' . http_build_query( $query ),
							'active' => true,
						) ),
					)
				) );
			?>
		</div>
		<?php
			// $page is validated above
			require_once "pages/$page.php";
		?>
	</div>

	<!-- Demonstrate JavaScript "infusion" of PHP widgets -->
	<script src="node_modules/jquery/dist/jquery.js"></script>
	<script src="node_modules/es5-shim/es5-shim.js"></script>
	<script src="node_modules/oojs/dist/oojs.jquery.js"></script>
	<script src="dist/oojs-ui-core.js"></script>
	<script src="dist/oojs-ui-<?php echo $theme; ?>.js"></script>
	<script src="infusion.js"></script>
</body>
</html>

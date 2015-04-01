<?php

class WikiEditorSeleniumConfig {

	public static function getSettings( &$includeFiles, &$globalConfigs ) {
		$includes = array(
			'extensions/Vector/Vector.php',
		    'extensions/WikiEditor/WikiEditor.php'
		);
		$configs = array(
			'wgDefaultSkin' => 'vector',
			'wgWikiEditorFeatures' => array(
				'toolbar' => array( 'global' => true, 'user' => true ),
				'dialogs' => array( 'global' => true, 'user' => true )
			),
			'wgVectorFeatures' => array(
				'editwarning' => array( 'global' => false, 'user' => false )
			)
		);
		$includeFiles = array_merge( $includeFiles, $includes );
		$globalConfigs = array_merge( $globalConfigs, $configs );
		return true;
	}
}

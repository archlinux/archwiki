<?php
/**
 * Cite extension hooks
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2016 Cite VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see MIT-LICENSE.txt
 */

class CiteHooks {
	/**
	 * Convert the content model of a message that is actually JSON to JSON. This
	 * only affects validation and UI when saving and editing, not loading the
	 * content.
	 *
	 * @param Title $title
	 * @param string $model
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
		if (
			$title->inNamespace( NS_MEDIAWIKI ) &&
			$title->getText() == 'Visualeditor-cite-tool-definition.json'
		) {
			$model = CONTENT_MODEL_JSON;
		}

		return true;
	}

	/**
	 * Conditionally register the unit testing module for the ext.cite.visualEditor module
	 * only if that module is loaded
	 *
	 * @param array $testModules The array of registered test modules
	 * @param ResourceLoader $resourceLoader The reference to the resource loader
	 * @return true
	 */
	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		$resourceModules = $resourceLoader->getConfig()->get( 'ResourceModules' );

		if (
			isset( $resourceModules[ 'ext.visualEditor.mediawiki' ] ) ||
			$resourceLoader->isModuleRegistered( 'ext.visualEditor.mediawiki' )
		) {
			$testModules['qunit']['ext.cite.visualEditor.test'] = array(
				'scripts' => array(
					'modules/ve-cite/tests/ve.dm.citeExample.js',
					'modules/ve-cite/tests/ve.dm.Converter.test.js',
					'modules/ve-cite/tests/ve.dm.InternalList.test.js',
					'modules/ve-cite/tests/ve.dm.Transaction.test.js',
				),
				'dependencies' => array(
					'ext.cite.visualEditor',
					'ext.visualEditor.test'
				),
				'localBasePath' => __DIR__,
				'remoteExtPath' => 'Cite',
			);
		}

		return true;
	}

	/**
	 * Callback for LinksUpdate hook
	 * Post-output processing of references property, for proper db storage
	 * Deferred to avoid performance overhead when outputting the page
	 *
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdate( LinksUpdate &$linksUpdate ) {
		global $wgCiteStoreReferencesData, $wgCiteCacheRawReferencesOnParse;
		if ( !$wgCiteStoreReferencesData ) {
			return;
		}
		$refData = $linksUpdate->getParserOutput()->getExtensionData( Cite::EXT_DATA_KEY );
		if ( $refData === null ) {
			return;
		}
		if ( $wgCiteCacheRawReferencesOnParse ) {
			// caching
			$cache = ObjectCache::getMainWANInstance();
			$articleID = $linksUpdate->getTitle()->getArticleID();
			$key = $cache->makeKey( Cite::EXT_DATA_KEY, $articleID );
			$cache->set( $key, $refData, Cite::CACHE_DURATION_ONPARSE );
		}
		// JSON encode
		$ppValue = FormatJson::encode( $refData, false, FormatJson::ALL_OK );
		// GZIP encode references data at maximum compression
		$ppValue = gzencode( $ppValue, 9 );
		// split the string in smaller parts that can fit into a db blob
		$ppValues = str_split( $ppValue, Cite::MAX_STORAGE_LENGTH );
		foreach ( $ppValues as $num => $ppValue ) {
			$key = 'references-' . intval( $num + 1 );
			$linksUpdate->mProperties[$key] = $ppValue;
		}
		$linksUpdate->getParserOutput()->setExtensionData( Cite::EXT_DATA_KEY, null );
	}

	/**
	 * Callback for LinksUpdateComplete hook
	 * If $wgCiteCacheRawReferencesOnParse is set to false, purges the cache
	 * when references are modified
	 *
	 * @param LinksUpdate $linksUpdate
	 */
	public static function onLinksUpdateComplete( LinksUpdate &$linksUpdate ) {
		global $wgCiteStoreReferencesData, $wgCiteCacheRawReferencesOnParse;
		if ( !$wgCiteStoreReferencesData || $wgCiteCacheRawReferencesOnParse ) {
			return;
		}
		// if we can, avoid clearing the cache when references were not changed
		if ( method_exists( $linksUpdate, 'getAddedProperties' )
			&& method_exists( $linksUpdate, 'getRemovedProperties' )
		) {
			$addedProps = $linksUpdate->getAddedProperties();
			$removedProps = $linksUpdate->getRemovedProperties();
			if ( !isset( $addedProps['references-1'] )
				&& !isset( $removedProps['references-1'] )
			) {
				return;
			}
		}
		$cache = ObjectCache::getMainWANInstance();
		$articleID = $linksUpdate->getTitle()->getArticleID();
		$key = $cache->makeKey( Cite::EXT_DATA_KEY, $articleID );
		// delete with reduced hold off period (LinksUpdate uses a master connection)
		$cache->delete( $key, WANObjectCache::MAX_COMMIT_DELAY );
	}
}

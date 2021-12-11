<?php

namespace MediaWiki\Extensions\ArchLinux;

use MediaWiki\MediaWikiServices;

class Hooks
{
    public static function onBeforePageDisplay(\OutputPage &$outputPage, \Skin &$skin)
    {
        $outputPage->addModuleStyles('zzz.ext.archLinux.styles');
    }

    public static function onAfterFinalPageOutput(\OutputPage $outputPage)
    {
        // Insert the navigation right after the <body> element
        $out = preg_replace(
            '/(<body[^>]*>)/s',
            '$1' . self::geArchNavBar($outputPage->getTitle()),
            ob_get_clean()
        );

        ob_start();
        echo $out;
        return true;
    }

    private static function geArchNavBar(string $title): string
    {
        $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig('archlinux');
        $archNavBar = $config->get("ArchNavBar");
        $archHome = $config->get("ArchHome");
        $archNavBarSelected = $config->get("ArchNavBarSelected");
        $archNavBarSelectedDefault = $config->get("ArchNavBarSelectedDefault");

        ob_start();
        include __DIR__ . '/ArchNavBar.php';
        return ob_get_clean();
    }
}

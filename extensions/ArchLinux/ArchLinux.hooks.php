<?php

namespace MediaWiki\Extensions\ArchLinux;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

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

    public static function onSkinAddFooterLinks(\Skin $skin, string $key, array &$footerlinks)
    {
        if ($key === 'places') {
            $linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

            $page_msg = $skin->msg('archwiki-code-of-conduct-page');
            $desc_msg = $skin->msg('archwiki-code-of-conduct-desc');
            if ($page_msg->exists() && $desc_msg->exists()) {
                $link_target = Title::newFromText($page_msg->inContentLanguage()->text());
                $link = $linkRenderer->makeLink($link_target, $desc_msg->text());
                $footerlinks['archwiki-code-of-conduct'] = $link;
            }

            $page_msg = $skin->msg('archwiki-terms-of-service-page');
            $desc_msg = $skin->msg('archwiki-terms-of-service-desc');
            if ($page_msg->exists() && $desc_msg->exists()) {
                $link_target = Title::newFromText($page_msg->inContentLanguage()->text());
                $link = $linkRenderer->makeLink($link_target, $desc_msg->text());
                $footerlinks['archwiki-terms-of-service'] = $link;
            }
        }
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

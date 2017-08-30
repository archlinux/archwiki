<?php

namespace MediaWiki\Extensions\ArchLinux;

use MediaWiki\MediaWikiServices;

class Hooks
{
    public static function onBeforePageDisplay(\OutputPage &$out, \Skin &$skin)
    {
        $out->addModuleStyles('zzz.ext.archLinux.styles');
    }

    public static function onSkinTemplateOutputPageBeforeExec(\SkinTemplate $skinTemplate, \QuickTemplate $tpl)
    {
        $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig('archlinux');
        $archNavBar = $config->get("ArchNavBar");
        $archHome = $config->get("ArchHome");
        $archNavBarSelected = $config->get("ArchNavBarSelected");
        $archNavBarSelectedDefault = $config->get("ArchNavBarSelectedDefault");

        ob_start();
        include __DIR__ . '/ArchNavBar.php';
        $tpl->set('headelement', $tpl->get('headelement') . ob_get_clean());
    }
}

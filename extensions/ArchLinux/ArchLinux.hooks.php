<?php

namespace MediaWiki\Extensions\ArchLinux;

class Hooks
{
    public static function onBeforePageDisplay(\OutputPage &$out, \Skin &$skin)
    {
        $out->addModuleStyles('zzz.ext.archLinux.archnavbar');
        $out->addModuleStyles('zzz.ext.archLinux.responsive');
        if ($out->getResourceLoader()->isModuleRegistered('zzz.ext.archLinux.skin.' . $skin->getSkinName())) {
            $out->addModuleStyles('zzz.ext.archLinux.skin.' . $skin->getSkinName());
        }
    }

    public static function onSkinTemplateOutputPageBeforeExec(\SkinTemplate $skinTemplate, \QuickTemplate $tpl)
    {
        ob_start();
        include __DIR__ . '/ArchNavBar.php';
        $tpl->set('headelement', $tpl->get('headelement') . ob_get_clean());
    }
}

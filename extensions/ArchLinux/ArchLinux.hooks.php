<?php

namespace MediaWiki\Extensions\ArchLinux;

class Hooks
{
    public static function onBeforePageDisplay(\OutputPage &$out, \Skin &$skin)
    {
        $out->addModuleStyles('zzz.ext.archLinux.styles');
    }

    public static function onSkinTemplateOutputPageBeforeExec(\SkinTemplate $skinTemplate, \QuickTemplate $tpl)
    {
        ob_start();
        include __DIR__ . '/ArchNavBar.php';
        $tpl->set('headelement', $tpl->get('headelement') . ob_get_clean());
    }
}

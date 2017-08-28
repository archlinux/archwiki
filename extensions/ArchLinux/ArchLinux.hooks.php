<?php

namespace MediaWiki\Extensions\ArchLinux;

class Hooks
{
    public static function onBeforePageDisplay(\OutputPage &$out, \Skin &$skin)
    {
        $out->addModuleStyles('zzz.ext.archLinux.archnavbar');
        switch ($skin->getSkinName()) {
            case 'vector':
                $out->addModuleStyles('zzz.ext.archLinux.vector');
                break;
            case 'monobook':
                $out->addModuleStyles('zzz.ext.archLinux.monobook');
                break;
        }
    }

    public static function onSkinTemplateOutputPageBeforeExec(\SkinTemplate $skinTemplate, \QuickTemplate $tpl)
    {
        ob_start();
        include __DIR__ . '/ArchNavBar.php';
        $tpl->set('headelement', $tpl->get('headelement') . ob_get_clean());
    }
}

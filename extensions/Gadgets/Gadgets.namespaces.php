<?php
$namespaceNames = array();

// For wikis without Gadgets installed.
if ( !defined( 'NS_GADGET' ) ) {
	define( 'NS_GADGET', 2300 );
	define( 'NS_GADGET_TALK', 2301 );
	define( 'NS_GADGET_DEFINITION', 2302 );
	define( 'NS_GADGET_DEFINITION_TALK', 2303 );
}

$namespaceNames['ar'] = array(
	NS_GADGET => 'إضافة',
	NS_GADGET_TALK => 'نقاش_الإضافة',
	NS_GADGET_DEFINITION => 'تعريف_الإضافة',
	NS_GADGET_DEFINITION_TALK => 'نقاش_تعريف_الإضافة',
);

$namespaceNames['azb'] = array(
	NS_GADGET => 'آلت',
	NS_GADGET_TALK => 'آلت_دانیشیغی',
	NS_GADGET_DEFINITION => 'آلت_آچیقلاماسی',
	NS_GADGET_DEFINITION_TALK => 'آلت_آچیقلاماسی_دانیشیغی',
);

$namespaceNames['bgn'] = array(
	NS_GADGET => 'وسیله_ئان',
	NS_GADGET_TALK => 'وسیله_ئان_ئی_گپ',
	NS_GADGET_DEFINITION => 'وسیله_ئانی_شرح',
	NS_GADGET_DEFINITION_TALK => 'وسیله_ئانی_شرح_ئی_گپ',
);

$namespaceNames['ckb'] = array(
	NS_GADGET => 'ئامراز',
	NS_GADGET_TALK => 'وتووێژی_ئامراز',
	NS_GADGET_DEFINITION => 'پێناسه‌ی_ئامراز',
	NS_GADGET_DEFINITION_TALK => 'وتووێژی_پێناسه‌ی_ئامراز',
);

$namespaceNames['de'] = array(
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Gadget_Diskussion',
	NS_GADGET_DEFINITION => 'Gadget-Definition',
	NS_GADGET_DEFINITION_TALK => 'Gadget-Definition_Diskussion',
);

$namespaceNames['en'] = array(
	NS_GADGET => 'Gadget',
	NS_GADGET_TALK => 'Gadget_talk',
	NS_GADGET_DEFINITION => 'Gadget_definition',
	NS_GADGET_DEFINITION_TALK => 'Gadget_definition_talk',
);

$namespaceNames['fa'] = array(
	NS_GADGET => 'ابزار',
	NS_GADGET_TALK => 'بحث_ابزار',
	NS_GADGET_DEFINITION => 'توضیحات_ابزار',
	NS_GADGET_DEFINITION_TALK => 'بحث_توضیحات_ابزار',
);

$namespaceNames['he'] = array(
	NS_GADGET => 'גאדג\'ט',
	NS_GADGET_TALK => 'שיחת_גאדג\'ט',
	NS_GADGET_DEFINITION => 'הגדרת_גאדג\'ט',
	NS_GADGET_DEFINITION_TALK => 'שיחת_הגדרת_גאדג\'ט',
);

$namespaceNames['it'] = array(
	NS_GADGET => 'Accessorio',
	NS_GADGET_TALK => 'Discussioni_accessorio',
	NS_GADGET_DEFINITION => 'Definizione_accessorio',
	NS_GADGET_DEFINITION_TALK => 'Discussioni_definizione_accessorio',
);

$namespaceNames['ko'] = array(
	NS_GADGET => '소도구',
	NS_GADGET_TALK => '소도구토론',
	NS_GADGET_DEFINITION => '소도구정의',
	NS_GADGET_DEFINITION_TALK => '소도구정의토론',
);

$namespaceNames['lrc'] = array(
	NS_GADGET => 'گأجئت',
	NS_GADGET_TALK => 'چأک_چئنە_گأجئت',
	NS_GADGET_DEFINITION => 'توضییا_گأجئت',
	NS_GADGET_DEFINITION_TALK => 'چأک_چئنە_توضییا_گأجئت',
);

$namespaceNames['mzn'] = array(
	NS_GADGET => 'گجت',
	NS_GADGET_TALK => 'گجت_گپ',
	NS_GADGET_DEFINITION => 'گجت_توضیحات',
	NS_GADGET_DEFINITION_TALK => 'گجت_توضیحات_گپ',
);

$namespaceNames['or'] = array(
	NS_GADGET => 'ଗ୍ୟାଜେଟ',
	NS_GADGET_TALK => 'ଗ୍ୟାଜେଟ_ଆଲୋଚନା',
	NS_GADGET_DEFINITION => 'ଗ୍ୟାଜେଟ_ସଂଜ୍ଞା',
	NS_GADGET_DEFINITION_TALK => 'ଗ୍ୟାଜେଟ_ସଂଜ୍ଞା_ଆଲୋଚନା',
);

$namespaceNames['pl'] = array(
	NS_GADGET => 'Gadżet',
	NS_GADGET_TALK => 'Dyskusja_gadżetu',
	NS_GADGET_DEFINITION => 'Definicja_gadżetu',
	NS_GADGET_DEFINITION_TALK => 'Dyskusja_definicji_gadżetu',
);

$namespaceNames['ur'] = array(
	NS_GADGET => 'آلہ',
	NS_GADGET_TALK => 'تبادلۂ_خیال_آلہ',
	NS_GADGET_DEFINITION => 'تعریف_آلہ',
	NS_GADGET_DEFINITION_TALK => 'تبادلۂ_خیال_تعریف_آلہ',
);

$namespaceNames['vi'] = array(
	NS_GADGET => 'Tiện_ích',
	NS_GADGET_TALK => 'Thảo_luận_Tiện_ích',
	NS_GADGET_DEFINITION => 'Định_nghĩa_tiện_ích',
	NS_GADGET_DEFINITION_TALK => 'Thảo_luận_Định_nghĩa_tiện_ích',
);

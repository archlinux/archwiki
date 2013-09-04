<?php
/**
 * Internationalisation file for extension ParserFunctions.
 *
 * @file
 * @ingroup Extensions
 */

$magicWords = array();

/** English (English) */
$magicWords['en'] = array(
	'expr' => array( 0, 'expr' ),
	'if' => array( 0, 'if' ),
	'ifeq' => array( 0, 'ifeq' ),
	'ifexpr' => array( 0, 'ifexpr' ),
	'iferror' => array( 0, 'iferror' ),
	'switch' => array( 0, 'switch' ),
	'default' => array( 0, '#default' ),
	'ifexist' => array( 0, 'ifexist' ),
	'time' => array( 0, 'time' ),
	'timel' => array( 0, 'timel' ),
	'rel2abs' => array( 0, 'rel2abs' ),
	'titleparts' => array( 0, 'titleparts' ),
	'len' => array( 0, 'len' ),
	'pos' => array( 0, 'pos' ),
	'rpos' => array( 0, 'rpos' ),
	'sub' => array( 0, 'sub' ),
	'count' => array( 0, 'count' ),
	'replace' => array( 0, 'replace' ),
	'explode' => array( 0, 'explode' ),
	'urldecode' => array( 0, 'urldecode' ),
);

/** Arabic (العربية) */
$magicWords['ar'] = array(
	'expr' => array( 0, 'تعبير' ),
	'if' => array( 0, 'لو' ),
	'ifeq' => array( 0, 'لومعادلة' ),
	'ifexpr' => array( 0, 'لوتعبير' ),
	'iferror' => array( 0, 'لوخطأ' ),
	'switch' => array( 0, 'تبديل' ),
	'default' => array( 0, '#افتراضي' ),
	'ifexist' => array( 0, 'لوموجود' ),
	'time' => array( 0, 'وقت' ),
	'timel' => array( 0, 'تيمل' ),
	'rel2abs' => array( 0, 'ريلتوآبس' ),
	'titleparts' => array( 0, 'أجزاء_العنوان' ),
	'len' => array( 0, 'لين' ),
	'pos' => array( 0, 'بوس' ),
	'rpos' => array( 0, 'آربوس' ),
	'sub' => array( 0, 'متفرع' ),
	'count' => array( 0, 'عدد' ),
	'replace' => array( 0, 'استبدال' ),
	'explode' => array( 0, 'انفجار' ),
	'urldecode' => array( 0, 'فك_مسار' ),
);

/** Egyptian Spoken Arabic (مصرى) */
$magicWords['arz'] = array(
	'expr' => array( 0, 'تعبير', 'expr' ),
	'if' => array( 0, 'لو', 'if' ),
	'ifeq' => array( 0, 'لومعادلة', 'ifeq' ),
	'ifexpr' => array( 0, 'لوتعبير', 'ifexpr' ),
	'iferror' => array( 0, 'لوخطأ', 'iferror' ),
	'switch' => array( 0, 'تبديل', 'switch' ),
	'default' => array( 0, '#افتراضي', '#default' ),
	'ifexist' => array( 0, 'لوموجود', 'ifexist' ),
	'time' => array( 0, 'وقت', 'time' ),
	'timel' => array( 0, 'تيمل', 'timel' ),
	'rel2abs' => array( 0, 'ريلتوآبس', 'rel2abs' ),
	'titleparts' => array( 0, 'أجزاء_العنوان', 'titleparts' ),
	'len' => array( 0, 'لين', 'len' ),
	'pos' => array( 0, 'بوس', 'pos' ),
	'rpos' => array( 0, 'آربوس', 'rpos' ),
	'sub' => array( 0, 'متفرع', 'sub' ),
	'count' => array( 0, 'عدد', 'count' ),
	'replace' => array( 0, 'استبدال', 'replace' ),
	'explode' => array( 0, 'انفجار', 'explode' ),
);

/** South Azerbaijani (تورکجه) */
$magicWords['azb'] = array(
	'ifeq' => array( 0, 'ایربیر' ),
	'ifexpr' => array( 0, 'ایرحساب' ),
	'iferror' => array( 0, 'ایریالنیش' ),
	'ifexist' => array( 0, 'ایراولسا' ),
);

/** Breton (brezhoneg) */
$magicWords['br'] = array(
	'time' => array( 0, 'amzer' ),
	'count' => array( 0, 'kontañ' ),
	'replace' => array( 0, 'erlec\'hiañ' ),
);

/** Chechen (нохчийн) */
$magicWords['ce'] = array(
	'time' => array( 0, 'хан', 'time' ),
	'replace' => array( 0, 'хийцарна', 'замена', 'replace' ),
);

/** Czech (česky) */
$magicWords['cs'] = array(
	'expr' => array( 0, 'výraz', 'expr' ),
	'if' => array( 0, 'když', 'if' ),
	'ifexist' => array( 0, 'kdyžexist', 'ifexist' ),
	'time' => array( 0, 'čas', 'time' ),
	'len' => array( 0, 'délka', 'len' ),
	'count' => array( 0, 'počet', 'count' ),
	'replace' => array( 0, 'nahradit', 'replace' ),
);

/** German (Deutsch) */
$magicWords['de'] = array(
	'switch' => array( 0, 'wechsle' ),
	'default' => array( 0, '#standard' ),
	'count' => array( 0, 'zähle' ),
	'replace' => array( 0, 'ersetze' ),
	'urldecode' => array( 0, 'URLDEKODIERT:' ),
);

/** Esperanto (Esperanto) */
$magicWords['eo'] = array(
	'expr' => array( 0, 'espr', 'esprimo' ),
	'if' => array( 0, 'se' ),
	'ifeq' => array( 0, 'seekv', 'seekvacio' ),
	'ifexpr' => array( 0, 'seespr', 'seeksprimo' ),
	'iferror' => array( 0, 'seeraras' ),
	'switch' => array( 0, 'ŝaltu', 'ŝalti', 'sxaltu', 'sxalti' ),
	'default' => array( 0, '#apriore', '#defaŭlte', '#defauxlte' ),
	'ifexist' => array( 0, 'seekzistas' ),
	'time' => array( 0, 'tempo' ),
	'timel' => array( 0, 'tempoo' ),
	'len' => array( 0, 'lungo' ),
	'replace' => array( 0, 'anstataŭigi' ),
);

/** Spanish (español) */
$magicWords['es'] = array(
	'if' => array( 0, 'si' ),
	'ifexpr' => array( 0, 'siexpr' ),
	'iferror' => array( 0, 'sierror' ),
	'switch' => array( 0, 'según' ),
	'default' => array( 0, '#predeterminado' ),
	'ifexist' => array( 0, 'siexiste' ),
	'time' => array( 0, 'tiempo' ),
	'len' => array( 0, 'long', 'longitud' ),
	'replace' => array( 0, 'reemplazar' ),
	'explode' => array( 0, 'separar' ),
);

/** Persian (فارسی) */
$magicWords['fa'] = array(
	'expr' => array( 0, 'حساب' ),
	'if' => array( 0, 'اگر' ),
	'ifeq' => array( 0, 'اگرمساوی' ),
	'ifexpr' => array( 0, 'اگرحساب' ),
	'iferror' => array( 0, 'اگرخطا' ),
	'switch' => array( 0, 'گزینه' ),
	'default' => array( 0, '#پیش‌فرض' ),
	'ifexist' => array( 0, 'اگرموجود' ),
	'time' => array( 0, 'زمان' ),
	'timel' => array( 0, 'زمان‌بلند' ),
	'rel2abs' => array( 0, 'نسبی‌به‌مطلق' ),
	'titleparts' => array( 0, 'پاره‌عنوان' ),
	'len' => array( 0, 'طول' ),
	'pos' => array( 0, 'جا' ),
	'rpos' => array( 0, 'جار' ),
	'sub' => array( 0, 'تکه' ),
	'count' => array( 0, 'شمار' ),
	'replace' => array( 0, 'جایگزین' ),
	'explode' => array( 0, 'گسترش' ),
	'urldecode' => array( 0, 'نشانی‌بی‌کد' ),
);

/** Hebrew (עברית) */
$magicWords['he'] = array(
	'expr' => array( 0, 'חשב' ),
	'if' => array( 0, 'תנאי' ),
	'ifeq' => array( 0, 'שווה' ),
	'ifexpr' => array( 0, 'חשב תנאי' ),
	'iferror' => array( 0, 'תנאי שגיאה' ),
	'switch' => array( 0, 'בחר' ),
	'default' => array( 0, '#ברירת מחדל' ),
	'ifexist' => array( 0, 'קיים' ),
	'time' => array( 0, 'זמן' ),
	'timel' => array( 0, 'זמןמ' ),
	'rel2abs' => array( 0, 'יחסי למוחלט' ),
	'titleparts' => array( 0, 'חלק בכותרת' ),
	'count' => array( 0, 'מספר' ),
);

/** Hungarian (magyar) */
$magicWords['hu'] = array(
	'expr' => array( 0, 'kif', 'expr' ),
	'if' => array( 0, 'ha', 'if' ),
	'ifeq' => array( 0, 'haegyenlő', 'ifeq' ),
	'ifexpr' => array( 0, 'hakif', 'ifexpr' ),
	'iferror' => array( 0, 'hahibás', 'iferror' ),
	'default' => array( 0, '#alapértelmezett', '#default' ),
	'ifexist' => array( 0, 'halétezik', 'ifexist' ),
	'time' => array( 0, 'idő', 'time' ),
	'len' => array( 0, 'hossz', 'len' ),
	'pos' => array( 0, 'pozíció', 'pos' ),
	'rpos' => array( 0, 'jpozíció', 'rpos' ),
);

/** Indonesian (Bahasa Indonesia) */
$magicWords['id'] = array(
	'expr' => array( 0, 'hitung', 'expr' ),
	'if' => array( 0, 'jika', 'if' ),
	'ifeq' => array( 0, 'jikasama', 'ifeq' ),
	'ifexpr' => array( 0, 'jikahitung', 'ifexpr' ),
	'iferror' => array( 0, 'jikasalah', 'iferror' ),
	'switch' => array( 0, 'pilih', 'switch' ),
	'default' => array( 0, '#baku', '#default' ),
	'ifexist' => array( 0, 'jikaada', 'ifexist' ),
	'time' => array( 0, 'waktu', 'time' ),
	'timel' => array( 0, 'waktu1', 'timel' ),
	'titleparts' => array( 0, 'bagianjudul', 'titleparts' ),
);

/** Igbo (Igbo) */
$magicWords['ig'] = array(
	'if' => array( 0, 'ȯ_bú', 'if' ),
	'time' => array( 0, 'ógè', 'time' ),
	'timel' => array( 0, 'ógèl', 'timel' ),
);

/** Italian (italiano) */
$magicWords['it'] = array(
	'expr' => array( 0, 'espr' ),
	'if' => array( 0, 'se' ),
	'ifeq' => array( 0, 'seeq' ),
	'ifexpr' => array( 0, 'seespr' ),
	'iferror' => array( 0, 'seerrore' ),
	'ifexist' => array( 0, 'seesiste' ),
	'time' => array( 0, 'tempo' ),
	'titleparts' => array( 0, 'patititolo' ),
	'count' => array( 0, 'conto' ),
	'replace' => array( 0, 'sostituisci' ),
);

/** Japanese (日本語) */
$magicWords['ja'] = array(
	'expr' => array( 0, '式' ),
	'if' => array( 0, 'もし' ),
	'ifeq' => array( 0, 'もし等しい' ),
	'ifexpr' => array( 0, 'もし式' ),
	'iferror' => array( 0, 'もしエラー' ),
	'switch' => array( 0, '切り替え' ),
	'default' => array( 0, '#既定' ),
	'ifexist' => array( 0, 'もし存在' ),
	'time' => array( 0, '時間' ),
	'timel' => array( 0, '時間地方' ),
	'rel2abs' => array( 0, '参照から絶対' ),
	'titleparts' => array( 0, 'タイトル部分' ),
	'len' => array( 0, '長さ' ),
	'pos' => array( 0, '位置' ),
	'rpos' => array( 0, '最後の位置' ),
	'sub' => array( 0, '切り取り' ),
	'count' => array( 0, '回数' ),
	'replace' => array( 0, '置き換え' ),
	'explode' => array( 0, '分割' ),
	'urldecode' => array( 0, 'URLデコード' ),
);

/** Korean (한국어) */
$magicWords['ko'] = array(
	'expr' => array( 0, '수식' ),
	'if' => array( 0, '만약' ),
	'ifeq' => array( 0, '만약일치' ),
	'ifexpr' => array( 0, '만약계산' ),
	'iferror' => array( 0, '만약오류' ),
	'switch' => array( 0, '스위치' ),
	'default' => array( 0, '#기본값' ),
	'ifexist' => array( 0, '만약존재' ),
	'time' => array( 0, '시간' ),
	'timel' => array( 0, '현지시간' ),
	'rel2abs' => array( 0, '상대를절대로' ),
	'titleparts' => array( 0, '제목부분' ),
	'len' => array( 0, '길이' ),
	'pos' => array( 0, '위치' ),
	'rpos' => array( 0, '오른위치' ),
	'sub' => array( 0, '자르기' ),
	'count' => array( 0, '개수' ),
	'replace' => array( 0, '바꾸기', '교체' ),
	'explode' => array( 0, '분리' ),
	'urldecode' => array( 0, '주소디코딩:' ),
);

/** Kurdish (Latin script) (Kurdî (latînî)‎) */
$magicWords['ku-latn'] = array(
	'len' => array( 0, '#ziman' ),
);

/** Cornish (kernowek) */
$magicWords['kw'] = array(
	'if' => array( 0, 'mar' ),
	'time' => array( 0, 'termyn' ),
);

/** Ladino (Ladino) */
$magicWords['lad'] = array(
	'switch' => array( 0, 'asegún', 'según', 'switch' ),
);

/** Malagasy (Malagasy) */
$magicWords['mg'] = array(
	'if' => array( 0, 'raha', 'if' ),
	'ifeq' => array( 0, 'rahamitovy', 'ifeq' ),
	'ifexpr' => array( 0, 'rahamarina', 'ifexpr' ),
	'iferror' => array( 0, 'rahadiso', 'iferror' ),
	'default' => array( 0, '#tsipalotra', '#default' ),
	'ifexist' => array( 0, 'rahamisy', 'ifexist' ),
	'time' => array( 0, 'lera', 'time' ),
);

/** Macedonian (македонски) */
$magicWords['mk'] = array(
	'expr' => array( 0, 'израз' ),
	'if' => array( 0, 'ако' ),
	'ifeq' => array( 0, 'акоисто' ),
	'ifexpr' => array( 0, 'акоизраз' ),
	'iferror' => array( 0, 'акогрешка' ),
	'switch' => array( 0, 'префрли' ),
	'default' => array( 0, '#поосновно' ),
	'ifexist' => array( 0, 'акопостои' ),
	'time' => array( 0, 'време' ),
	'timel' => array( 0, 'времел' ),
	'rel2abs' => array( 0, 'релдоапс' ),
	'titleparts' => array( 0, 'насловделови' ),
	'len' => array( 0, 'долж' ),
	'pos' => array( 0, 'пол' ),
	'rpos' => array( 0, 'впол' ),
	'sub' => array( 0, 'зам' ),
	'count' => array( 0, 'сметај' ),
	'replace' => array( 0, 'замени' ),
	'explode' => array( 0, 'разложи' ),
	'urldecode' => array( 0, 'urlдекод' ),
);

/** Malayalam (മലയാളം) */
$magicWords['ml'] = array(
	'if' => array( 0, 'എങ്കിൽ' ),
	'ifeq' => array( 0, 'സമെമെങ്കിൽ' ),
	'ifexpr' => array( 0, 'എക്സ്പ്രെഷനെങ്കിൽ' ),
	'iferror' => array( 0, 'പിഴവെങ്കിൽ' ),
	'switch' => array( 0, 'മാറ്റുക' ),
	'default' => array( 0, '#സ്വതേ' ),
	'ifexist' => array( 0, 'ഉണ്ടെങ്കിൽ' ),
	'time' => array( 0, 'സമയം' ),
	'timel' => array( 0, 'സമയം|' ),
	'sub' => array( 0, 'ഉപം' ),
	'count' => array( 0, 'എണ്ണുക' ),
	'replace' => array( 0, 'മാറ്റിച്ചേർക്കുക' ),
	'explode' => array( 0, 'വിസ്ഫോടനം' ),
);

/** Marathi (मराठी) */
$magicWords['mr'] = array(
	'expr' => array( 0, 'करण' ),
	'if' => array( 0, 'जर', 'इफ' ),
	'ifeq' => array( 0, 'जरसम' ),
	'ifexpr' => array( 0, 'जरकरण' ),
	'iferror' => array( 0, 'जरत्रुटी' ),
	'switch' => array( 0, 'कळ', 'सांगकळ', 'असेलतरसांग', 'असलेतरसांग', 'स्वीच' ),
	'default' => array( 0, '#अविचल' ),
	'ifexist' => array( 0, 'जरअसेल', 'जरआहे' ),
	'time' => array( 0, 'वेळ' ),
	'timel' => array( 0, 'वेळस्था' ),
	'titleparts' => array( 0, 'शीर्षकखंड', 'टाइटलपार्ट्स' ),
	'len' => array( 0, 'लांबी' ),
	'pos' => array( 0, 'स्थशोध' ),
	'rpos' => array( 0, 'माग्चास्थशोध' ),
	'sub' => array( 0, 'उप' ),
	'count' => array( 0, 'मोज', 'मोजा' ),
	'replace' => array( 0, 'नेबदल', 'रिप्लेस' ),
	'explode' => array( 0, 'एकफोड' ),
);

/** Low Saxon (Netherlands) (Nedersaksies) */
$magicWords['nds-nl'] = array(
	'if' => array( 0, 'as', 'als' ),
	'ifeq' => array( 0, 'asgelieke', 'alsgelijk' ),
	'ifexpr' => array( 0, 'asexpressie', 'alsexpressie' ),
	'iferror' => array( 0, 'asfout', 'alsfout' ),
	'default' => array( 0, '#standard', '#standaard' ),
	'ifexist' => array( 0, 'asbesteet', 'alsbestaat' ),
	'time' => array( 0, 'tied', 'tijd' ),
	'timel' => array( 0, 'tiedl', 'tijdl' ),
	'rel2abs' => array( 0, 'relatiefnaorabseluut', 'relatiefnaarabsoluut' ),
);

/** Dutch (Nederlands) */
$magicWords['nl'] = array(
	'expr' => array( 0, 'expressie' ),
	'if' => array( 0, 'als' ),
	'ifeq' => array( 0, 'alsgelijk' ),
	'ifexpr' => array( 0, 'alsexpressie' ),
	'iferror' => array( 0, 'alsfout' ),
	'switch' => array( 0, 'schakelen' ),
	'default' => array( 0, '#standaard' ),
	'ifexist' => array( 0, 'alsbestaat' ),
	'time' => array( 0, 'tijd' ),
	'timel' => array( 0, 'tijdl' ),
	'rel2abs' => array( 0, 'relatiefnaarabsoluut' ),
	'titleparts' => array( 0, 'paginanaamdelen' ),
	'count' => array( 0, 'telling' ),
	'replace' => array( 0, 'vervangen' ),
	'explode' => array( 0, 'exploderen' ),
	'urldecode' => array( 0, 'urldecoderen' ),
);

/** Norwegian Nynorsk (norsk nynorsk) */
$magicWords['nn'] = array(
	'expr' => array( 0, 'uttrykk' ),
	'if' => array( 0, 'om' ),
	'ifeq' => array( 0, 'omlik' ),
	'ifexpr' => array( 0, 'omuttrykk' ),
	'iferror' => array( 0, 'omfeil' ),
	'switch' => array( 0, 'byt' ),
	'ifexist' => array( 0, 'omfinst' ),
	'time' => array( 0, 'tid' ),
	'timel' => array( 0, 'tidl' ),
	'rel2abs' => array( 0, 'reltilabs' ),
	'titleparts' => array( 0, 'titteldelar' ),
	'len' => array( 0, 'lengd' ),
	'replace' => array( 0, 'erstatt' ),
);

/** Oriya (ଓଡ଼ିଆ) */
$magicWords['or'] = array(
	'time' => array( 0, 'ସମୟ' ),
);

/** Punjabi (ਪੰਜਾਬੀ) */
$magicWords['pa'] = array(
	'time' => array( 0, 'ਸਮੇ' ),
);

/** Pashto (پښتو) */
$magicWords['ps'] = array(
	'if' => array( 0, 'که', 'if' ),
	'time' => array( 0, 'وخت', 'time' ),
	'count' => array( 0, 'شمېرل', 'count' ),
);

/** Portuguese (português) */
$magicWords['pt'] = array(
	'if' => array( 0, 'se', 'if' ),
	'ifeq' => array( 0, 'seigual', 'ifeq' ),
	'ifexpr' => array( 0, 'seexpr', 'ifexpr' ),
	'iferror' => array( 0, 'seerro', 'iferror' ),
	'default' => array( 0, '#padrão', '#padrao', '#default' ),
	'ifexist' => array( 0, 'seexiste', 'ifexist' ),
	'titleparts' => array( 0, 'partesdotítulo', 'partesdotitulo', 'titleparts' ),
	'len' => array( 0, 'comprimento', 'len' ),
);

/** Russian (русский) */
$magicWords['ru'] = array(
	'if' => array( 0, 'если' ),
	'iferror' => array( 0, 'еслиошибка' ),
	'switch' => array( 0, 'переключатель' ),
	'default' => array( 0, '#умолчание' ),
	'time' => array( 0, 'время' ),
	'timel' => array( 0, 'мвремя' ),
	'replace' => array( 0, 'замена' ),
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎) */
$magicWords['sr-ec'] = array(
	'default' => array( 0, '#подразумевано' ),
	'time' => array( 0, 'време' ),
	'pos' => array( 0, 'поз' ),
	'count' => array( 0, 'број' ),
	'replace' => array( 0, 'замени' ),
);

/** Swedish (svenska) */
$magicWords['sv'] = array(
	'expr' => array( 0, 'utr', 'expr' ),
	'if' => array( 0, 'om', 'if' ),
	'ifeq' => array( 0, 'omlika', 'ifeq' ),
	'ifexpr' => array( 0, 'omutr', 'ifexpr' ),
	'iferror' => array( 0, 'omfel', 'iferror' ),
	'switch' => array( 0, 'växel', 'switch' ),
	'default' => array( 0, '#standard', '#default' ),
	'ifexist' => array( 0, 'omfinns', 'ifexist' ),
	'time' => array( 0, 'tid', 'time' ),
	'timel' => array( 0, 'tidl', 'timel' ),
	'replace' => array( 0, 'ersätt', 'replace' ),
	'explode' => array( 0, 'explodera', 'explode' ),
);

/** Tamil (தமிழ்) */
$magicWords['ta'] = array(
	'count' => array( 0, 'எண்ணிக்கை' ),
);

/** Turkish (Türkçe) */
$magicWords['tr'] = array(
	'expr' => array( 0, 'işlem', 'islem', 'ifade' ),
	'if' => array( 0, 'eğer', 'eger' ),
	'switch' => array( 0, 'değiştir', 'degistir' ),
	'default' => array( 0, '#vas' ),
);

/** Ukrainian (українська) */
$magicWords['uk'] = array(
	'expr' => array( 0, 'вираз' ),
	'if' => array( 0, 'якщо' ),
	'ifeq' => array( 0, 'якщорівні', 'рівні' ),
	'ifexpr' => array( 0, 'якщовираз' ),
	'iferror' => array( 0, 'якщопомилка' ),
	'switch' => array( 0, 'вибірка' ),
	'default' => array( 0, '#інакше' ),
	'ifexist' => array( 0, 'якщоіснує' ),
	'replace' => array( 0, 'заміна' ),
);

/** Urdu (اردو) */
$magicWords['ur'] = array(
	'if' => array( 0, 'اگر' ),
);

/** Uzbek (oʻzbekcha) */
$magicWords['uz'] = array(
	'expr' => array( 0, 'ifoda' ),
	'if' => array( 0, 'agar' ),
	'ifeq' => array( 0, 'agarteng' ),
	'ifexpr' => array( 0, 'agarifoda' ),
	'iferror' => array( 0, 'agarxato' ),
	'switch' => array( 0, 'tanlov' ),
	'default' => array( 0, '#boshlangʻich' ),
	'ifexist' => array( 0, 'agarbor' ),
	'time' => array( 0, 'vaqt' ),
	'len' => array( 0, 'uzunlik' ),
	'pos' => array( 0, 'oʻrin' ),
	'count' => array( 0, 'miqdor' ),
	'replace' => array( 0, 'almashtirish' ),
);

/** Vietnamese (Tiếng Việt) */
$magicWords['vi'] = array(
	'expr' => array( 0, 'côngthức' ),
	'if' => array( 0, 'nếu' ),
	'ifeq' => array( 0, 'nếubằng' ),
	'ifexpr' => array( 0, 'nếucôngthức' ),
	'iferror' => array( 0, 'nếulỗi' ),
	'default' => array( 0, '#mặcđịnh' ),
	'ifexist' => array( 0, 'nếutồntại' ),
	'time' => array( 0, 'giờ' ),
	'timel' => array( 0, 'giờđịaphương' ),
	'len' => array( 0, 'sốchữ', 'sốkýtự', 'sốkítự' ),
	'pos' => array( 0, 'vịtrí' ),
	'rpos' => array( 0, 'vịtríphải' ),
	'sub' => array( 0, 'chuỗicon' ),
	'count' => array( 0, 'số' ),
	'replace' => array( 0, 'thaythế' ),
	'urldecode' => array( 0, 'giảimãurl' ),
);

/** Yiddish (ייִדיש) */
$magicWords['yi'] = array(
	'expr' => array( 0, 'רעכן' ),
	'if' => array( 0, 'תנאי' ),
	'ifeq' => array( 0, 'גלייך' ),
	'ifexpr' => array( 0, 'אויברעכן' ),
	'switch' => array( 0, 'קלייב' ),
	'default' => array( 0, '#גרונט' ),
	'ifexist' => array( 0, 'עקזיסט' ),
	'time' => array( 0, 'צייט' ),
	'timel' => array( 0, 'צייטל' ),
	'count' => array( 0, 'צאל' ),
);

/** Chinese (中文) */
$magicWords['zh'] = array(
	'expr' => array( 0, '计算式' ),
	'if' => array( 0, '非空式' ),
	'ifeq' => array( 0, '相同式', '匹配式' ),
	'iferror' => array( 0, '错误式' ),
	'switch' => array( 0, '多选式', '多条件式', '双射式' ),
	'default' => array( 0, '#默认' ),
	'ifexist' => array( 0, '存在式' ),
	'len' => array( 0, '长度' ),
	'pos' => array( 0, '位置' ),
	'rpos' => array( 0, '最近位置' ),
	'sub' => array( 0, '截取' ),
	'count' => array( 0, '计数' ),
	'replace' => array( 0, '替换' ),
	'explode' => array( 0, '爆炸', '炸开' ),
);
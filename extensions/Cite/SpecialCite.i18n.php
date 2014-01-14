<?php
/**
 * Internationalisation file for Cite special page extension.
 *
 * @file
 * @ingroup Extensions
*/

$messages = array();

$messages['en'] = array(
	'cite_article_desc'       => 'Adds a [[Special:Cite|citation]] special page and toolbox link',
	'cite_article_link'       => 'Cite this page',
	'tooltip-cite-article'    => 'Information on how to cite this page',
	'accesskey-cite-article'  => '', # Do not translate this
	'cite'                    => 'Cite',
	'cite-summary'            => '', # Do not translate this
	'cite_page'               => 'Page:',
	'cite_submit'             => 'Cite',
	'cite_text'               => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliographic details for {{FULLPAGENAME}} ==

* Page name: {{FULLPAGENAME}}
* Author: {{SITENAME}} contributors
* Publisher: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Date of last revision: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Date retrieved: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanent URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Page Version ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citation styles for {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE style]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may be preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Message documentation (Message documentation)
 * @author Jon Harald Søby
 * @author Lloffiwr
 * @author Shirayuki
 * @author Siddhartha Ghai
 * @author Siebrand
 * @author Tgr
 * @author Umherirrender
 */
$messages['qqq'] = array(
	'cite_article_desc' => '{{desc|name=Special Cite|url=http://www.mediawiki.org/wiki/Extension:Cite/Special:Cite.php}}',
	'cite_article_link' => 'Text of link in toolbox

See also:
* {{msg-mw|Cite article link}}
* {{msg-mw|Accesskey-cite-article}}
* {{msg-mw|Tooltip-cite-article}}',
	'tooltip-cite-article' => 'Used as tooltip for the link {{msg-mw|Cite article link}}.

See also:
* {{msg-mw|Cite article link}}
* {{msg-mw|Accesskey-cite-article}}
* {{msg-mw|Tooltip-cite-article}}',
	'accesskey-cite-article' => '{{doc-accesskey}}
See also:
* {{msg-mw|Cite article link}}
* {{msg-mw|Accesskey-cite-article}}
* {{msg-mw|Tooltip-cite-article}}',
	'cite' => '{{doc-special|Cite|unlisted=1}}
{{Identical|Cite}}',
	'cite-summary' => '{{notranslate}}',
	'cite_page' => '{{Identical|Page}}',
	'cite_submit' => '{{Identical|Cite}}',
	'cite_text' => 'Refers to {{msg-mw|Sitesubtitle}}.

* This message is the entire text for the page Special:Cite
* Any wikilinks in this message point to pages on the wiki, so they may be translated.
* Do not translate magic words like CURRENTYEAR, SITENAME etc.
* Do not translate the parameter names (author, title etc.) for BibTeX entries.
* Do not translate the div class plainlinks mw-specialcite-styles.',
);

/** Achinese (Acèh)
 * @author Si Gam Acèh
 */
$messages['ace'] = array(
	'cite_article_link' => 'Cok ôn nyoë',
);

/** Afrikaans (Afrikaans)
 * @author Naudefj
 * @author SPQRobin
 */
$messages['af'] = array(
	'cite_article_desc' => "Maak 'n [[Special:Cite|spesiale bladsy vir sitasie]], en 'n skakel daarna in hulpmiddels beskikbaar",
	'cite_article_link' => 'Haal dié blad aan',
	'tooltip-cite-article' => 'Inligting oor hoe u hierdie bladsy kan citeer',
	'cite' => 'Aanhaling',
	'cite_page' => 'Bladsy:',
	'cite_submit' => 'Aanhaling',
);

/** Amharic (አማርኛ)
 * @author Codex Sinaiticus
 * @author Teferra
 */
$messages['am'] = array(
	'cite_article_link' => 'ይህንን ገጽ አጣቅስ',
	'cite' => 'መጥቀሻ',
	'cite_page' => 'አርዕስት፦',
	'cite_submit' => 'ዝርዝሮች ይታዩ',
);

/** Aragonese (aragonés)
 * @author Juanpabl
 */
$messages['an'] = array(
	'cite_article_desc' => 'Adibe un vinclo y una pachina especial de [[Special:Cite|cita]]',
	'cite_article_link' => 'Citar ista pachina',
	'tooltip-cite-article' => 'Información de como citar ista pachina',
	'cite' => 'Citar',
	'cite_page' => 'Pachina:',
	'cite_submit' => 'Citar',
);

/** Arabic (العربية)
 * @author Meno25
 * @author OsamaK
 */
$messages['ar'] = array(
	'cite_article_desc' => 'يضيف صفحة [[Special:Cite|استشهاد]] خاصة ووصلة صندوق أدوات',
	'cite_article_link' => 'استشهد بهذه الصفحة',
	'tooltip-cite-article' => 'معلومات عن كيفية الاستشهاد بالصفحة',
	'cite' => 'استشهاد',
	'cite_page' => 'الصفحة:',
	'cite_submit' => 'استشهاد',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== تفاصيل التأليف ل{{FULLPAGENAME}} ==

* اسم الصفحة: {{FULLPAGENAME}}
* المؤلف: مساهمو {{SITENAME}}
* الناشر: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* تاريخ آخر مراجعة: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* تاريخ الاسترجاع: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* وصلة دائمة: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* رقم نسخة الصفحة: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== أنماط الاستشهاد ل{{FULLPAGENAME}} ==

=== [[APA style|نمط APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|نمط MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|نمط MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|نمط شيكاغو]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|نمط CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|نمط Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== مدخلة [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

عند استخدام وصلة مجموعة [[LaTeX]] (<code>\\usepackage{url}</code> في مكان ما) مما يؤدي إى إعطاء عناوين ويب مهيأة بشكل أفضل، التالي ربما يكون مفضلا:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Aramaic (ܐܪܡܝܐ)
 * @author Basharh
 */
$messages['arc'] = array(
	'cite_article_link' => 'ܡܣܗܕ ܥܠ ܗܕܐ ܦܐܬܐ',
	'tooltip-cite-article' => 'ܝܕ̈ܥܬܐ ܥܠ ܐܝܟܢܐ ܕܡܣܗܕ ܥܠ ܦܐܬܐ',
	'cite' => 'ܡܣܗܕ',
	'cite_page' => 'ܦܐܬܐ:',
	'cite_submit' => 'ܡܣܗܕ',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== ܐܪ̈ܝܟܬܐ ܕܦܘܓܪܦܐ ܕ {{FULLPAGENAME}} ==

* ܫܡܐ ܕܦܐܬܐ: {{FULLPAGENAME}}
* ܣܝܘܡܐ: ܫܘܬܦܢ̈ܐ ܕ {{SITENAME}}
* ܡܦܪܣܐ: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* ܣܝܩܘܡܐ ܕܬܢܝܬܐ ܐܚܪܝܬܐ: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* ܣܝܩܘܡܐ ܕܡܬܦܢܝܢܘܬܐ: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* ܐܣܘܪܐ ܦܝܘܫܐ: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ܗܝܝܘܬܐ ܕܨܚܚܐ ܕܦܐܬܐ: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== ܙܢܝ̈ܐ ܕܡܣܗܕܬܐ ܕ {{FULLPAGENAME}} ==

=== [[ܙܢܐ ܕ APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|ܙܢܐ ܕ MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|ܙܢܐ ܕ MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style| ܙܢܐ ܕ Chicago]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|ܙܢܐ ܕ CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|ܙܢܐ ܕ Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== ܡܥܠܬܐ ܕ [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may be preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Mapuche (mapudungun)
 * @author Kaniw
 * @author Remember the dot
 */
$messages['arn'] = array(
	'cite_article_desc' => 'Yomvmi kiñe wicu aztapvl ñi [[Special:Cite|konvmpan]] mew ka jasun kvzawpeyvm mew',
	'cite_article_link' => 'Konvmpape faci xoy',
	'tooltip-cite-article' => 'Cumley konvmpageay faci xoy',
	'cite' => 'Konvmpan',
	'cite_page' => 'Aztapvl:',
	'cite_submit' => 'Konvmpan',
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Ghaly
 * @author Ramsis II
 */
$messages['arz'] = array(
	'cite_article_desc' => 'بيضيف [[Special:Cite|مرجع]] صفحة مخصوصة ولينك لصندوء أدوات',
	'cite_article_link' => 'استشهد بالصفحة دى',
	'cite' => 'مرجع',
	'cite_page' => 'الصفحه:',
	'cite_submit' => 'مرجع',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== تفاصيل التأليف ل{{FULLPAGENAME}} ==

* اسم الصفحة: {{FULLPAGENAME}}
* المؤلف: مساهمو {{SITENAME}}
* الناشر: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* تاريخ آخر مراجعة: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* تاريخ الاسترجاع: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* وصلة دائمة: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* رقم نسخة الصفحة: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">


== أنماط الاستشهاد ل{{FULLPAGENAME}} ==

=== [[APA style|نمط APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|نمط MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|نمط MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|نمط شيكاغو]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|نمط CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|نمط Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== مدخلة [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

عند استخدام وصلة مجموعة [[LaTeX]] (<code>\\usepackage{url}</code> في مكان ما) مما يؤدى إلى إعطاء عناوين ويب مهيأة بشكل أفضل، التالى ربما يكون مفضلا:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Assamese (অসমীয়া)
 * @author Bishnu Saikia
 * @author Gitartha.bordoloi
 */
$messages['as'] = array(
	'cite_article_desc' => 'এখন [[Special:Cite|উদ্ধৃতি]] পৃষ্ঠা আৰু এটা টুলবক্স লিংক যোগ কৰে',
	'cite_article_link' => 'এই পৃষ্ঠাৰ উদ্ধৃতি দিয়ক',
	'tooltip-cite-article' => 'এই পৃষ্ঠাখনৰ উদ্ধৃতি দিয়াৰ বিষয়ে তথ্য',
	'cite' => '↓উদ্ধৃত',
	'cite_page' => 'পৃষ্ঠা:',
	'cite_submit' => '↓উদ্ধৃত',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} জীৱনীমূলক তথ্য ==

* পৃষ্ঠাৰ নাম: {{FULLPAGENAME}}
* লিখক: {{SITENAME}} contributors
* প্ৰকাশক: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* অন্তিম সংস্কৰণৰ তাৰিখ: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} ইউ.টি.ছি.
* আহৰণৰ তাৰিখ: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* স্থায়ী ইউ.আৰ.এল.: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* পৃষ্ঠাৰ সংস্কৰণৰ আই.ডি.: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}}ৰ বাবে উদ্ধৃতি সজ্জা ==

=== [[APA style|APA সজ্জা]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. আহৰণ <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> পৰা {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA সজ্জা]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA সজ্জা]] ===
{{SITENAME}} বৰঙনিদাতাসকল, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|চিকাগো সজ্জা]] ===
{{SITENAME}} বৰঙনিদাতাসকল, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (আহৰণ <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE সজ্জা]] ===
{{SITENAME}} বৰঙনিদাতাসকল. {{FULLPAGENAME}} [ইণ্টাৰনেট]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [উদ্ধৃত <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. উপলদ্ধ :
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook সজ্জা]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (অন্তিম পৰিদৰ্শন <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[অনলাইন; আহৰিত <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may be preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[অনলাইন; আহৰিত <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Asturian (asturianu)
 * @author Esbardu
 * @author Xuacu
 */
$messages['ast'] = array(
	'cite_article_desc' => 'Añade una páxina especial de [[Special:Cite|cites]] y un enllaz a la caxa de ferramientes',
	'cite_article_link' => 'Citar esta páxina',
	'tooltip-cite-article' => 'Información tocante a cómo citar esta páxina',
	'cite' => 'Citar',
	'cite_page' => 'Páxina:',
	'cite_submit' => 'Citar',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Datos bibliográficos pa {{FULLPAGENAME}} ==

* Nome de la páxina: {{FULLPAGENAME}}
* Autor: collaboradores de {{SITENAME}}
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data de la última revisión: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data na que s'algamó: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Dirección URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID de versión de la páxina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Estilu de cites pa {{FULLPAGENAME}} ==

=== [[APA style|Estilu APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Consultáu el <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> en {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Estilu MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Estilu MHRA]] ===
Collaboradores de {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [consultáu el <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Estilu Chicago]] ===
Collaboradores de {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (consultáu el <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Estilu CBE/CSE]] ===
Collaboradores de {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citáu el <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponible en:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Estilu Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (última visita: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entrada [[BibTeX]] ===

  @misc{ wiki:xxx,
    autor = \"{{SITENAME}}\",
    títulu = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    añu = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    nota = \"[En llinia; consultáu el <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Cuando s'utiliza la dirección URL del paquete [[LaTeX]] (<code>\\usepackage{url}</code> n'algún llugar del preámbulu) que tiende a dar direiciones web con meyor formatu, pue ser preferible lo siguiente:

  @misc{ wiki:xxx,
    autor = \"{{SITENAME}}\",
    títulu = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    añu = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    nota = \"[En llinia; consultáu el <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--zarrando'l div de \"plainlinks\"-->",
);

/** Avaric (авар)
 * @author Amikeco
 */
$messages['av'] = array(
	'cite_article_link' => 'Гьумер рехсезе',
);

/** Azerbaijani (azərbaycanca)
 * @author Cekli829
 */
$messages['az'] = array(
	'cite' => 'Sayt',
	'cite_page' => 'Səhifə:',
	'cite_submit' => 'Sayt',
);

/** South Azerbaijani (تورکجه)
 * @author Amir a57
 * @author Mousa
 */
$messages['azb'] = array(
	'cite_article_desc' => 'بیر اؤزل [[Special:Cite|آلینتی]] صحیفه‌سی و آراج-قوتوسو باغلانتی‌سی آرتیرار',
	'cite_article_link' => 'بو صحیفه‌دن آلینتی گؤتور',
	'tooltip-cite-article' => 'بو صحیفه‌دن نئجه آلینتی گؤتورمک اوچون بیلگیلر',
	'cite' => 'سایت',
	'cite_page' => 'صحیفه:',
	'cite_submit' => 'سایت',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} اوچون قایناق‌جالیق بیلگیلری ==

* صحیفه آدی: {{FULLPAGENAME}}
* یارادیجی: {{SITENAME}} ایستیفاده‌چیلری
* نشر ائدن: ''{{SITENAME}}، {{int:sitesubtitle}}''.
* سون نوسخه‌نین تاریخی: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* گؤتورن تاریخ: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* قالیجی آدرس: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* صحیفه نوسخه بلیردنی: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}} اوچون آلینتی بیچیملری ==

=== [[APA بیچیمی]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA بیچیم بیلگیلری|MLA بیچیمی]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA بیچیم رهبرلیگی|MHRA بیچیمی]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[شیکاگو بیچیم بیلگیلری|شیکاگو بیچیمی]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[بیلگی شوراسی یازارلاری|CBE/CSE بیچیمی]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook بیچیمی]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] بیچیمی ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

[[لتک]] یوآر‌اِل بسته‌سینی ایشلدن‌ده (<code>\\usepackage{url}</code> باشلیق ایچینده) کی داها گؤزل بیچیملنمیش اینترنت آدرسلری وئرر، بو آشاغیداکی ترجیح وئریلیر:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Bashkir (башҡортса)
 * @author Assele
 * @author Haqmar
 */
$messages['ba'] = array(
	'cite_article_desc' => '[[Special:Cite|Өҙөмтә яһау]] махсус битен һәм ҡоралдарҙа һылтанма өҫтәй',
	'cite_article_link' => 'Биттән өҙөмтә яһарға',
	'tooltip-cite-article' => 'Был битте нисек өҙөмтәләргә кәрәклеге тураһында мәғлүмәт',
	'cite' => 'Өҙөмтәләү',
	'cite_page' => 'Бит:',
	'cite_submit' => 'Өҙөмтәләргә',
);

/** Bavarian (Boarisch)
 * @author Man77
 * @author Mucalexx
 */
$messages['bar'] = array(
	'cite_article_desc' => "Ergänzd d' [[Special:Cite|Zitirhüf]]-Speziaalseiten und an Link im Werkzeigkosten",
	'cite_article_link' => "d' Seiten zitirn",
	'tooltip-cite-article' => 'Hihweis, wia dé Seiten zitird wern kå',
	'cite' => 'Zitirhüf',
	'cite_page' => 'Seiten:',
	'cite_submit' => 'åzoang',
);

/** Southern Balochi (بلوچی مکرانی)
 * @author Mostafadaneshvar
 */
$messages['bcc'] = array(
	'cite_article_desc' => 'اضافه کن یک [[Special:Cite|citation]] صفحه حاص و لینک جعبه ابزار',
	'cite_article_link' => 'ای صفحه ی مرجع بل',
	'cite' => 'مرجع',
	'cite_page' => 'صفحه:',
	'cite_submit' => 'مرجع',
);

/** Bikol Central (Bikol Central)
 * @author Filipinayzd
 * @author Geopoet
 */
$messages['bcl'] = array(
	'cite_article_desc' => 'Nagdudugang nin sarong [[Special:Cite|citation]] espesyal na pahina asin kasugpunan sa palindông kahon',
	'cite_article_link' => 'Isambit ining pahina',
	'tooltip-cite-article' => 'Impormasyon kun paanuhon na sambiton ining pahina',
	'cite' => 'Sambiton',
	'cite_page' => 'Pahina:',
	'cite_submit' => 'Sambiton',
	'cite_text' => "__NOTOC__ 
<div class=\"mw-specialcite-bibliographic\"> 

== Bibliograpikong mga detalye para sa {{FULLPAGENAME}} == 
* Pangaran kan pahina: {{FULLPAGENAME}} 
* Awtor: {{SITENAME}} mga paraambag 
* Publikador: ''{{SITENAME}}, {{int:sitesubtitle}}''. 
* Petsa kan huring pagliwat: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC 
* Petsa kan pagbawi: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation> 
* Permanenteng URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} 
* ID kan Bersyon kan Pahina: {{REVISIONID}} 

</div> 
<div class=\"plainlinks mw-specialcite-styles\"> 

== Pagsambit na mga istilo para sa {{FULLPAGENAME}} == 

=== [[Istilong APA]] === 

{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Pinagbawi <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> gikan sa {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}. 

=== [[An MLA Manwal na Istilo|Istilong MLA]] === \"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;. 

=== [[MHRA Giya sa Istilo|Istilo sa MHRA]] === 
{{SITENAME}} mga paraambag, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>] 

=== [[An Chicago Manwal na Istilo|Istilo sa Chicago]] === 
{{SITENAME}} mga paraambag, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>). 

=== [[Konseho kan mga Paraliwat sa Siyensiya|CBE/CSE style]] === 
{{SITENAME}} mga paraambag. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Yaon gikan sa : {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}. 

=== [[Librong Asul|Istilo sa Librong Asul]] === 
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>). 

=== [[BibTeX]] entrada === 

@misc{ wiki:xxx, awtor = \"{{SITENAME}}\", titulo = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\", taon = \"{{CURRENTYEAR}}\", url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\", tandai = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\" }

Kunsoarin na ginagamit an [[Latex]] pampaketeng url (<code>\\usepackage{url}</code> yason sa parte kan prayambulo) na tantong minatao nin mas marhayon na kadagdagan sa pormat kan mga estada sa web, an minasunod mapupuwedeng pagpipilian: 

@misc{ wiki:xxx, 
awtor = \"{{SITENAME}}\", titulo = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\", taon = \"{{CURRENTYEAR}}\", url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\", tandaan = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\" 
} 

</div> <!--closing div for \"plainlinks\"-->",
);

/** Belarusian (беларуская)
 * @author Хомелка
 */
$messages['be'] = array(
	'cite_article_desc' => 'Дадае [[Special:Cite|цытату]] адмысловых старонак і спасылку панэлі інструментаў',
	'cite_article_link' => 'Цытаваць гэту старонку',
	'tooltip-cite-article' => 'Інфармацыя пра тое, як цытаваць гэтую старонку',
	'cite' => 'Спаслацца',
	'cite_page' => 'Старонка:',
	'cite_submit' => 'Спаслацца',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'cite_article_desc' => 'Дадае спэцыяльную старонку [[Special:Cite|цытаваньня]] і спасылку ў інструмэнтах',
	'cite_article_link' => 'Цытаваць старонку',
	'tooltip-cite-article' => 'Інфармацыя пра тое, як цытатаваць гэтую старонку',
	'cite' => 'Цытаваньне',
	'cite_page' => 'Старонка:',
	'cite_submit' => 'Цытаваць',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Бібліяграфічныя зьвесткі артыкула «{{FULLPAGENAME}}» ==

* Назва артыкула: {{FULLPAGENAME}}
* Аўтар: Рэдактары {{GRAMMAR:родны|{{SITENAME}}}}
* Выдавец: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Дата апошняй рэвізіі: {{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Дата атрыманьня: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Сталы URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Ідэнтыфікатар вэрсіі артыкула: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Цытаваньне артыкула «{{FULLPAGENAME}}» рознымі стандартамі ==

=== [[Стыль АПА]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA style manual|Стыль MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Стыль MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Стыль Чыкага]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Стыль CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Стыль Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Пры выкарыстаньні пакета url для [[LaTeX]] (<code>\\usepackage{url}</code> у пачатку) можна дабіцца лепшага выяўленьня вэб-адрасоў. Неабходна аформіць наступным чынам:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Bulgarian (български)
 * @author DCLXVI
 * @author Turin
 */
$messages['bg'] = array(
	'cite_article_desc' => 'Добавя специална страница и препратка за [[Special:Cite|цитиране]]',
	'cite_article_link' => 'Цитиране на страницата',
	'tooltip-cite-article' => 'Данни за начин на цитиране на тази страница',
	'cite' => 'Цитиране',
	'cite_page' => 'Страница:',
	'cite_submit' => 'Цитиране',
);

/** Bengali (বাংলা)
 * @author Bellayet
 * @author Zaheen
 */
$messages['bn'] = array(
	'cite_article_desc' => 'একটি বিশেষ [[Special:Cite|উদ্ধৃতি]] পাতা ও টুলবক্স সংযোগ যোগ করে',
	'cite_article_link' => 'এ পাতাটি উদ্ধৃত করো',
	'cite' => 'উদ্ধৃত',
	'cite_page' => 'পাতা:',
	'cite_submit' => 'উদ্ধৃত করো',
);

/** Tibetan (བོད་ཡིག)
 * @author Freeyak
 */
$messages['bo'] = array(
	'cite' => '',
	'cite_page' => 'ཤོག་ངོས།',
);

/** Bishnupria Manipuri (বিষ্ণুপ্রিয়া মণিপুরী)
 */
$messages['bpy'] = array(
	'cite_article_link' => 'নিবন্ধ এহানরে উদ্ধৃত করেদে',
	'cite' => 'উদ্ধৃত করেদে',
);

/** Breton (brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'cite_article_desc' => 'Ouzhpennañ a ra ur bajenn dibar [[Special:Cite|arroud]] hag ul liamm er voest ostilhoù',
	'cite_article_link' => 'Menegiñ ar pennad-mañ',
	'tooltip-cite-article' => 'Titouroù war an doare da venegiñ ar bajenn-mañ',
	'cite' => 'Menegiñ',
	'cite_page' => 'Pajenn :',
	'cite_submit' => 'Menegiñ',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Titouroù levrlennadurel evit {{FULLPAGENAME}} ==

* Anv ar bajenn : {{FULLPAGENAME}} 
* Aozer : kenlabourerien {{SITENAME}}
* Embanner : ''{{SITENAME}}, {{int:sitesubtitle}}''. 
* Kemm diwezhañ : {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Deiziad adtapout : <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL pad : {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identeler ar stumm-mañ : {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stiloù arroudoù evit {{FULLPAGENAME}} ==

=== [[Stil APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Adtapet d'an <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> e {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Stil MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Stil MHRA]] ===
Perzhidi {{SITENAME}}, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [sellet d'an <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Stil Chicago]] ===
Perzhidi {{SITENAME}}, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (sellet d'an <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Stil CBE/CSE]] ===
Perzhidi {{SITENAME}}. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{int:sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}},   {{CURRENTTIME}} UTC [meneget d'an <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>].  Hegerz war : 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Stil Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (sellet d'an <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Enmont [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Enlinenn ; sellet d'an <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Ma rit gant ar pakadur URL e [[LaTeX]] (<code>\\usepackage{url}</code> en ul lec'h bennak er raklavar), a bourchas chomlec'hioù Web furmadet gwelloc'h, grit gant ar furmad-mañ :

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Enlinenn ; sellet d'an <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Bosnian (bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'cite_article_desc' => 'Dodaje posebnu stranicu za [[Special:Cite|citiranje]] i link u alatnoj kutiji',
	'cite_article_link' => 'Citiraj ovu stranicu',
	'tooltip-cite-article' => 'Informacije kako citirati ovu stranicu',
	'cite' => 'Citiranje',
	'cite_page' => 'Stranica:',
	'cite_submit' => 'Citiraj',
);

/** Catalan (català)
 * @author Davidpar
 * @author SMP
 * @author Toniher
 * @author Vriullop
 */
$messages['ca'] = array(
	'cite_article_desc' => 'Afegeix un enllaç i una pàgina especial de [[Special:Cite|citació]]',
	'cite_article_link' => 'Cita aquesta pàgina',
	'tooltip-cite-article' => 'Informació sobre com citar aquesta pàgina.',
	'cite' => 'Citeu',
	'cite_page' => 'Pàgina:',
	'cite_submit' => 'Cita',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Informació bibliogràfica de {{FULLPAGENAME}} ==

* Pàgina: {{FULLPAGENAME}}
* Autor: col·laboradors del projecte {{SITENAME}}
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Darrera versió: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Consulta: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanent: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identificador de la versió: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Estils de citacions per {{FULLPAGENAME}} ==

=== [[Llibre d'estil APA|Estil APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Recuperat <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> a {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Llibre d'estil MLA|Estil MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Llibre d'estil MHRA|Estil MHRA]] ===
Col·laboradors de {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [consulta <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Llibre d'estil Chicago|Estil Chicago]] ===
Col·laboradors de {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (consulta <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Estil CBE/CSE]] ===
Col·laboradors de {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citat <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponible a:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Estil Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (darrera consulta <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entrada [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[En línia; consulta <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Si empreu el paquet url per a [[LaTeX]] (<code>\\usepackage{url}</code> en algun lloc del preàmbul) que facilita el format d'adreces web, pot ser millor el codi següent:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[En línia; consulta <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Min Dong Chinese (Mìng-dĕ̤ng-ngṳ̄)
 * @author Yejianfei
 */
$messages['cdo'] = array(
	'cite_article_link' => '標記茲蜀頁',
	'cite' => '標記',
	'cite_page' => '頁面',
	'cite_submit' => '標記',
);

/** Chechen (нохчийн)
 * @author Sasan700
 * @author Умар
 */
$messages['ce'] = array(
	'cite_article_link' => 'АгӀонах лаьцна дешнаш дало',
	'tooltip-cite-article' => 'ХӀара бу хаам агӀонах лаьцна дешнаш муха дало деза гойтуш',
	'cite' => 'Далийнадош',
	'cite_page' => 'АгӀо:',
	'cite_submit' => 'Даладе дош',
);

/** Cebuano (Cebuano)
 * @author Abastillas
 */
$messages['ceb'] = array(
	'cite' => 'Kutloa',
);

/** Sorani Kurdish (کوردی)
 * @author Asoxor
 * @author Calak
 */
$messages['ckb'] = array(
	'cite_article_link' => 'ئەم پەڕەیە بکە بە ژێدەر',
	'tooltip-cite-article' => 'زانیاری سەبارەت بە چۆنیەتیی بە ژێدەر کردنی ئەم پەڕە',
	'cite' => 'بیکە بە ژێدەر',
	'cite_page' => 'پەڕە:',
	'cite_submit' => 'بیکە بە ژێدەر',
);

/** Corsican (corsu)
 */
$messages['co'] = array(
	'cite_article_link' => 'Cità issu articulu', # Fuzzy
	'cite' => 'Cità',
	'cite_page' => 'Pagina:',
);

/** Czech (česky)
 * @author Beren
 * @author Li-sung
 * @author Martin Kozák
 * @author Mormegil
 */
$messages['cs'] = array(
	'cite_article_desc' => 'Přidává speciální stránku [[Special:Cite|Citace]] a odkaz v nabídce nástrojů',
	'cite_article_link' => 'Citovat stránku',
	'tooltip-cite-article' => 'Informace o tom, jak citovat tuto stránku',
	'cite' => 'Citace',
	'cite_page' => 'Článek:',
	'cite_submit' => 'Citovat',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografické detaily ke stránce {{FULLPAGENAME}} ==

* Jméno stránky: {{FULLPAGENAME}}
* Autor: Přispěvatelé {{grammar:2sg|{{SITENAME}}}}
* Vydavatel: ''{{MediaWiki:Sitesubtitle}}''.
* Datum poslední úpravy: {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Datum převzetí: <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC</citation>
* Trvalý odkaz: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identifikace verze stránky: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Způsoby citace stránky {{FULLPAGENAME}} ==

=== ISO 690-2 (1)===
Přispěvatelé {{grammar:2sg|{{SITENAME}}}},'' {{FULLPAGENAME}}'' [online],  {{int:sitesubtitle}}, c{{CURRENTYEAR}}, 
Datum poslední revize {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC, 
[citováno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]
&lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; 

=== ISO 690-2 (2)===
''{{int:sitesubtitle}}: {{FULLPAGENAME}}''  [online]. c{{CURRENTYEAR}} [citováno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]. Dostupný z WWW: &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; 

=== APA ===
{{FULLPAGENAME}}. ({{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}). ''{{int:sitesubtitle}}''. Získáno <citation>{{CURRENTTIME}}, {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation> z {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA ===
„{{FULLPAGENAME}}.“ ''{{int:sitesubtitle}}''. {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA ===
Přispěvatelé {{grammar:2sg|{{SITENAME}}}}, '{{FULLPAGENAME}}',  ''{{int:sitesubtitle}},'' {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [získáno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]

=== Chicago ===
Přispěvatelé {{grammar:2sg|{{SITENAME}}}}, „{{FULLPAGENAME}},“  ''{{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (získáno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>).

=== CBE/CSE ===
Přispěvatelé {{grammar:2sg|{{SITENAME}}}}. {{FULLPAGENAME}} [Internet].  {{int:sitesubtitle}};  {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}},   {{CURRENTTIME}} UTC [cited <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>].  Dostupné na: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebook ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (naposledy navštíveno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>).

=== [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; navštíveno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]\"
  }

Při použití [[LaTeX]]ového balíčku url (někde na začátku dokumentu je uvedeno <code>\\usepackage{url}</code>), který o něco lépe formátuje webové adresy, můžete upřednostnit následující verzi:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; navštíveno <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing div for \"plainlinks\"-->",
);

/** Church Slavic (словѣ́ньскъ / ⰔⰎⰑⰂⰡⰐⰠⰔⰍⰟ)
 * @author ОйЛ
 */
$messages['cu'] = array(
	'cite_article_link' => 'привєдєниѥ члѣна словєсъ',
	'cite_page' => 'страница :',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'cite_article_desc' => 'Yn ychwanegu tudalen arbennig ar gyfer [[Special:Cite|cyfeirio at erthygl]] a chyswllt bocs offer',
	'cite_article_link' => 'Cyfeiriwch at yr erthygl hon',
	'tooltip-cite-article' => 'Gwybodaeth ar sut i gyfeirio at y dudalen hon',
	'cite' => 'Cyfeirio at erthygl',
	'cite_page' => 'Tudalen:',
	'cite_submit' => 'Cyfeirio',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Manylion am {{FULLPAGENAME}} at ddiben llyfryddiaeth ==

* Enw'r dudalen: {{FULLPAGENAME}}
* Awdur: {{SITENAME}} contributors
* Cyhoeddwr: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Dyddiad y diwygiad diweddaraf: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Dyddiad adalw: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Yr URL parhaol: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID Diwygiad y Dudalen: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Arddulliau cyfeirio ar gyfer {{FULLPAGENAME}} ==

=== [[APA Style|Arddull APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Adalwyd <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> o {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Arddull MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Arddull MHRA]] ===
Cyfranwyr i {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [adalwyd <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Arddull Chicago]] ===
Cyfranwyr i {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Arddull CBE/CSE]] ===
Cyfranwyr i {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cyfeiriwyd ato am <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Ar gael o:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Arddull Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (adalwyd ddiwethaf am <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Cofnod [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; cyrchwyd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Wrth ddefnyddio url y pecyn [[LaTeX]] (<code>\\usepackage{url}</code> rhywle yn y rhaglith), sydd fel arfer yn dangos cyfeiriadau gwe ar fformat del iawn, gallwch ddefnyddio'r arddull canlynol:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Arlein; cyrchwyd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Danish (dansk)
 * @author Byrial
 * @author Christian List
 * @author Morten LJ
 * @author Peter Alberti
 */
$messages['da'] = array(
	'cite_article_desc' => 'Tilføjer en [[Special:Cite|specialside til citering]] og en henvisning i værktøjsmenuen',
	'cite_article_link' => 'Citér denne artikel',
	'tooltip-cite-article' => 'Information om, hvordan man kan citere denne side',
	'cite' => 'Citér',
	'cite_page' => 'Side:',
	'cite_submit' => 'Citér',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

 == Bibliografiske oplysninger for {{FULLPAGENAME}} ==

 * Sidenavn: {{FULLPAGENAME}}
 * Forfatter: {{SITENAME}} bidragydere
 * Udgiver: ''{{SITENAME}}, {{int:sitesubtitle}}''.
 * Dato for seneste revision: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
 * Datoen hentet: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
 * Permanent URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
 * Side versions-ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

 == Typografier til citat af {{FULLPAGENAME}} ==

 === [[APA typografi]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Hentet <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> fra {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

 === [[The MLA stil manual|MLA typografi]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt; {{canonicalurl: {{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

 === [[MHRA stil Guide|MHRA typografi]] ===
{{SITENAME}} bidragydere, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt; {{canonicalurl: {{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [hentet <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

 === [[Chicago manualen om Style|Chicago typografi]] ===
{{SITENAME}} bidragydere, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (hentet <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

 === [[Rådet for videnskabsredaktører|CBE/CSE typografi]] ===
{{SITENAME}} bidragydere. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citeret <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Tilgængelig fra:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

 === [[Bluebook|Bluebook typografi]] ===
{{FULLPAGENAME}}, {{canonicalurl: {{FULLPAGENAME}}|oldid={{REVISIONID}}}} (senest besøgt <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

 === [[BibTeX]] indlæg ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[Online; hentet <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Når du bruger [[LaTeX]] pakkens URL-adressen (<code>\\usepackage{url}</code> et sted i præamblen) som har tendens til at give meget mere pænt formaterede webadresser, kan følgende være at foretrække:

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[Online; hentet <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** German (Deutsch)
 * @author Kghbln
 */
$messages['de'] = array(
	'cite_article_desc' => 'Ergänzt eine [[Special:Cite|Spezialseite]] als Zitierhilfe sowie einen zugehörigen Link im Bereich Werkzeuge',
	'cite_article_link' => 'Seite zitieren',
	'tooltip-cite-article' => 'Hinweis, wie diese Seite zitiert werden kann',
	'cite' => 'Zitierhilfe',
	'cite_page' => 'Seite:',
	'cite_submit' => 'zitieren',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografische Angaben für {{FULLPAGENAME}} ==

* Seitentitel: {{FULLPAGENAME}}
* Autor(en): {{SITENAME}}-Bearbeiter
* Herausgeber: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Zeitpunkt der letzten Bearbeitung: {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Datum des Abrufs: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC</citation>
* Permanente URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Versionskennung: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Zitatstile für {{FULLPAGENAME}} ==

=== [[APA-Stil]] ===
{{FULLPAGENAME}}. ({{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> von {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA-Stil]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA-Stil]] ===
{{SITENAME}}-Bearbeiter, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Chicago-Stil]] ===
{{SITENAME}}-Bearbeiter, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[CBE/CSE-Stil]] ===
{{SITENAME}}-Bearbeiter. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC [zitiert am <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}</citation>]. Verfügbar unter:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook-Stil]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-Eintrag ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }

Bei Benutzung der [[LaTeX]]-Moduls „url“ (<code>\\usepackage{url}</code> im Bereich der Einleitung), welches eine schöner formatierte Internetadresse ausgibt, kann die folgende Ausgabe genommen werden:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; abgerufen am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Zazaki (Zazaki)
 * @author Erdemaslancan
 * @author Mirzali
 * @author Xoser
 */
$messages['diq'] = array(
	'cite_article_desc' => 'Pela xısusiye u gıreyê qutiya hacetan [[Special:Cite|citation]] ilawe keno.',
	'cite_article_link' => 'Na pele bia xo viri',
	'tooltip-cite-article' => 'Melumato ke ena pele çıtewri iqtıbas keno',
	'cite' => 'Bia xo viri',
	'cite_page' => 'Pele:',
	'cite_submit' => 'Bia xo viri',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliyografiya teferruatanê {{FULLPAGENAME}} ==

* Nameyê pele: {{FULLPAGENAME}}
* Nuskar: İştıraqkerê {{SITENAME}}
* Vılaker: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Revizyonê demi: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Serkerdışê demi: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Ancıyayışê URLê cı: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Verziyonê kamiya pela: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Terzê istasyonê {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. ancıyayo <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Terzê MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Terzê MHRA]] ===
iştırazkarê {{SITENAME}} , '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [zerre <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Terzê Şikagoy]] ===
iştırazkarê {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Terzê CBE/CSE]] ===
{{SITENAME}} İştıraxkari. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [sitedo <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Ancıyayışê cı:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Terzê Bluebooki]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (tewr peyên<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Cı kewê [[BibTeX]] ===

  @misc{ wiki:xxx,
    Nuskar = \"{{SITENAME}}\",
    Sername = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    Serre = \"{{CURRENTYEAR}}\",
    Url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    Not = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

  @misc{ wiki:xxx,
    Nuskar = \"{{SITENAME}}\",
    Sername = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    Serre = \"{{CURRENTYEAR}}\",
    Url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    Not = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'cite_article_desc' => 'Pśidawa specialny bok [[Special:Cite|Citěrowańska pomoc]] a link w kašćiku źěłowe rědy',
	'cite_article_link' => 'Toś ten bok citěrowaś',
	'tooltip-cite-article' => 'Informacije wó tom, kak toś ten bok dajo se citěrowaś',
	'cite' => 'Citěrowańska pomoc',
	'cite_page' => 'Bok:',
	'cite_submit' => 'pokazaś',
);

/** Ewe (eʋegbe)
 */
$messages['ee'] = array(
	'cite_page' => 'Nuŋɔŋlɔ:',
);

/** Greek (Ελληνικά)
 * @author Consta
 * @author Glavkos
 * @author Omnipaedista
 * @author Protnet
 */
$messages['el'] = array(
	'cite_article_desc' => 'Προσθέτει μία ειδική σελίδα [[Special:Cite|παραθέσεων]] και έναν σύνδεσμο προς την εργαλειοθήκη',
	'cite_article_link' => 'Παραθέστε αυτή τη σελίδα',
	'tooltip-cite-article' => 'Πληροφορίες για το πως να παραπέμψετε σε αυτήν την σελίδα',
	'cite' => 'Αναφορά',
	'cite_page' => 'Σελίδα:',
	'cite_submit' => 'Προσθήκη παραθέσεων',
);

/** Esperanto (Esperanto)
 * @author Michawiki
 * @author Tlustulimu
 * @author Yekrats
 */
$messages['eo'] = array(
	'cite_article_desc' => 'Aldonas specialan paĝon por [[Special:Cite|citado]] kaj ligilo al ilaro',
	'cite_article_link' => 'Citi ĉi tiun paĝon',
	'tooltip-cite-article' => 'Informoj pri tio, kiel oni citu ĉi tiun paĝon',
	'cite' => 'Citado',
	'cite_page' => 'Paĝo:',
	'cite_submit' => 'Citi',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografiaj detaloj por {{FULLPAGENAME}} ==

* Nomo de paĝo: {{FULLPAGENAME}}
* Aŭtoro: {{SITENAME}} contributors
* Eldonejo: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Dato de lasta revizio: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Dato ricevita: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Daŭra URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Versio-identigo de paĝo: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citaj stiloj por {{FULLPAGENAME}} ==

=== [[APA-stilo]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA-stilo]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA-stilo]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Ĉikago-stilo]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[CBE/CSE-stilo]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Blulibro-stilo]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] datumaro ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Kiam uzante [[LaTeX]]-on, url (<code>\\usepackage{url}</code> ie en la kapteksto) kiu emas formati pli belaj retadresoj, la jeno eble estos preferata:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Spanish (español)
 * @author Armando-Martin
 * @author Crazymadlover
 * @author Icvav
 * @author Jatrobat
 * @author Muro de Aguas
 * @author Sanbec
 */
$messages['es'] = array(
	'cite_article_desc' => 'Añade una página especial para [[Special:Cite|citar la página]] y un enlace en la caja de herramientas.',
	'cite_article_link' => 'Citar este artículo',
	'tooltip-cite-article' => 'Información de como citar esta página',
	'cite' => 'Citar',
	'cite_page' => 'Página:',
	'cite_submit' => 'Citar',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Datos bibliográficos sobre {{FULLPAGENAME}} ==

* Nombre de la página: {{FULLPAGENAME}}
* Autor: {{SITENAME}} contributors
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Fecha de la última revisión: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Fecha obtenida: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Dirección URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identificador de versión de la página: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Estilo de citas para {{FULLPAGENAME}} ==

=== [[APA style|Estilo APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Consultado el <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> en {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Estilo MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Estilo MHRA]] ===
Colaboradores de {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [consultado el <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Estilo Chicago]] ===
Colaboradores de {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (consultado el <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Estilo CBE/CSE]] ===
Colaboradores de {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citado el <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponible en:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Estilo Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (última visita: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entrada [[BibTeX]] ===

  @misc{ wiki:xxx,
    autor = \"{{SITENAME}}\",
    título = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    año = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    nota = \"[En línea; consultado el <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Cuando se utiliza la dirección URL de empaquetamiento [[LaTeX]] (<code>\\usepackage{url}</code> en algún lugar del preámbulo) que tiende a dar direcciones web con un formato más agradable, se prefiere lo siguiente:

  @misc{ wiki:xxx,
    autor = \"{{SITENAME}}\",
    título = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    año = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    nota = \"[En línea; consultado el <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--cerrando div para \"plainlinks\"-->",
);

/** Estonian (eesti)
 * @author Pikne
 * @author WikedKentaur
 */
$messages['et'] = array(
	'cite_article_desc' => 'Lisab [[Special:Cite|tsiteerimise]] erilehekülje ja lingi külgmenüü tööriistakasti.',
	'cite_article_link' => 'Tsiteeri seda artiklit',
	'tooltip-cite-article' => 'Teave tsiteerimisviiside kohta',
	'cite' => 'Tsiteerimine',
	'cite_page' => 'Leht:',
	'cite_submit' => 'Tsiteeri',
	'cite_text' => '__NOTOC__
<div class="mw-specialcite-bibliographic">

== Lehekülje "{{FULLPAGENAME}}" bibliograafilised andmed ==

* Lehekülje pealkiri: {{FULLPAGENAME}}
* Autor: {{GRAMMAR:genitive|{{SITENAME}}}} kaastöölised
* Väljaandja: \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'.
* Viimane redaktsioon: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Vaadatud: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Püsilink: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Lehekülje versiooninumber: {{REVISIONID}}

</div>
<div class="plainlinks mw-specialcite-styles">

== Viitamisstiilid lehekülje "{{FULLPAGENAME}}" jaoks ==

=== APA stiil ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. Vaadatud: <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>, aadressil {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA stiil ===
"{{FULLPAGENAME}}." \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA stiil ===
{{GRAMMAR:genitive|{{SITENAME}}}} kaastöölised, \'{{FULLPAGENAME}}\', \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [vaadatud: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== Chicago stiil ===
{{GRAMMAR:genitive|{{SITENAME}}}} kaastöölised, "{{FULLPAGENAME}}," \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (vaadatud: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== CBE/CSE stiil ===
{{GRAMMAR:genitive|{{SITENAME}}}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [vaadatud: <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Kättesaadav aadressil:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebooki stiil ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (viimati vaadatud: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== BibTeX-i sissekanne ===

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}",
    note = "[Võrgus; vaadatud: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]"
  }

Kui kasutada LaTeX-i url-i (<code>\\usepackage{url}</code> kuskil lehekülje alguses), mis vormindab sageli võrguaadressi ilusamini, võib eelistatavamaks osutuda järgmine kood:

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "\'\'\'\\url{\'\'\'{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\'\'\'}\'\'\'",
    note = "[Võrgus; vaadatud: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]"
  }


</div> <!--closing div for "plainlinks"-->',
);

/** Basque (euskara)
 * @author An13sa
 * @author Theklan
 * @author Xabier Armendaritz
 */
$messages['eu'] = array(
	'cite_article_desc' => '[[Special:Cite|Aipatu]] orrialde berezia gehitzen du tresna-kutxaren loturetan',
	'cite_article_link' => 'Aipatu orri hau',
	'tooltip-cite-article' => 'Orri honen aipua egiteko moduari buruzko informazioa',
	'cite' => 'Aipamenak',
	'cite_page' => 'Orrialdea:',
	'cite_submit' => 'Aipatu',
);

/** Extremaduran (estremeñu)
 * @author Better
 */
$messages['ext'] = array(
	'cite_article_link' => 'Almiental esti artículu', # Fuzzy
	'cite' => 'Almiental',
	'cite_page' => 'Páhina:',
	'cite_submit' => 'Almiental',
);

/** Persian (فارسی)
 * @author Huji
 * @author Reza1615
 * @author Wayiran
 * @author ZxxZxxZ
 */
$messages['fa'] = array(
	'cite_article_desc' => 'صفحهٔ ویژه‌ای برای [[Special:Cite|یادکرد]] اضافه می‌کند و پیوندی به جعبه ابزار می‌افزاید',
	'cite_article_link' => 'یادکرد پیوند این مقاله',
	'tooltip-cite-article' => 'اطلاعات در خصوص چگونگی یادکرد این صفحه',
	'cite' => 'یادکرد این مقاله',
	'cite_page' => 'صفحه:',
	'cite_submit' => 'یادکرد',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== اطلاعات کتاب‌شناسی برای {{FULLPAGENAME}} ==

* نام صفحه: {{FULLPAGENAME}}
* نویسنده: مشارکت‌کنندگان {{SITENAME}}
* ناشر: ''{{SITENAME}}، {{int:sitesubtitle}}''.
* تاریخ آخرین نسخه: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* تاریخ بازبینی: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* نشانی پایدار: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* شناسهٔ نسخهٔ صفحه: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== شیوه‌های یادکرد برای {{FULLPAGENAME}} ==

=== [[شیوه APA|شیوهٔ APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}، {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}، {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}، {{CURRENTMONTHNAME}} {{CURRENTDAY}}، {{CURRENTYEAR}}</citation> از {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[شیوه ام‌ال‌آ|شیوهٔ MLA]] ===
«{{FULLPAGENAME}}». ''{{SITENAME}}، {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}، {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}، {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt؛.

=== [[شیوه MHRA|شیوهٔ MHRA]] ===
مشارکت‌کنندگان {{SITENAME}}، «{{FULLPAGENAME}}»، ''{{SITENAME}}، {{int:sitesubtitle}}،'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}، {{CURRENTTIME}} UTC، &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[شیوه‌نامه شیکاگو|شیوهٔ شیکاگو]] ===
مشارکت‌کنندگان {{SITENAME}}، «{{FULLPAGENAME}}»، ''{{SITENAME}}، {{int:sitesubtitle}}،'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (بازیابی‌شده در <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|شیوهٔ CBE/CSE]] ===
مشارکت‌کنندگان {{SITENAME}}. {{FULLPAGENAME}} [اینترنت]. {{SITENAME}}، {{int:sitesubtitle}}؛ {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}، {{CURRENTTIME}} UTC [یادکردشده در <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. قابل دسترسی از:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[w:en:Bluebook|شیوهٔ Bluebook]] ===
{{FULLPAGENAME}}، {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (آخرین بازدید در <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[برخط؛ بازبینی‌شده در <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

در زمان استفاده از بستهٔ  [[LaTeX]]  نشانی (<code>\\usepackage{url}</code> جایی در پیوند پایدار) که برای ارائه فرمت‌های وبی طراحی شده‌است، شاید به صورت زیر مطلوب باشد:

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[برخط؛ بازبینی‌شده در <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Finnish (suomi)
 * @author Linnea
 * @author Nike
 * @author ZeiP
 */
$messages['fi'] = array(
	'cite_article_desc' => 'Lisää työkaluihin toimintosivun, joka neuvoo [[Special:Cite|viittaamaan]] oikeaoppisesti.',
	'cite_article_link' => 'Viitetiedot',
	'tooltip-cite-article' => 'Tietoa tämän sivun lainaamisesta',
	'cite' => 'Viitetiedot',
	'cite_page' => 'Sivu:',
	'cite_submit' => 'Viittaa',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografiset tiedot artikkelille {{FULLPAGENAME}} ==

* Sivun nimi: {{FULLPAGENAME}}
* Tekijä: {{SITENAME}}-projektin osanottajat
* Julkaisija: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Viimeisimmän version päivämäärä: {{CURRENTDAY}}. {{CURRENTMONTHNAME}}ta {{CURRENTYEAR}}, kello {{CURRENTTIME}} (UTC)
* Sivu haettu: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}}ta {{CURRENTYEAR}}, kello {{CURRENTTIME}} (UTC)</citation>
* Pysyvä osoite: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Sivun version tunniste: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Viittaustyylit artikkelille {{FULLPAGENAME}} ==

=== APA-tyyli ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}}n {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Haettu <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}}n {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> osoitteesta {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA-tyyli ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA-tyyli ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}}ta {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [haettu <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}}ta {{CURRENTYEAR}}</citation>]

=== Chicago-tyyli ===
{{SITENAME}}-projektin osanottajat, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (haettu <citation>{{CURRENTMONTHNAME}}n {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== CBE/CSE-tyyli ===
{{SITENAME}}-projektin osanottajat. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Saatavilla osoitteesta: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebook-tyyli ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (vierailtu viimeksi <citation>{{CURRENTMONTHNAME}}n {{CURRENTDAY}}., {{CURRENTYEAR}}</citation>).

=== BibTeX-muoto ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; haettu <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Käytettäessä [[LaTeX]]-pakettia url, (<code>\\usepackage{url}</code> jossain alussa) joka tapaa antaa paremmin muotoiltuja osoitteita, seuraavaa muotoa voidaan käyttää:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; haettu <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Fijian (Na Vosa Vakaviti)
 */
$messages['fj'] = array(
	'cite_article_link' => 'Vola dau vaqarai', # Fuzzy
);

/** Faroese (føroyskt)
 * @author Diupwijk
 * @author Spacebirdy
 */
$messages['fo'] = array(
	'cite_article_link' => 'Sitera hesa síðuna',
	'cite' => 'Sitera',
	'cite_page' => 'Síða:',
	'cite_submit' => 'Sitera',
);

/** French (français)
 * @author DavidL
 * @author Grondin
 * @author Hégésippe Cormier
 * @author PieRRoMaN
 * @author Urhixidur
 */
$messages['fr'] = array(
	'cite_article_desc' => 'Ajoute une page spéciale [[Special:Cite|citation]] et un lien dans la boîte à outils',
	'cite_article_link' => 'Citer cette page',
	'tooltip-cite-article' => 'Informations sur comment citer cette page',
	'cite' => 'Citation',
	'cite_page' => 'Page :',
	'cite_submit' => 'Citer',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Détails bibliographiques pour {{FULLPAGENAME}} ==

* Nom de la page : {{FULLPAGENAME}}
* Auteur : contributeurs de {{SITENAME}}
* Éditeur : ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Dernière modification : {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} TUC
* Récupéré : <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} TUC</citation>
* URL permanente : {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identifiant de cette version : {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Styles de citations pour {{FULLPAGENAME}} ==

=== [[Style APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> depuis {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Style MLA]] ===
«&nbsp;{{FULLPAGENAME}}&nbsp;» ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Style MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accédé le <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Style Chicago]] ===
Contributeurs de {{SITENAME}}, «&nbsp;{{FULLPAGENAME}}&nbsp;», ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accédé le <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Style CBE/CSE]] ===
Contributeurs de {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}&nbsp;; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} TUC [cité le <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponible sur&nbsp;: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Style Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accédé le <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entrée [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[En ligne ; accédé le <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Si vous utilisez le package URL dans [[LaTeX]] (<code>\\usepackage{url}</code> quelque part dans le préambule), qui donne des adresses web mieux formatées, utilisez le format suivant :

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[En ligne ; accédé le <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'cite_article_desc' => 'Apond una pâge spèciâla [[Special:Cite|citacion]] et un lim dens la bouèta d’outils.',
	'cite_article_link' => 'Citar ceta pâge',
	'tooltip-cite-article' => 'Enformacions sur coment citar ceta pâge',
	'cite' => 'Citacion',
	'cite_page' => 'Pâge :',
	'cite_submit' => 'Citar',
);

/** Friulian (furlan)
 * @author Klenje
 * @author MF-Warburg
 */
$messages['fur'] = array(
	'cite_article_link' => 'Cite cheste vôs',
	'cite' => 'Citazion',
	'cite_page' => 'Pagjine:',
	'cite_submit' => 'Cree la citazion',
);

/** Western Frisian (Frysk)
 * @author SK-luuut
 * @author Snakesteuben
 */
$messages['fy'] = array(
	'cite_article_desc' => 'Foeget in [[Special:Cite|spesjale side]] om te sitearjen, lykas in ferwizing nei de helpmiddels, ta.',
	'cite_article_link' => 'Sitearje dizze side',
	'cite' => 'Sitearje',
	'cite_page' => 'Side:',
	'cite_submit' => 'Sitearje',
);

/** Irish (Gaeilge)
 * @author Alison
 */
$messages['ga'] = array(
	'cite_article_desc' => 'Cuir [[Special:Cite|deismireacht]] leathanach speisíalta agus nasc bosca uirlisí',
	'cite_article_link' => 'Luaigh an lch seo',
	'cite' => 'Luaigh',
	'cite_page' => 'Leathanach:',
	'cite_submit' => 'Luaigh',
);

/** Galician (galego)
 * @author Toliño
 * @author Xosé
 */
$messages['gl'] = array(
	'cite_article_desc' => 'Engade unha páxina especial de [[Special:Cite|citas]] e unha ligazón na caixa de ferramentas',
	'cite_article_link' => 'Citar esta páxina',
	'tooltip-cite-article' => 'Información sobre como citar esta páxina',
	'cite' => 'Citar',
	'cite_page' => 'Páxina:',
	'cite_submit' => 'Citar',
	'cite_text' => '__NOTOC__
<div class="mw-specialcite-bibliographic">

== Detalles bibliográficos de "{{FULLPAGENAME}}" ==

* Nome da páxina: {{FULLPAGENAME}}
* Autor: Colaboradores de {{SITENAME}}
* Editor: \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'.
* Data da última revisión: {{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}} ás {{CURRENTTIME}} UTC
* Data da consulta: <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}} ás {{CURRENTTIME}} UTC</citation>
* Enderezo URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID da versión da páxina: {{REVISIONID}}

</div>
<div class="plainlinks mw-specialcite-styles">

== Modelos de referencia bibliográfica de "{{FULLPAGENAME}}" ==

=== [[APA style|Estilo APA]] ===
{{FULLPAGENAME}}. ({{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}). \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. Consultado o <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}} ás {{CURRENTTIME}}</citation> en {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Estilo MLA]] ===
"{{FULLPAGENAME}}." \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. {{CURRENTDAY}} de {{CURRENTMONTHABBREV}} de {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} de {{CURRENTMONTHABBREV}} de {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Estilo MHRA]] ===
Colaboradores de {{SITENAME}}, \'{{FULLPAGENAME}}\', \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [consultado o <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Estilo Chicago]] ===
Colaboradores de {{SITENAME}}, "{{FULLPAGENAME}}," \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (consultado o <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Estilo CBE/CSE]] ===
Colaboradores de {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTDAY}} de {{CURRENTMONTHABBREV}} de {{CURRENTYEAR}}, {{CURRENTTIME}} UTC [citado o <citation>{{CURRENTDAY}} de {{CURRENTMONTHABBREV}} de {{CURRENTYEAR}}</citation>]. Dispoñible en:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Estilo Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (última visita o <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} de {{CURRENTYEAR}}</citation>).

=== Entrada [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}",
    note = "[En liña; consultado o <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]"
  }

Ao empregar o paquete "url" do [[LaTeX]] (<code>\\usepackage{url}</code> nalgunha parte do preámbulo), que tende a mostrar os enderezos web nun formato moito máis agradable, poida que prefira o seguinte:

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "\'\'\'\\url{\'\'\'{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\'\'\'}\'\'\'",
    note = "[En liña; consultado o <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]"
  }


</div> <!--etiqueta "div" de peche para os "plainlinks" abertos-->',
);

/** Ancient Greek (Ἀρχαία ἑλληνικὴ)
 * @author AndreasJS
 * @author LeighvsOptimvsMaximvs
 * @author Omnipaedista
 */
$messages['grc'] = array(
	'cite_article_desc' => 'Προσθέτει εἰδικὴν δἐλτον [[Special:Cite|ἀναφορῶν]] τινὰ καὶ σύνδεσμον τινὰ ἐν τῷ ἐργαλειοκάδῳ',
	'cite_article_link' => 'Άναφέρειν τήνδε τὴν δέλτον',
	'cite' => 'Μνημονεύειν',
	'cite_page' => 'Δέλτος:',
	'cite_submit' => 'Μνημονεύειν',
);

/** Swiss German (Alemannisch)
 * @author Als-Chlämens
 * @author Als-Holder
 * @author Strommops
 */
$messages['gsw'] = array(
	'cite_article_desc' => 'Ergänzt d [[Special:Cite|Zitierhilf]]-Spezialsyte un e Link im Chaschte Wärchzyyg',
	'cite_article_link' => 'Die Site zitiere',
	'tooltip-cite-article' => 'Informatione driber, wie mer die Syte cha zitiere',
	'cite' => 'Zitierhilf',
	'cite_page' => 'Syte:',
	'cite_submit' => 'aazeige',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografischi Aagabe für {{FULLPAGENAME}} ==

* Sytetitel: {{FULLPAGENAME}}
* Autor(e): {{SITENAME}}-Bearbeiter
* Herussgeber: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Zitpunkt vo de letschte Bearbeitig: {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Abruefdatum: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC</citation>
* Permanenti URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Versionsschlüssel: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Zitatstil für {{FULLPAGENAME}} ==

=== [[APA-Stil]] ===
{{FULLPAGENAME}}. ({{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> vo {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA-Stil]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA-Stil]] ===
{{SITENAME}}-Bearbeiter, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Chicago-Stil]] ===
{{SITENAME}}-Bearbeiter, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[CBE/CSE-Stil]] ===
{{SITENAME}}-Bearbeiter. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC [zitiert am <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}</citation>]. Verfiegbar unter:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook-Stil]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-Yytrag ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }

Wänn de s [[LaTeX]]-Modul „url“ (<code>\\usepackage{url}</code> im Bereich vo de Yyleitig) bruuchsch, wo e schöner formatierti Internetadress ussegit, cha die Ussgab, wo folgt, gno werde:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; abgruefe am <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Gujarati (ગુજરાતી)
 * @author Dsvyas
 * @author KartikMistry
 * @author Sushant savla
 */
$messages['gu'] = array(
	'cite_article_desc' => '[[Special:Cite|સંદર્ભ]] ખાસ પાનું અને સાધન પેટીની કડી ઉમેરે છે',
	'cite_article_link' => 'આ પાનું ટાંકો',
	'tooltip-cite-article' => 'આ પાનાંને સમર્થન કઈ રીતે આપવું તેની માહિતી',
	'cite' => 'ટાંકો',
	'cite_page' => 'પાનું:',
	'cite_submit' => 'ટાંકો',
);

/** Manx (Gaelg)
 * @author MacTire02
 */
$messages['gv'] = array(
	'cite_article_desc' => 'Cur duillag [[Special:Cite|symney]] er lheh as kiangley kishtey greie',
	'cite_article_link' => 'Symney yn duillag shoh',
	'cite' => 'Symney',
	'cite_page' => 'Duillag:',
	'cite_submit' => 'Symney',
);

/** Hausa (Hausa)
 */
$messages['ha'] = array(
	'cite_page' => 'Shafi:',
);

/** Hawaiian (Hawai`i)
 * @author Singularity
 */
$messages['haw'] = array(
	'cite_article_link' => "E ho'ōia i kēia mea", # Fuzzy
	'cite_page' => '‘Ao‘ao:',
);

/** Hebrew (עברית)
 * @author Amire80
 * @author Rotem Liss
 */
$messages['he'] = array(
	'cite_article_desc' => 'הוספת דף מיוחד וקישור בתיבת הכלים ל[[Special:Cite|ציטוט]]',
	'cite_article_link' => 'ציטוט דף זה',
	'tooltip-cite-article' => 'מידע כיצד לצטט דף זה',
	'cite' => 'ציטוט',
	'cite_page' => 'דף:',
	'cite_submit' => 'ציטוט',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== מידע ביבליוגרפי על {{FULLPAGENAME}} ==

* שם הדף: {{FULLPAGENAME}}
* מחבר: תורמי {{SITENAME}}
* מוציא לאור: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* תאריך השינוי האחרון: {{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* תאריך האחזור: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* קישור קבוע: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* קוד זיהוי גרסה: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== סגנונות ציטוט עבור {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. אוחזר <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> מתוך {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
תורמי {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [אוחזר <citation>{{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
תורמי {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (אוחזר <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE style]] ===
תורמי {{SITENAME}}. {{FULLPAGENAME}} [אינטרנט]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [צוטט <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. זמין בכתובת:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ביקור אחרון <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== ערך [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[מקוון; אוחזר <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

כאשר משתמשים ב־URL מחבילת [[LaTeX]] (באמצעות כתיבת \\usepackage{url} במקום כלשהו במבוא), המניבה כתובות אינטרנט המעוצבות טוב יותר, יש להעדיף את דרך הכתיבה הבאה:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[מקוון; אוחזר <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Hindi (हिन्दी)
 * @author Ansumang
 * @author Kaustubh
 * @author Siddhartha Ghai
 */
$messages['hi'] = array(
	'cite_article_desc' => '[[Special:Cite|सन्दर्भ]] देने वाला एक विशेष पृष्ठ और टूलबॉक्स कड़ी जोड़ता है',
	'cite_article_link' => 'इस पन्ने को उद्धृत करें',
	'tooltip-cite-article' => 'इस पृष्ठ को उद्धृत करने के लिये जानकारी',
	'cite' => 'उद्धृत करें',
	'cite_page' => 'पृष्ठ:',
	'cite_submit' => 'उद्धृत करें',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} के लिए उद्धरण जानकारी ==

* पृष्ठ नाम: {{FULLPAGENAME}}
* लेखक: {{SITENAME}} योगदानकर्ता
* प्रकाशक: ''{{SITENAME}}, {{int:sitesubtitle}}''।
* अंतिम संशोधन तिथि: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} यू॰टी॰सी
* अभिगमन तिथि: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* स्थायी यू॰आर॰एल: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* पृष्ठ अवतरण आई॰डी: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}} के लिए उद्धरण प्रकार ==

=== APA प्रकार ===
{{FULLPAGENAME}}। ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}})। ''{{SITENAME}}, {{int:sitesubtitle}}''। {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} से अभिगमन <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> को।

=== MLA प्रकार ===
\"{{FULLPAGENAME}}।\" ''{{SITENAME}}, {{int:sitesubtitle}}''। {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} यू॰टी॰सी। <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;।

=== MHRA प्रकार ===
{{SITENAME}} योगदानकर्ता, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} यू॰टी॰सी, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [अभिगमन <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation> को]

=== शिकागो प्रकार ===
{{SITENAME}} योगदानकर्ता, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (अभिगमन <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> को)।

=== CBE/CSE प्रकार ===
{{SITENAME}} योगदानकर्ता। {{FULLPAGENAME}} [इन्टरनेट]। {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} यू॰टी॰सी [<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation> उद्धृत]। {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} से उपलब्ध।

=== ब्लूबुक प्रकार ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (अभिगमन तिथि: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== बिबटेक्स प्रकार ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[ऑनलाइन; अभिगमन तिथि: <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

यदि LaTeX पैकेज यू॰आर॰एल का प्रयोग किया जा रहा हो(<code>\\usepackage{url}</code> प्रियेम्बल में कहीं प्रयुक्त हो) तो बेहतर स्वरूपण वाले यू॰आर॰एल के लिए निम्न का प्रयोग किया जा सकता है:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[ऑनलाइन; अभिगमन तिथि: <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Fiji Hindi (Latin script) (Fiji Hindi)
 * @author Karthi.dr
 */
$messages['hif-latn'] = array(
	'cite_page' => 'Panna:',
);

/** Hiligaynon (Ilonggo)
 * @author Jose77
 */
$messages['hil'] = array(
	'cite_article_link' => 'Tumuron ining artikulo',
);

/** Croatian (hrvatski)
 * @author Dalibor Bosits
 * @author Excaliboor
 * @author SpeedyGonsales
 */
$messages['hr'] = array(
	'cite_article_desc' => 'Dodaje posebnu stranicu za [[Special:Cite|citiranje]] i link u okvir za alate',
	'cite_article_link' => 'Citiraj ovaj članak',
	'tooltip-cite-article' => 'Informacije o tome kako citirati ovu stranicu',
	'cite' => 'Citiranje',
	'cite_page' => 'Stranica:',
	'cite_submit' => 'Citiraj',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'cite_article_desc' => 'Přidawa specialnu stronu [[Special:Cite|Citowanska pomoc]] a wotkaz w gratowym kašćiku',
	'cite_article_link' => 'Nastawk citować',
	'tooltip-cite-article' => 'Informacije wo tym, kak tuta strona hodźi so citować',
	'cite' => 'Citowanska pomoc',
	'cite_page' => 'Strona:',
	'cite_submit' => 'pokazać',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografiske podrobnosće za {{FULLPAGENAME}} ==

* Mjeno strony: {{FULLPAGENAME}}
* Awtor: sobuskutkowarjo projekta {{SITENAME}}
* Wudawaćel: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Datum poslednjeje wersije: {{CURRENTDAY}}. {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Datum wotwołanja: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC</citation>
* Trajny URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID wersije strony: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citowanske stile za {{FULLPAGENAME}} ==

=== [[APA stil]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Wotwołany dnja <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> z {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA-stil]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA-stil]] ===
Sobuskutkowarjo projekta {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [wotwołany dnja <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago-stil]] ===
Sobuskutkowarjo projekta {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (wotwołany dnja <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE-stil]] ===
Sobuskutkowarjo projekta {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citowany dnja <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. K dispoziciji wot:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook-stil]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (posledni raz wopytany <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-zapisk ===

  @misc{ wiki:xxx,
    awtor = \"{{SITENAME}}\",
    titul = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    lěto = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; wotwołany dnja <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Hdyž so paket [[LaTeX]] url (<code>\\usepackage{url}</code> něhdźe w preambli) wužiwa, kotryž zwjetša rjeńšo formatowane webadresy zmóžnja, móhli so slědowaće podaća wužiwać:

  @misc{ wiki:xxx,
    awtor = \"{{SITENAME}}\",
    titul = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    lěto = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; wotwołany dnja <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Haitian (Kreyòl ayisyen)
 * @author Masterches
 */
$messages['ht'] = array(
	'cite_article_desc' => 'Ajoute yon paj espesyal [[Special:Cite|sitasyon]] epitou yon lyen nan bwat zouti yo',
	'cite_article_link' => 'Site paj sa',
	'cite' => 'Sitasyon',
	'cite_page' => 'Paj:',
	'cite_submit' => 'Site',
);

/** Hungarian (magyar)
 * @author Dani
 * @author Glanthor Reviol
 * @author Tgr
 */
$messages['hu'] = array(
	'cite_article_desc' => '[[Special:Cite|Hivatkozás-készítő]] speciális lap és link az eszközdobozba',
	'cite_article_link' => 'Hogyan hivatkozz erre a lapra',
	'tooltip-cite-article' => 'Információk a lap idézésével kapcsolatban',
	'cite' => 'Hivatkozás',
	'cite_page' => 'Lap neve:',
	'cite_submit' => 'Mehet',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

'''FONTOS MEGJEGYZÉS:''' A legtöbb tanár és szakember nem tartja helyesnek a [[harmadlagos forrás]]ok – mint a lexikonok – kizárólagos forrásként való felhasználását. A Wiki cikkeket háttérinformációnak, vagy a további kutatómunka kiindulásaként érdemes használni.

Mint minden [[{{ns:project}}:Ki írja a Wikipédiát|közösség által készített]] hivatkozásnál, a wiki tartalmában is lehetségesek hibák vagy pontatlanságok: kérjük, több független forrásból ellenőrizd a tényeket és ismerd meg a [[{{ns:project}}:Jogi nyilatkozat|jogi nyilatkozatunkat]], mielőtt a wiki adatait felhasználod.

<div style=\"border: 1px solid grey; background: #E6E8FA; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== {{FULLPAGENAME}} lap adatai ==

* Lap neve: {{FULLPAGENAME}} 
* Szerző: Wiki szerkesztők
* Kiadó: ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. 
* A legutóbbi változat dátuma: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Letöltés dátuma: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Állandó hivatkozás: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Lapváltozat-azonosító: {{REVISIONID}}

Légy szíves, ellenőrizd, hogy ezek az adatok megfelelnek-e a kívánalmaidnak.  További információhoz lásd az '''[[{{ns:project}}:Idézés a Wikipédiából|Idézés a Wikipédiából]]''' lapot.

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Idézési stílusok a(z) {{FULLPAGENAME}} laphoz ==

=== APA stílus ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. Retrieved <citation>{{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}. {{CURRENTTIME}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA stílus ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. {{CURRENTYEAR}}. {{CURRENTMONTHABBREV}}. {{CURRENTDAY}}. {{CURRENTTIME}} UTC. <citation>{{CURRENTYEAR}}. {{CURRENTMONTHABBREV}}. {{CURRENTDAY}}. {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA stílus ===
Wiki szerkesztők, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}. {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}.</citation>]

=== Chicago stílus ===
Wiki szerkesztők, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}.</citation>).

=== CBE/CSE stílus ===
wiki szerkesztők. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{MediaWiki:Sitesubtitle}};  {{CURRENTYEAR}}. {{CURRENTMONTHABBREV}}. {{CURRENTDAY}}.  {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}}. {{CURRENTMONTHABBREV}}. {{CURRENTDAY}}.</citation>].  Elérhető: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebook stílus ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTYEAR}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}}.</citation>).

=== [[BibTeX]] bejegyzés ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Az <code>url</code> nevű [[LaTeX]] csomag használata esetén (<code>\\usepackage{url}</code> a preambulumban), amely a webes hivatkozások formázásában nyújt segítséget, a következő forma ajánlott:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing \"Citation styles\" div-->", # Fuzzy
);

/** Armenian (Հայերեն)
 * @author Chaojoker
 * @author Teak
 */
$messages['hy'] = array(
	'cite_article_link' => 'Քաղվածել հոդվածը', # Fuzzy
	'cite' => 'Քաղվածում',
	'cite_page' => 'Էջ.',
	'cite_submit' => 'Քաղվածել',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} էջի մատենագրական մանրամասներ ==

* Էջանուն՝ {{FULLPAGENAME}}
* Հեղինակ՝ {{SITENAME}} contributors
* Հրատարակիչ՝ ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Վերջինն վերանայման թիվ՝ {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} ՀԿԺ
* Վերստացման թիվ՝ <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} ՀԿԺ</citation>
* Մշտական հասցե՝ {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Էջի վարկածի թիվ՝ {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}} էջի մեջբերման ոճեր ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}})։ ''{{SITENAME}}, {{int:sitesubtitle}}''։ Վերստացված է՝ <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}} թվին՝</citation> {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}-ից։

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}։\" ''{{SITENAME}}, {{int:sitesubtitle}}''։ {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} ՀԿԺ։ <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;։

=== [[MHRA Style Guide|MHRA style]] ===
{{SITENAME}} կայքի ներդնողներ, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} ՀԿԺ, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [վերստացված է՝ <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
{{SITENAME}} կայքի ներդնողներ, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (վերստացված է՝ <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>)։

=== [[Council of Science Editors|CBE/CSE style]] ===
{{SITENAME}} կայքի ներդնողներ։ {{FULLPAGENAME}} [Համացանց]։ {{SITENAME}}, {{int:sitesubtitle}}․ {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} ՀԿԺ [մեջբերած՝ <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]։ Հասանելի է՝
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}։

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (վերջին այցելություն՝ <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>)։

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[Առցանց․ վերստացված է՝ <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

[[ԼաՏեԽ]] փաթեթային հասցեն (<code>\\usepackage{url}</code> օգտագործելիս, որը շատ ավելի գեղեցկորեն ոճավորված է ցուցադրում կայքերի հասցեները, կարելի է հետևյալը նախընտրել՝

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[Առցանց․ վերստացված է՝ <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Interlingua (interlingua)
 * @author Malafaya
 * @author McDutchie
 */
$messages['ia'] = array(
	'cite_article_desc' => 'Adde un pagina special de [[Special:Cite|citation]] e un ligamine verso le instrumentario',
	'cite_article_link' => 'Citar iste pagina',
	'tooltip-cite-article' => 'Informationes super como citar iste pagina',
	'cite' => 'Citation',
	'cite_page' => 'Pagina:',
	'cite_submit' => 'Citar',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Detalios bibliographic sur {{FULLPAGENAME}} ==

* Nomine del pagina: {{FULLPAGENAME}}
* Autor: {{SITENAME}} contributors
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data del ultime version: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data de recuperation: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Adresse URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID del version del pagina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stilos de citation pro {{FULLPAGENAME}} ==

=== [[:en:APA style|Stilo APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Recuperate le <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} {{CURRENTYEAR}} a {{CURRENTTIME}}</citation> ab {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:The MLA style manual|Stilo MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[:en:MHRA Style Guide|Stilo MHRA]] ===
Contributores a {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' le {{CURRENTDAY}} de {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [consultate le <citation>{{CURRENTDAY}} de {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[:en:The Chicago Manual of Style|Stilo Chicago]] ===
Contributores a {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (consultate le <citation>{{CURRENTMONTHNAME}} de {{CURRENTDAY}} {{CURRENTYEAR}}</citation>).

=== [[:en:Council of Science Editors|Stilo CBE/CSE]] ===
Contributores a {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citate <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponibile a:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:Bluebook|Stilo Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (visitate ultimemente le <citation>le {{CURRENTDAY}} de {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== Entrata [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[In linea; consultate le <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Quando usar le URL de pacchetto [[LaTeX]] (<code>\\usepackage{url}</code> in qualque parte del preambulo) que tende a resultar in adresses web con formato multo plus agradabile, le sequente pote esser preferite:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[In linea; consultate le <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Indonesian (Bahasa Indonesia)
 * @author Bennylin
 * @author Farras
 * @author IvanLanin
 */
$messages['id'] = array(
	'cite_article_desc' => 'Menambahkan halaman istimewa [[Special:Cite|kutipan]] dan pranala pada kotak peralatan',
	'cite_article_link' => 'Kutip halaman ini',
	'tooltip-cite-article' => 'Informasi tentang bagaimana mengutip halaman ini',
	'cite' => 'Kutip',
	'cite_page' => 'Halaman:',
	'cite_submit' => 'Kutip',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Rincian bibliografis untuk {{FULLPAGENAME}} ==

* Nama halaman: {{FULLPAGENAME}} 
* Pengarang: Para kontributor {{SITENAME}}
* Penerbit: ''{{SITENAME}}, {{int:sitesubtitle}}''. 
* Tanggal revisi terakhir: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tanggal akses: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Pranala permanen: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID versi halaman: {{REVISIONID}}

</div>
<div class=\"plainlinks\" style=\"border: 1px solid grey; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== Format pengutipan untuk {{FULLPAGENAME}} ==

=== [[Gaya APA|Format APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Diakses pada <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> dari {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Manual gaya MLA|Format MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Format MHRA]] ===
Para kontributor {{SITENAME}}, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [diakses pada <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Format Chicago]] ===
Para kontributor {{SITENAME}}, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (diakses pada <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Format CBE/CSE]] ===
Para kontributor {{SITENAME}}. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{int:sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}},   {{CURRENTTIME}} UTC [dikutip pada <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>].  Tersedia dari: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Format Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (terakhir dikunjungi pada <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entri [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Saat menggunakan url paket [[LaTeX]] (<code>\\usepackage{url}</code> di manapun di bagian pembuka) yang biasanya menghasilkan alamat-alamat web yang diformat dengan lebih baik, cara berikut ini lebih disarankan:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Interlingue (Interlingue)
 * @author Malafaya
 */
$messages['ie'] = array(
	'cite_page' => 'Págine:',
);

/** Igbo (Igbo)
 * @author Ukabia
 */
$messages['ig'] = array(
	'cite_article_desc' => 'Nè tí [[Special:Cite|ndéputà]] ihü kárírí na jikodo ngwa ọru',
	'cite_article_link' => 'Députà ihüa',
	'tooltip-cite-article' => 'Ùmà màkà otụ ha shi députà ihe na ihüa',
	'cite' => 'Ndéputà',
	'cite_page' => 'Ihü:',
	'cite_submit' => 'Ndéputà',
);

/** Iloko (Ilokano)
 * @author Lam-ang
 */
$messages['ilo'] = array(
	'cite_article_desc' => 'Agnayon ti [[Special:Cite|dakamat]] ti naipangpangruna a panid ken panilpo ti ramramit',
	'cite_article_link' => 'Dakamaten daytoy a panid',
	'tooltip-cite-article' => 'Pakaammo no kasanu ti panagdakamat daytoy a panid',
	'cite' => 'Dakamaten',
	'cite_page' => 'Panid:',
	'cite_submit' => 'Dakamaten',
);

/** Ido (Ido)
 * @author Malafaya
 */
$messages['io'] = array(
	'cite_article_desc' => 'Ico adjuntas specala pagino e ligilo por [[Special:Cite|citaji]] en utensilo-buxo',
	'cite_article_link' => 'Citar ca pagino',
	'cite' => 'Citar',
	'cite_page' => 'Pagino:',
	'cite_submit' => 'Citar',
);

/** Icelandic (íslenska)
 * @author S.Örvarr.S
 * @author לערי ריינהארט
 */
$messages['is'] = array(
	'cite_article_link' => 'Vitna í þessa síðu',
	'cite' => 'Vitna í síðu',
	'cite_page' => 'Síða:',
	'cite_submit' => 'Vitna í',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliographic details for {{FULLPAGENAME}} ==

* Page name: {{FULLPAGENAME}}
* Author: {{SITENAME}} contributors
* Publisher: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Date of last revision: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Date retrieved: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanent URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Page Version ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citation styles for {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE style]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may be preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Italian (italiano)
 * @author Beta16
 * @author BrokenArrow
 * @author Ximo17
 */
$messages['it'] = array(
	'cite_article_desc' => 'Aggiunge una pagina speciale per le [[Special:Cite|citazioni]] e un collegamento negli strumenti',
	'cite_article_link' => 'Cita questa pagina',
	'tooltip-cite-article' => 'Informazioni su come citare questa pagina',
	'cite' => 'Citazione',
	'cite_page' => 'Pagina da citare:',
	'cite_submit' => 'Crea la citazione',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Dettagli bibliografici per {{FULLPAGENAME}} ==

* Titolo pagina: {{FULLPAGENAME}}
* Autore: contributori {{SITENAME}}
* Editore: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data dell'ultima modifica: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data estrazione: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID versione pagina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stili citazioni per {{FULLPAGENAME}} ==

=== [[APA style|Stile APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Estratto il <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> da {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Stile MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Stile MHRA]] ===
Contributori {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accesso il <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Stile Chicago]] ===
Contributori {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accesso il <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Stile CBE/CSE]] ===
Contributori {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citato il <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponibile su:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Stile Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ultima visita il <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accesso il <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Quando si usa il pacchetto [[LaTeX]] per url (<code>\\usepackage{url}</code> da qualche parte nel preambolo) che in genere dà indirizzi web formattati in modo migliore, è preferibile usare il seguente codice:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accesso il <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Japanese (日本語)
 * @author Aotake
 * @author Fryed-peach
 * @author JtFuruhata
 * @author Shirayuki
 * @author Suisui
 * @author Whym
 */
$messages['ja'] = array(
	'cite_article_desc' => '[[Special:Cite|引用情報]]の特別ページとツールボックスのリンクを追加する',
	'cite_article_link' => 'このページを引用',
	'tooltip-cite-article' => 'このページの引用方法',
	'cite' => '引用',
	'cite_page' => 'ページ:',
	'cite_submit' => '引用',
	'cite_text' => '__NOTOC__
<div class="mw-specialcite-bibliographic">

== 「{{FULLPAGENAME}}」の書誌情報 ==

* ページ名: {{FULLPAGENAME}}
* 著者: {{SITENAME}}への寄稿者ら
* 発行者: {{int:sitesubtitle}}『{{SITENAME}}』
* 更新日時: {{CURRENTYEAR}}年{{CURRENTMONTHNAME}}{{CURRENTDAY}}日 {{CURRENTTIME}} (UTC)
* 取得日時: <citation>{{CURRENTYEAR}}年{{CURRENTMONTHNAME}}{{CURRENTDAY}}日 {{CURRENTTIME}} (UTC)</citation>
* 恒久的なURI: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ページの版番号: {{REVISIONID}}

</div>
<div class="plainlinks mw-specialcite-styles">

== 各種方式による「{{FULLPAGENAME}}」の書誌表示 ==

=== [[APA方式]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}年{{CURRENTMONTHNAME}}{{CURRENTDAY}}日{{CURRENTTIME}}). \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. <citation>{{CURRENTYEAR}}年{{CURRENTMONTHNAME}}{{CURRENTDAY}}日{{CURRENTTIME}}</citation> {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} にて閲覧.

=== [[The MLA style manual|MLA方式]] ===
"{{FULLPAGENAME}}." \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. {{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日{{CURRENTTIME}} (UTC). <citation>{{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日{{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA方式]] ===
{{SITENAME}}への寄稿者ら, \'{{FULLPAGENAME}}\', \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\'{{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日 (UTC), &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [<citation>{{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日</citation>閲覧]

=== [[The Chicago Manual of Style|Chicago方式]] ===
{{SITENAME}}への寄稿者ら, "{{FULLPAGENAME}}," \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日</citation>閲覧).

=== [[Council of Science Editors|CBE/CSE方式]] ===
{{SITENAME}}への寄稿者ら. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日{{CURRENTTIME}} (UTC) [<citation>{{CURRENTYEAR}}年{{CURRENTMONTHABBREV}}{{CURRENTDAY}}日</citation>現在で引用]. 入手元:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook方式]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTYEAR}}年{{CURRENTMONTHNAME}}{{CURRENTDAY}}日</citation>最終訪問).

=== [[BibTeX]]エントリ ===

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}",
    note = "[オンライン; 閲覧日時 <citation>{{CURRENTYEAR}}-{{CURRENTDAY}}-{{CURRENTMONTH}}</citation>]"
  }

URIの体裁を整えるために[[LaTeX]]の url パッケージを用いる (プリアンブルのどこかに <code>\\usepackage{url}</code> と書く) 場合は、以下のようにした方がいいかもしれません。

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "\'\'\'\\url{\'\'\'{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\'\'\'}\'\'\'",
    note = "[オンライン; 閲覧日時 <citation>{{CURRENTYEAR}}-{{CURRENTDAY}}-{{CURRENTMONTH}}</citation>]"
  }


</div> <!--closing div for "plainlinks"-->',
);

/** Jutish (jysk)
 * @author Huslåke
 */
$messages['jut'] = array(
	'cite_article_link' => 'Fodnåter denne ertikel',
	'cite' => 'Fodnåt',
	'cite_page' => 'Side:',
	'cite_submit' => 'Fodnåt',
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 * @author NoiX180
 */
$messages['jv'] = array(
	'cite_article_desc' => 'Nambahaké kaca astaméwa [[Special:Cite|sitat (kutipan)]] lan pranala ing kothak piranti',
	'cite_article_link' => 'Kutip (sitir) kaca iki',
	'tooltip-cite-article' => 'Informasi ngenani carané ngutip kaca iki',
	'cite' => 'Kutip (sitir)',
	'cite_page' => 'Kaca:',
	'cite_submit' => 'Kutip (sitir)',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Rincian bibliograpi kanggo {{FULLPAGENAME}} ==

* Jeneng kaca: {{FULLPAGENAME}}
* Panganggit: {{SITENAME}} kontributor
* Panyithak: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Tanggal rèvisi pungkasan: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tanggal njupuk: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanèn: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID Vèrsi Kaca: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Gagrag kutipan kanggo {{FULLPAGENAME}} ==

=== [[APA style|Gagrag APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Dijupuk <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> saka {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Gagrag MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Gagrag MHRA]] ===
{{SITENAME}} kontributor, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [diaksès <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Gagrag Chicago]] ===
{{SITENAME}} kontributor, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (diaksès <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Gagrag CBE/CSE]] ===
{{SITENAME}} kontributor. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [dikutip <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Sumadhiya saka:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Gagrag Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ditekani pungkasan <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== Isi [[BibTeX]] ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[Online; diaksès <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Yèn nganggo url pakèt [[LaTeX]] (<code>\\usepackage{url}</code> ngendi waé nèng pambuka) sing bakal ndadèkaké alamat wèb sing dipormat dadi luwih èndah, sing ngisor iki disaranaké:

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[Online; diaksès <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Georgian (ქართული)
 * @author BRUTE
 * @author David1010
 * @author Malafaya
 * @author გიორგიმელა
 */
$messages['ka'] = array(
	'cite_article_desc' => 'ამატებს [[Special:Cite|ციტირების]] სპეციალურ გვერდს ხელსაწყოებში',
	'cite_article_link' => 'ამ გვერდის ციტირება',
	'tooltip-cite-article' => 'ინფორმაცია ამ გვერდის ციტირების შესახებ',
	'cite' => 'ციტირება',
	'cite_page' => 'გვერდი:',
	'cite_submit' => 'ციტირება',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== ბიბლიოგრაფიული დეტალები სტატიისათვის {{FULLPAGENAME}} ==

* გვერდის სახელი: {{FULLPAGENAME}}
* ავტორი: {{SITENAME}} contributors
* გამომქვეყნებელი: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* ბოლო ცვლილების თარიღი: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* ჩატვირთვის თარიღი: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* მუდმივი URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* გვერდის ვერსიის ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== სტილის ციტირება სტატიისათვის {{FULLPAGENAME}} ==

=== [[APA სტილი]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA სტილი]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA სტილი]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[ჩიკაგოს სტილი]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[CBE/CSE სტილი]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook სტილი]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-ის ჩანაწერი ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

[[LaTeX]]-ის პაკეტის url-ს გამოყენებისას ვებ-გვერდების უფრო თვალსაჩინო წარმოდგენისათვის (<code>\\usepackage{url}</code> პრეამბულაში), სავარაუდოდ უკეთესი იქნება მიუთითოთ:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Kazakh (Arabic script) (قازاقشا (تٴوتە)‏)
 */
$messages['kk-arab'] = array(
	'cite_article_link' => 'بەتتەن دايەكسوز الۋ',
	'cite' => 'دايەكسوز الۋ',
	'cite_page' => 'بەت اتاۋى:',
	'cite_submit' => 'دايەكسوز ال!',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== «{{FULLPAGENAME}}» اتاۋىلى بەتىنىڭ كىتاپنامالىق ەگجەي-تەگجەيلەرى ==

* بەتتىڭ اتاۋى: {{FULLPAGENAME}}
* اۋتورى: {{SITENAME}} ۇلەسكەرلەرى
* باسپاگەرى: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* سوڭعى نۇسقاسىنىڭ كەزى: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* الىنعان كەزى: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* تۇراقتى سىلتەمەسى: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* بەت نۇسقاسىنىڭ تەڭدەستىرۋ ٴنومىرى: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== «{{FULLPAGENAME}}» بەتىنىڭ دايەكسوز مانەرلەرى ==

=== [[گوست مانەرى]] ===
<!-- ([[گوست 7.1|گوست 7.1—2003]] جانە [[گوست 7.82|گوست 7.82—2001]]) -->
{{SITENAME}}, {{int:sitesubtitle}} [ەلەكتروندى قاينار] : {{FULLPAGENAME}}, نۇسقاسىنىڭ ٴنومىرى {{REVISIONID}}, سوڭعى تۇزەتۋى {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC / ۋىيكىيپەدىييا اۋتورلارى. — ەلەكتروندى دەرەك. — فلورىيدا شتاتى. : ۋىيكىيمەدىييا قورى, {{CURRENTYEAR}}. — قاتىناۋ رەتى: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}

=== [[APA مانەرى]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}'' ماعلۇماتى. {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} بەتىنەن <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> كەزىندە الىنعان.

=== [[MLA مانەرى]] ===
«{{FULLPAGENAME}}». ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> <{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}>.

=== [[MHRA مانەرى]] ===
{{SITENAME}} ۇلەسكەرلەرى, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, <{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}> [<citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation> كەزىندە قاتىنالدى]

=== [[شىيكاگو مانەرى]] ===
{{SITENAME}} ۇلەسكەرى, «{{FULLPAGENAME}}», ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> كەزىندە قاتىنالدى).

=== [[CBE/CSE مانەرى]] ===
{{SITENAME}} ۇلەسكەرلەرى. {{FULLPAGENAME}} [ىينتەرنەت]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation> كەزىندە دايەكسوز الىندى]. قاتىناۋى:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[كوك كىتاپ|كوك كىتاپ مانەرى]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (سوڭعى قارالعانى <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> كەزىندە).

=== [[BibTeX]] جازباسى ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[جەلىدەن; <citation>{{CURRENTDAY}}-{CURRENTMONTHNAME}}-{CURRENTYEAR}}</citation> كەزىندە قاتىنالدى]\"
  }

[[LaTeX]] بۋماسىنىڭ URL جايىن (<code>\\usepackage{url}</code> كىرىسپەنىڭ قايبىر ورنىندا) قولدانعاندا (ۆەب جايلارىن ونەرلەۋ پىشىمدەۋىن كەلتىرەدى) كەلەسىسىن قالاۋعا بولادى:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[جەلىدەن; <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation> كەزىندە قاتىنالدى]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Kazakh (Cyrillic script) (қазақша (кирил)‎)
 * @author Kaztrans
 */
$messages['kk-cyrl'] = array(
	'cite_article_desc' => '[[Special:Cite|Дәйексөз]] арнайы бетін және құрал сілтемесін қосады',
	'cite_article_link' => 'Беттен дәйексөз алу',
	'cite' => 'Дәйексөз алу',
	'cite_page' => 'Бет атауы:',
	'cite_submit' => 'Дәйексөз ал!',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== «{{FULLPAGENAME}}» атауылы бетінің кітапнамалық егжей-тегжейлері ==

* Беттің атауы: {{FULLPAGENAME}}
* Ауторы: {{SITENAME}} үлескерлері
* Баспагері: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Соңғы нұсқасының кезі: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Алынған кезі: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Тұрақты сілтемесі: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Бет нұсқасының теңдестіру номірі: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== «{{FULLPAGENAME}}» бетінің дәйексөз мәнерлері ==

=== [[ГОСТ мәнері]] ===
<!-- ([[ГОСТ 7.1|ГОСТ 7.1—2003]] және [[ГОСТ 7.82|ГОСТ 7.82—2001]]) -->
{{SITENAME}}, {{int:sitesubtitle}} [Электронды қайнар] : {{FULLPAGENAME}}, нұсқасының нөмірі {{REVISIONID}}, соңғы түзетуі {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC / Уикипедия ауторлары. — Электронды дерек. — Флорида штаты. : Уикимедия Қоры, {{CURRENTYEAR}}. — Қатынау реті: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}

=== [[APA мәнері]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}'' мағлұматы. {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} бетінен <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> кезінде алынған.

=== [[MLA мәнері]] ===
«{{FULLPAGENAME}}». ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA мәнері]] ===
{{SITENAME}} үлескерлері, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [<citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation> кезінде қатыналды]

=== [[Шикаго мәнері]] ===
{{SITENAME}} үлескері, «{{FULLPAGENAME}}», ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> кезінде қатыналды).

=== [[CBE/CSE мәнері]] ===
{{SITENAME}} үлескерлері. {{FULLPAGENAME}} [Интернет]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation> кезінде дәйексөз алынды]. Қатынауы:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Көк кітап|Көк кітап мәнері]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (соңғы қаралғаны <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> кезінде).

=== [[BibTeX]] жазбасы ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Желіден; <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation> кезінде қатыналды]\"
  }

[[LaTeX]] бумасының URL жайын (<code>\\usepackage{url}</code> кіріспенің қайбір орнында) қолданғанда (веб жайларын өнерлеу пішімдеуін келтіреді) келесісін қалауға болады:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Желіден; <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation> кезінде қатыналды]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Kazakh (Latin script) (qazaqşa (latın)‎)
 */
$messages['kk-latn'] = array(
	'cite_article_link' => 'Betten däýeksoz alw',
	'cite' => 'Däýeksöz alw',
	'cite_page' => 'Bet atawı:',
	'cite_submit' => 'Däýeksöz al!',
	'cite_text' => "__NOTOC__
<div style=\"border: 1px solid grey; background: #E6E8FA; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== «{{FULLPAGENAME}}» atawılı betiniñ kitapnamalıq egjeý-tegjeýleri ==

* Bettiñ atawı: {{FULLPAGENAME}}
* Awtorı: {{SITENAME}} üleskerleri
* Baspageri: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Soñğı nusqasınıñ kezi: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Alınğan kezi: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Turaqtı siltemesi: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Bet nusqasınıñ teñdestirw nomiri: {{REVISIONID}}

</div>
<div class=\"plainlinks\" style=\"border: 1px solid grey; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== «{{FULLPAGENAME}}» betiniñ däýeksöz mänerleri ==

=== [[GOST mäneri]] ===
<!-- ([[GOST 7.1|GOST 7.1—2003]] jäne [[GOST 7.82|GOST 7.82—2001]]) -->
{{SITENAME}}, {{int:sitesubtitle}} [Élektrondı qaýnar] : {{FULLPAGENAME}}, nusqasınıñ nömiri {{REVISIONID}}, soñğı tüzetwi {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC / Wïkïpedïya awtorları. — Élektrondı derek. — Florïda ştatı. : Wïkïmedïya Qorı, {{CURRENTYEAR}}. — Qatınaw reti: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}

=== [[APA mäneri]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}'' mağlumatı. {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} betinen <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> kezinde alınğan.

=== [[MLA mäneri]] ===
«{{FULLPAGENAME}}». ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA mäneri]] ===
{{SITENAME}} üleskerleri, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [<citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation> kezinde qatınaldı]

=== [[Şïkago mäneri]] ===
{{SITENAME}} üleskeri, «{{FULLPAGENAME}}», ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> kezinde qatınaldı).

=== [[CBE/CSE mäneri]] ===
{{SITENAME}} üleskerleri. {{FULLPAGENAME}} [Ïnternet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation> kezinde däýeksöz alındı]. Qatınawı:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Kök kitap|Kök kitap mäneri]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (soñğı qaralğanı <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> kezinde).

=== [[BibTeX]] jazbası ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Jeliden; <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation> kezinde qatınaldı]\"
  }

[[LaTeX]] bwmasınıñ URL jaýın (<code>\\usepackage{url}</code> kirispeniñ qaýbir ornında) qoldanğanda (veb jaýların önerlew pişimdewin keltiredi) kelesisin qalawğa boladı:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Jeliden; <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation> kezinde qatınaldı]\"
  }


</div> <!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Kalaallisut (kalaallisut)
 * @author Qaqqalik
 */
$messages['kl'] = array(
	'cite_article_link' => 'Una qupperneq issuaruk',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 * @author Lovekhmer
 * @author គីមស៊្រុន
 */
$messages['km'] = array(
	'cite_article_link' => 'ប្រភពនៃទំព័រនេះ',
	'tooltip-cite-article' => 'ព័ត៌មានអំពីការយោងមកអត្ថបទនេះ',
	'cite' => 'ការយោង',
	'cite_page' => 'ទំព័រ ៖',
	'cite_submit' => 'ដាក់ការយោង',
);

/** Kannada (ಕನ್ನಡ)
 * @author Nayvik
 * @author Shushruth
 */
$messages['kn'] = array(
	'cite_article_link' => 'ಈ ಪುಟವನ್ನು ಉಲ್ಲೇಖಿಸಿ',
	'cite' => 'ಉಲ್ಲೇಖಿಸಿ',
	'cite_page' => 'ಪುಟ:',
);

/** Korean (한국어)
 * @author Kwj2772
 * @author ToePeu
 * @author 관인생략
 * @author 아라
 */
$messages['ko'] = array(
	'cite_article_desc' => '[[Special:Cite|인용]] 특수 문서와 도구모음 링크를 추가합니다',
	'cite_article_link' => '이 문서 인용하기',
	'tooltip-cite-article' => '이 문서를 인용하는 방법에 대한 정보',
	'cite' => '인용',
	'cite_page' => '문서:',
	'cite_submit' => '인용',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}}의 출처 정보 ==

* 문서 이름: {{FULLPAGENAME}}
* 저자: {{SITENAME}} 기여자
* 발행처: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* 최신 판의 날짜: {{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일 {{CURRENTTIME}} UTC
* 확인한 날짜: <citation>{{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일 {{CURRENTTIME}} UTC</citation>
* 고유 URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* 문서 판 ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}}의 인용 양식 ==

=== [[APA 양식]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일). ''{{SITENAME}}, {{int:sitesubtitle}}''. <citation>{{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일, {{CURRENTTIME}}</citation>에 확인 {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} 에서 찾아볼 수 있음.

=== [[MLA 양식]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA 양식]] ===
{{SITENAME}} 기여자, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [<citation>{{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일</citation>에 접근]

=== [[시카고 양식]] ===
{{SITENAME}} 기여자, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일</citation>에 접근).

=== [[Council of Science Editors|CBE/CSE 양식]] ===
{{SITENAME}} 기여자. {{FULLPAGENAME}} [인터넷]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>에 인용]. 다음에서 찾아볼 수 있음:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[블루북|블루북 양식]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (<citation>{{CURRENTYEAR}}년 {{CURRENTMONTHNAME}} {{CURRENTDAY}}일</citation>에 마지막으로 방문함).

=== [[BibTeX]] 기록 ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[온라인; 접근한 날짜 <citation>{{CURRENTYEAR}}년-{{CURRENTMONTHNAME}}-{{CURRENTDAY}}일</citation>]\"
  }

[[LaTeX]] 패키지 URL (프리앰블의 어딘가에 <code>\\usepackage{url}</code>)을 사용하면 더 정돈된 형식의 웹 주소를 얻을 수 있습니다. 다음과 같은 방법을 선호합니다:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[온라인; 접근한 날짜 <citation>{{CURRENTYEAR}}년-{{CURRENTMONTHNAME}}-{{CURRENTDAY}}일</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Karachay-Balkar (къарачай-малкъар)
 * @author Iltever
 */
$messages['krc'] = array(
	'cite_article_link' => 'Бетни цитата эт',
	'tooltip-cite-article' => 'Бу бетни къалай цитата этерге керек болгъаныны юсюнден информация',
	'cite' => 'Цитата этиу',
);

/** Kinaray-a (Kinaray-a)
 * @author Jose77
 */
$messages['krj'] = array(
	'cite_page' => 'Pahina:',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'cite_article_desc' => 'Brenk de Sondersigg „[[Special:Cite|Ziteere]]“ un ene Link onger „{{int:toolbox}}“.',
	'cite_article_link' => 'Di Sigk Zitteere',
	'tooltip-cite-article' => 'Enfommazjuhne doh drövver, wi mer heh di Sigg zitteere sullt.',
	'cite' => 'Zittiere',
	'cite_page' => 'Sigk:',
	'cite_submit' => 'Zittėere',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== De biblejojraafesche Aanjabe för di Sigg „{{FULLPAGENAME}}“ ==

* Siggetittel: {{FULLPAGENAME}}
* Schriever: Beärbeider {{GRAMMAR:Genitive|{{SITENAME}}}}
* Rußjävver: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Et läz jändert aam: {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}} öm {{CURRENTTIME}} Uhr (UTC)
* Affjeroofe aam: <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}} öm {{CURRENTTIME}} Uhr (UTC)</citation>
* URL met Beschtand: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Version: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== De Zitatstile för di Sigg „{{FULLPAGENAME}}“ ==

=== Noh dä [[APA iehre Schtil|APA iehren Schtil]] ===
{{FULLPAGENAME}}. ({{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> vun {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Noh de [[MLA style manual|MLA iehrem Schtil-Handbooch]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== Nohm [[MHRA Style Guide|MHRA Schtil-Föhrer]] ===
Beärbeider {{GRAMMAR:Genitive|{{SITENAME}}}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== Nohm [[Chicago Manual of Style|Chicago-Schtil-Handbooch]] ===
Beärbeider {{GRAMMAR:Genitive|{{SITENAME}}}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== Nohm Schtil vum [[Council of Science Editors|Rood vun de wesseschafflije Schriever (CBE/CSE)]] ===
Beärbeider {{GRAMMAR:Genitive|{{SITENAME}}}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC [zitteerd aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}</citation>]. Affroofbaa onger:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Nohm Schtil vum [[Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>).

=== Als ene [[BibTeX]]-Endraach ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }

Dat [[LaTeX]]-Modul „url“ määd_en schönere Internet-Addräß. 
Wam_mer <code>\\usepackage{url}</code> em Einleidongsberett hät, kam_mer dat heh nämme:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; affjeroofe aam <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing div for \"plainlinks\"-->",
);

/** Kurdish (Latin script) (Kurdî (latînî)‎)
 * @author George Animal
 * @author Ghybu
 */
$messages['ku-latn'] = array(
	'cite_article_link' => 'Qalkirina rûpelê bibîne',
	'tooltip-cite-article' => 'Agahdariya li ser qalkirina rûpelê',
	'cite_page' => 'Rûpel:',
);

/** Cornish (kernowek)
 * @author Kernoweger
 * @author Kw-Moon
 * @author Nrowe
 */
$messages['kw'] = array(
	'cite_article_link' => 'Devynna an erthygel-ma',
	'tooltip-cite-article' => 'Kedhlow war fatel dhevynnir an folen-ma',
	'cite' => 'Devynna',
	'cite_page' => 'Folen:',
	'cite_submit' => 'Devynna',
);

/** Latin (Latina)
 * @author MissPetticoats
 * @author SPQRobin
 * @author UV
 */
$messages['la'] = array(
	'cite_article_desc' => ' Addet [[Special:Cite|citation]] specialem paginam et arcam instrumenti', # Fuzzy
	'cite_article_link' => 'Hanc paginam citare',
	'cite' => 'Paginam citare',
	'cite_page' => 'Pagina:',
	'cite_submit' => 'Citare',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Kaffi
 * @author Robby
 */
$messages['lb'] = array(
	'cite_article_desc' => "Setzt eng [[Special:Cite|Zitatioun op dëser Spezialsäit]] bäi an e Link an d'Geschiirkëscht",
	'cite_article_link' => 'Dës Säit zitéieren',
	'tooltip-cite-article' => 'Informatioune wéi een dës Säit zitéiere kann',
	'cite' => 'Zitéierhëllef',
	'cite_page' => 'Säit:',
	'cite_submit' => 'weisen',
);

/** Lezghian (лезги)
 * @author Migraghvi
 */
$messages['lez'] = array(
	'cite' => 'Цитата гъин',
	'cite_page' => 'Ччин:',
	'cite_submit' => 'Цитата гъин',
);

/** Lingua Franca Nova (Lingua Franca Nova)
 * @author Malafaya
 */
$messages['lfn'] = array(
	'cite_page' => 'Paje:',
);

/** Ganda (Luganda)
 * @author Kizito
 */
$messages['lg'] = array(
	'cite_article_link' => 'Juliza olupapula luno',
	'tooltip-cite-article' => "Amagezi agakwata ku ngeri ey'okujuliz'olupapula luno",
	'cite' => 'Juliza',
	'cite_page' => 'Lupapula:',
	'cite_submit' => 'Kakasa okujuliza',
);

/** Limburgish (Limburgs)
 * @author Ooswesthoesbes
 * @author Pahles
 */
$messages['li'] = array(
	'cite_article_desc' => "Voog 'n [[Special:Cite|speciaal pagina óm te citere]] toe en 'ne link derhaer in de gereidsjapskis",
	'cite_article_link' => 'Citeer dees pagina',
	'tooltip-cite-article' => 'Informatie euver wie se dees pazjena kins citere',
	'cite' => 'Citere',
	'cite_page' => 'Pagina:',
	'cite_submit' => 'Citere',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografische gegaeves veur {{FULLPAGENAME}} ==

* Paginanaam: {{FULLPAGENAME}}
* Sjriever: {{SITENAME}}-biedragers
* Oetgaever: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Tiedstip lèste versie: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tiedstip geraodplieëgd: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanente URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Paginaversienómmer: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citaatstiel veur {{FULLPAGENAME}} ==

=== [[APA style|APA-stiel]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Geraodplieëg op <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> van {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA-stiel]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA-stiel]] ===
{{SITENAME}}-biedragers, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [geraodplieëg <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicagostiel]] ===
{{SITENAME}}-biedragers, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (geraodplieëg <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE-stiel]] ===
{{SITENAME}}-biedragers. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cetaot van <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Besjikbaar op:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebookstiel]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (geraodplieëg op <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-gegaeves ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; geraadpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

't Volgendje kan de veurkäör höbben es de [[LaTeX]]-moduul \"url\" wuuertj gebroek (<code>\\usepackage{url}</code> örges in de inleiding), die webadresse sjónder opgemaak:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; geraadpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** lumbaart (lumbaart)
 * @author Dakrismeno
 */
$messages['lmo'] = array(
	'cite_article_link' => 'Cita quela vus chì',
	'cite' => 'Cita una vus',
);

/** Lao (ລາວ)
 */
$messages['lo'] = array(
	'cite_article_link' => 'ອ້າງອີງບົດຄວາມນີ້',
	'cite' => 'ອ້າງອີງ',
	'cite_page' => 'ໜ້າ:',
);

/** لوری (لوری)
 * @author Mogoeilor
 */
$messages['lrc'] = array(
	'cite_page' => 'بلگه',
);

/** Lithuanian (lietuvių)
 * @author Garas
 */
$messages['lt'] = array(
	'cite_article_desc' => 'Prideda [[Special:Cite|citavimo]] specialųjį puslapį ir įrankių juostos nuorodą',
	'cite_article_link' => 'Cituoti šį puslapį',
	'tooltip-cite-article' => 'Informacija kaip cituoti šį puslapį',
	'cite' => 'Cituoti',
	'cite_page' => 'Puslapis:',
	'cite_submit' => 'Cituoti',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografinės \"{{FULLPAGENAME}}\" detalės==

* Puslapio pavadinimas: {{FULLPAGENAME}} 
* Autorius: Projekto \"{{SITENAME}}\" naudotojai
* Leidėjas: ''{{SITENAME}}''. 
* Paskutinės versijos data: {{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}} UTC
* Puslapis gautas: <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}} UTC</citation>
* Nuolatinė nuoroda: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Puslapio versijos Nr.: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citatų stiliai puslapiui \"{{FULLPAGENAME}}\" ==

=== APA stilius ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}''. Gautas <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}}</citation> iš {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA stilius ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}''. {{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}} UTC. <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA stilius ===
{{SITENAME}} naudotojai, '{{FULLPAGENAME}}', ''{{SITENAME}},'' {{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [žiūrėta <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}</citation>]

=== Čikagos stilius ===
{{SITENAME}} naudotojai, \"{{FULLPAGENAME}}\",  ''{{SITENAME}}'', {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (žiūrėta <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}</citation>).

=== CBE/CSE stilius ===
{{SITENAME}} naudotojai. {{FULLPAGENAME}} [internete].  {{SITENAME}},  {{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTTIME}} UTC [cituota <citation>{{CURRENTYEAR}}-{{CURRENTMONTH}}-{{CURRENTDAY2}}</citation>]. Galima rasti: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebook stilius ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (paskutinį kartą žiūrėta <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}</citation>).

=== BibTeX įrašas ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Žiniatinklyje; žiūrėta <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}</citation>]\"
  }

Kai naudojate LaTeX paketą ''url'' (<code>\\usepackage{url}</code> kur nors pradžioje), kuris skirtas duoti daug gražiau suformuotus žiniatinklio adresus, patartina naudoti šitaip:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Žiniatinklyje; žiūrėta <citation>{{CURRENTYEAR}} {{CURRENTMONTHNAME}} {{CURRENTDAY}}</citation>]\"
  }


</div>", # Fuzzy
);

/** Mizo (Mizo ţawng)
 * @author RMizo
 */
$messages['lus'] = array(
	'cite_article_desc' => '[[Special:Cite|Ràwnna]] phêk vohbîk leh hmanrawbawm zawmna belhna',
	'cite_article_link' => 'Ràwnna',
	'tooltip-cite-article' => 'Hë phêk ràwnna chungchanga kaihhruaina',
	'cite' => 'Ràwnna',
	'cite_page' => 'Phêk:',
	'cite_submit' => 'Ràwnna:',
);

/** Latvian (latviešu)
 * @author Xil
 */
$messages['lv'] = array(
	'cite_article_link' => 'Atsauce uz šo lapu',
	'cite' => 'Citēšana',
	'cite_page' => 'Raksts:',
	'cite_submit' => 'Parādīt atsauci',
);

/** Literary Chinese (文言)
 */
$messages['lzh'] = array(
	'cite_article_link' => '引文',
	'cite' => '引文',
);

/** Malagasy (Malagasy)
 * @author Jagwar
 */
$messages['mg'] = array(
	'cite_article_link' => 'Hitanisa ity pejy ity',
);

/** Eastern Mari (олык марий)
 * @author Сай
 */
$messages['mhr'] = array(
	'cite_page' => 'Лаштык:',
);

/** Minangkabau (Baso Minangkabau)
 * @author Iwan Novirion
 */
$messages['min'] = array(
	'cite_article_desc' => 'Manambahan laman istimewa [[Special:Cite|kutipan]] jo pautan pado kotak pakakeh',
	'cite_article_link' => 'Kutip laman ko',
	'tooltip-cite-article' => 'Informasi caro mangutip laman ko',
	'cite' => 'Kutip',
	'cite_page' => 'Laman:',
	'cite_submit' => 'Kutip',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Rincian bibliografi untuak {{FULLPAGENAME}} ==

* Namo laman: {{FULLPAGENAME}} 
* Pangarang: Para kontributor {{SITENAME}}
* Panerbit: ''{{SITENAME}}, {{int:sitesubtitle}}''. 
* Tanggal revisi tarakhia: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Tanggal akses: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Pautan parmanen: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Kontributor utamo: [http://vs.aka-online.de/cgi-bin/wppagehiststat.pl?lang=min.wikipedia&page={{urlencode:{{FULLPAGENAME}}}} Sajarah revisi]
* ID versi laman: {{REVISIONID}}

</div>
<div class=\"plainlinks\" style=\"border: 1px solid grey; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== Format kutipan untuak {{FULLPAGENAME}} ==

=== [[:en:APA style|Format APA]] ===
{{FULLPAGENAME}}. ({{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}). Pado ''{{SITENAME}}, {{int:sitesubtitle}}''. Diakses pukua {{#time:H:i, j F Y}}, dari {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:The MLA Style Manual|Format MLA]] ===
Kontributor {{SITENAME}}. \"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. Situs, {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[:en:MHRA Style Guide|Format MHRA]] ===
Kontributor {{SITENAME}}, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [diakses {{#time:j F Y}}]

=== [[:en:The Chicago Manual of Style|Format Chicago]] ===
Kontributor {{SITENAME}}, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (diakses {{#time:j F Y}}).

=== [[:en:Council of Science Editors|Format CBE/CSE]] ===
Kontributor {{SITENAME}}. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{MediaWiki:Sitesubtitle}};  {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}},   {{CURRENTTIME}} UTC [dikutip pado {{#time:j M Y}}].  Tasadio dari: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:Bluebook|Format Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (tarakhia dikunjuangi pado {{#time:j M Y}}).

=== [[:en:Bluebook#Citation_to_Wikipedia|Bluebook: Harvard JOLT style]] ===
{{SITENAME}}, ''{{FULLPAGENAME}}'', {{canonicalurl:{{FULLPAGENAME}}}} (opsi deskripsi disiko) (pado {{#time:j M Y, H:i}} GMT).

=== [[:en:American Medical Association|AMA]] style ===
Kontributor {{SITENAME}}. {{FULLPAGENAME}}. {{SITENAME}}, {{MediaWiki:Sitesubtitle}}. {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. Tasadio pado: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}. Diakses {{#time:j F Y}}.

=== Entri [[:en:BibTeX|BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Daring; diakses {{#time:j-F-Y}}]\"
  }

Bilo manggunoan paket url [[:en:LaTeX|LaTeX]] (<code>\\usepackage{url}</code> di manopun di bagian pambukak) nan biasonyo manghasilkan alamaik-alamaik web nan diformat labiah rancak, caro ko labiah disarankan:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Daring; diakses {{#time:j-F-Y}}]\"
  }

=== Laman rundiang Wikipedia ===
;Markah: <nowiki>[[</nowiki>{{FULLPAGENAME}}<nowiki>]]</nowiki> (<nowiki>[</nowiki>{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} versi ko<nowiki>]</nowiki>)

;Hasil: [{{canonicalurl:{{FULLPAGENAME}}}} {{FULLPAGENAME}}] ([{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} versi ko])


</div> <!--closing div for \"plainlinks\"-->",
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 * @author Brest
 * @author Misos
 */
$messages['mk'] = array(
	'cite_article_desc' => 'Додава специјална страница за [[Special:Cite|наведување]] и врска кон алатникот',
	'cite_article_link' => 'Наведи ја страницава',
	'tooltip-cite-article' => 'Информации како да ја цитирате оваа страница',
	'cite' => 'Цитат',
	'cite_page' => 'Страница:',
	'cite_submit' => 'Наведи',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Библиографски податоци за {{FULLPAGENAME}} ==

* Назив на страницата: {{FULLPAGENAME}}
* Автор: Учесници на {{SITENAME}}
* Извадач: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Последна измена: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Пристапено на: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Трајна URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Назнака на верзијата: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Стилови на наведување за {{FULLPAGENAME}} ==

=== [[APA style|Стил APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Стил MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Стил MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Чикашки стил]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Стил CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Стил „Сина книга“]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-запис ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[на интернет; пристапено на <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }
Кога користите [[LaTeX]], спакувајте ја URL-адресата (<code>\\usepackage{url}</code> некаде во преамбулата), при што се добиваат многу поубаво горматирани адреси. Се претпочитаат следниве:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Malayalam (മലയാളം)
 * @author Praveenp
 * @author Shijualex
 */
$messages['ml'] = array(
	'cite_article_desc' => '[[Special:Cite|സൈറ്റേഷൻ]] എന്ന പ്രത്യേക താളും, പണി സഞ്ചി  കണ്ണിയും ചേർക്കുന്നു',
	'cite_article_link' => 'ഈ താൾ ഉദ്ധരിക്കുക',
	'tooltip-cite-article' => 'ഈ താളിനെ എങ്ങനെ അവലംബിതമാക്കാം എന്ന വിവരങ്ങൾ',
	'cite' => 'ഉദ്ധരിക്കുക',
	'cite_page' => 'താൾ:',
	'cite_submit' => 'ഉദ്ധരിക്കുക',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} താളിന്റെ ഗ്രന്ഥസൂചി വിവരണം ==

* താളിന്റെ തലക്കെട്ട്: {{FULLPAGENAME}}
* എഴുതിയത്: {{SITENAME}} ലേഖകർ
* പ്രസിദ്ധീകരിച്ചത്: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* അവസാനത്തെ നാൾപ്പതിപ്പിന്റെ തീയതി: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* ശേഖരിച്ച് തീയതി: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* സ്ഥിരം യു.ആർ.എൽ.: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* താളിന്റെ പതിപ്പിന്റെ ഐ.ഡി.: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}} താളിനുള്ള അവലംബ ശൈലികൾ ==
=== [[:w:en:APA style|എ.പി.എ. ശൈലി]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''.  {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} താളിൽ നിന്നും, <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> -നു ശേഖരിച്ചത്.

=== [[:w:en:The MLA style manual|എം.എൽ.എ. ശൈലി]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} യു.റ്റി.സി.. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[:w:en:MHRA Style Guide|എം.എച്ച്.ആർ.എ. ശൈലി]] ===
{{SITENAME}} ലേഖകർ, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} യൂ.റ്റി.സി., &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [എടുത്ത തീയതി:  <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[:w:en:The Chicago Manual of Style|ഷിക്കാഗോ ശൈലി]] ===
{{SITENAME}} ലേഖകർ, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (എടുത്ത തീയതി: <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[:w:en:Council of Science Editors|സി.ബി.ഇ./സി.എസ്.ഇ. ശൈലി]] ===
{{SITENAME}} ലേഖകർ. {{FULLPAGENAME}} [ഇന്റർനെറ്റ്]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} യു.റ്റി.സി. [അവലംബിച്ച തീയതി: <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. ലഭിച്ചത്:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:w:en:Bluebook|ബ്ലൂബുക്ക് ശൈലി]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (അവസാനം പരിശോധിച്ചത് <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[:w:en:BibTeX|ബിബ്ടെക്സ്]] രീതി ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

[[:w:en:LaTeX|ലാറ്റക്സ്]] പാക്കേജ് യൂ.ആർ.എൽ. ഉപയോഗിക്കുകയാണെങ്കിൽ (പീഠികയിൽ <code>\\usepackage{url}</code> എന്ന് നൽകി), കൂടുതൽ മനോഹരമായി വെബ് വിലാസം നൽകാറുണ്ട്, താഴെക്കൊടുക്കുന്ന രീതി ഉപയോഗിക്കാൻ താത്പര്യപ്പെടുന്നു:
  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing div for \"plainlinks\"-->",
);

/** Mongolian (монгол)
 * @author Chinneeb
 */
$messages['mn'] = array(
	'cite_article_link' => 'Энэ хуудаснаас иш татах',
	'cite' => 'Иш татах',
	'cite_page' => 'Хуудас:',
	'cite_submit' => 'Иш татах',
);

/** Marathi (मराठी)
 * @author Kaustubh
 * @author Mahitgar
 * @author V.narsikar
 */
$messages['mr'] = array(
	'cite_article_desc' => 'एक विशेष [[Special:Cite|बाह्यदुवे]] देणारे पान व टूलबॉक्सची लिंक तयार करा',
	'cite_article_link' => 'हे पान उधृत करा',
	'tooltip-cite-article' => 'हे पृष्ठ बघण्यासाठीची माहिती',
	'cite' => 'उधृत करा',
	'cite_page' => 'पान',
	'cite_submit' => 'उधृत करा',
);

/** Hill Mari (кырык мары)
 * @author Amdf
 */
$messages['mrj'] = array(
	'cite_article_link' => 'Ӹлӹшташӹм цитируяш',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 * @author Aurora
 * @author Aviator
 */
$messages['ms'] = array(
	'cite_article_desc' => 'Menambah laman khas dan pautan kotak alatan untuk [[Special:Cite|pemetikan]]',
	'cite_article_link' => 'Petik laman ini',
	'tooltip-cite-article' => 'Maklumat tentang cara memetik laman ini',
	'cite' => 'Petik',
	'cite_page' => 'Laman:',
	'cite_submit' => 'Petik',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Butiran bibliografi {{FULLPAGENAME}} ==

* Nama laman: {{FULLPAGENAME}}
* Pengarang: Para penyumbang {{SITENAME}}
* Penerbit: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Tarikh semakan terkini: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tarikh diambil: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL kekal: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID versi laman: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Gaya petikan {{FULLPAGENAME}} ==

=== [[Gaya APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Manual gaya MLA|Gaya MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Panduan gaya MHRA|Gaya MHRA]] ===
Para penyumbang {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [dicapai pada <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Gaya Chicago]] ===
Para penyumbang {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (dicapai pada <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Gaya CBE/CSE]] ===
Para penyumbang {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [dipetik pada <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Didapati dari:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Gaya Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (kali terakhir dilawati pada <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Lema [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; dicapai pada <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Apabila menggunakan URL pakej [[LaTeX]] (<code>\\usepackage{url}</code> di suatu tempat dalam mukadimah) yang sering memberikan alamat web yang lebih kemas formatnya, ada baiknya menggunakan yang berikut:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; dicapai pada <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Maltese (Malti)
 * @author Chrisportelli
 * @author Giangian15
 */
$messages['mt'] = array(
	'cite_article_desc' => 'Iżżid paġna speċjali għaċ-[[Special:Cite|ċitazzjonijiet]] u ħolqa mal-istrumenti',
	'cite_article_link' => 'Iċċita din il-paġna',
	'tooltip-cite-article' => 'Informazzjoni fuq kif tiċċita din il-paġna',
	'cite' => 'Ċitazzjoni',
	'cite_page' => 'Paġna:',
	'cite_submit' => 'Oħloq ċitazzjoni',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Dettalji biblijografiċi għal {{FULLPAGENAME}} ==

* Titlu tal-paġna: {{FULLPAGENAME}}
* Awtur: kontributuri ta' {{SITENAME}}
* Editur: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data tal-aħħar modifika: {{CURRENTDAY}} ta' {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data tal-konsultazzjoni tal-paġna: <citation>{{CURRENTDAY}} ta' {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanenti: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID tal-verżjoni tal-paġna: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stili ta' ċitazzjoni għal {{FULLPAGENAME}} ==

=== [[APA style|Stil APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Aċċessat fil-<citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> minn {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Stil MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} ta' {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Stil MHRA]] ===
Kontributuri ta' {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} ta' {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [aċċessat fil-<citation>{{CURRENTDAY}} ta' {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Stil Chicago]] ===
Kontributuri ta' {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (aċċessat f'<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Stil CBE/CSE]] ===
Kontributuri ta' {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [iċċitat fl-<citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponibbli fuq:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Stil Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (l-aħħar viżta f'<citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Daħla [[BibTeX]] ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[Online; aċċessat fil-<citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Meta tuża l-pakkett [[LaTeX]] għall-url (<code>\\usepackage{url}</code> f'kwalunkwe parti fil-preambolu) li ġeneralment tagħti indirizzi elettroniċi ifformattjati aħjar, huwa ppreferut li jintuża l-kodiċi segwenti:

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[Online; aċċessat fil<citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Mirandese (Mirandés)
 * @author Malafaya
 */
$messages['mwl'] = array(
	'cite_page' => 'Páigina:',
);

/** Erzya (эрзянь)
 * @author Amdf
 * @author Botuzhaleny-sodamo
 */
$messages['myv'] = array(
	'cite_page' => 'Лопась:',
);

/** Nahuatl (Nāhuatl)
 * @author Fluence
 * @author Ricardo gs
 */
$messages['nah'] = array(
	'cite_article_link' => 'Tlahtoa inīn tlahcuilōltechcopa',
	'cite' => 'Titēnōtzaz',
	'cite_page' => 'Zāzanilli:',
	'cite_submit' => 'Titēnōtzaz',
);

/** Min Nan Chinese (Bân-lâm-gú)
 */
$messages['nan'] = array(
	'cite_article_link' => 'Ín-iōng chit phiⁿ bûn-chiuⁿ',
	'cite' => 'Ín-iōng',
	'cite_page' => 'Ia̍h:',
	'cite_submit' => 'Ín-iōng',
);

/** Norwegian Bokmål (norsk bokmål)
 * @author Nghtwlkr
 */
$messages['nb'] = array(
	'cite_article_desc' => 'Legger til en [[Special:Cite|siteringsside]] og lenke i verktøy-menyen',
	'cite_article_link' => 'Siter denne siden',
	'tooltip-cite-article' => 'Informasjon om hvordan denne siden kan siteres',
	'cite' => 'Siter',
	'cite_page' => 'Side:',
	'cite_submit' => 'Siter',
	'cite_text' => "__NOTOC__
<div style=\"width: 90%; text-align: center; font-size: 85%; margin: 10px auto;\">Innhold:  [[#APA-stil|APA]] | [[#MLA-stil|MLA]] | [[#MHRA-stil|MHRA]] | [[#Chicago-stil|Chicago]] | [[#CBE/CSE-stil|CSE]] | [[#Bluebook-stil|Bluebook]] | [[#BibTeX|BibTeX]]</div>
<div style=\"border: 1px solid grey; background: #E6E8FA; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

==Bibliografiske detaljer for «[[{{PAGENAME}}|{{FULLPAGENAME}}]]»==

* Sidenavn: [[{{PAGENAME}}|{{FULLPAGENAME}}]]
* Forfatter: Wikipedia-brukere
* Utgiver: ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. 
* Dato for forrige revisjon: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Dato sitert: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanent lenke: {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Revisjons-ID: {{REVISIONID}}

<!--Please remember to check for the exact syntax to suit your needs. For more detailed advice, see '''[[Wikipedia:Citing Wikipedia|Citing Wikipedia]]'''.-->

</div>
<div class=\"plainlinks\" style=\"border: 1px solid grey; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\"> 

== Siteringsstiler for «[[{{PAGENAME}}|{{FULLPAGENAME}}]]»==

=== [[:en:APA style|APA-stil]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. Hentet <citation>{{CURRENTTIME}}, {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTYEAR}}</citation> fra {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.



=== [[:en:The MLA style manual|MLA-stil]] ===
«{{FULLPAGENAME}}». ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. {{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}. {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.



=== [[:en:MHRA Style Guide|MHRA-stil]] ===
Wikipedia-brukere, «{{FULLPAGENAME}}»,  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [besøkt <citation>{{CURRENTDAY}}. {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]



=== [[:en:The Chicago Manual of Style|Chicago-stil]] ===
Wikipedia-brukere, «{{FULLPAGENAME}}»,  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (besøkt <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).



=== [[:en:Council of Science Editors|CBE/CSE-stil]] ===
Wikipedia-brukere. {{FULLPAGENAME}} [internett].  {{SITENAME}}, {{MediaWiki:Sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [sitert <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Tilgjengelig fra: 
{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.



=== [[:en:Bluebook|Bluebook-stil]] ===
{{FULLPAGENAME}}, {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (sist besøkt <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).



=== [[:en:BibTeX|BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[På internett; besøkt <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Om man bruker [[:en:LaTeX|LaTeX]]' pakke-URL (<code>\\usepackage{url}</code> et sted i begynnelsen) som pleier å gi mye finere formaterte internettadresser, kan følgende være foretrukket:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[På internett; besøkt <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing \"Citation styles\" div-->", # Fuzzy
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'cite_article_desc' => 'Föögt en [[Special:Cite|Spezialsied för Zitaten]] un en Lenk dorop in’n Kasten Warktüüch to',
	'cite_article_link' => 'Disse Siet ziteren',
	'cite' => 'Ziteerhelp',
	'cite_page' => 'Siet:',
	'cite_submit' => 'Ziteren',
);

/** Low Saxon (Netherlands) (Nedersaksies)
 * @author Servien
 */
$messages['nds-nl'] = array(
	'cite_article_desc' => 'Zet n [[Special:Cite|spesiale zied]] derbie um te siteren, en n verwiezing dernaor in de hulpmiddels',
	'cite_article_link' => 'Disse zied siteren',
	'tooltip-cite-article' => "Informasie over hoe of da'j disse zied siteren kunnen",
	'cite' => 'Siteerhulpe',
	'cite_page' => 'Zied:',
	'cite_submit' => 'Siteren',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografiese gegevens veur {{FULLPAGENAME}} ==

* Ziednaam: {{FULLPAGENAME}}
* Auteur: {{SITENAME}}-biedragers
* Uutgever: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Tiedstip leste versie: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tiedstip eraodpleegd: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanente URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Ziedversienummer: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Sitaotstielen veur {{FULLPAGENAME}} ==

=== [[APA-stiel]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Eraodpleegd op <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> van {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA-stiel]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA-stiel]] ===
{{SITENAME}}-biedragers, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [eraodpleegd <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago-stiel]] ===
{{SITENAME}}-biedragers, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (eraodpleegd <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE-stiel]] ===
{{SITENAME}}-biedragers. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [sitaot van <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Beschikbaor op:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook-stiel]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (eraodpleegd op <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-gegevens ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; geraadpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

t Volgende kan de veurkeur hebben as de [[LaTeX]]-module \"url\" gebruukt wörden (<code>\\usepackage{url}</code> argens in de inleiding), die webadressen mooier opmaakt:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; eraodpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Nepali (नेपाली)
 */
$messages['ne'] = array(
	'cite_article_link' => 'लेख उद्दरण गर्नुहोस्',
	'cite' => 'उद्दरण गर्नु',
	'cite_page' => 'पृष्ठ:',
);

/** Niuean (ko e vagahau Niuē)
 * @author Jose77
 */
$messages['niu'] = array(
	'cite_article_link' => 'Fakakite e tala nei',
);

/** Dutch (Nederlands)
 * @author Effeietsanders
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'cite_article_desc' => 'Voegt een [[Special:Cite|speciale pagina]] toe om te citeren, en een koppeling ernaar in de hulpmiddelen',
	'cite_article_link' => 'Deze pagina citeren',
	'tooltip-cite-article' => 'Informatie over hoe u deze pagina kunt citeren',
	'cite' => 'Citeren',
	'cite_page' => 'Pagina:',
	'cite_submit' => 'Citeren',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografische gegevens voor {{FULLPAGENAME}} ==

* Paginanaam: {{FULLPAGENAME}}
* Auteur: {{SITENAME}}-bijdragers
* Uitgever: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Tijdstip laatste versie: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Tijdstip geraadpleegd: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanente URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Paginaversienummer: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citaatstijlen voor {{FULLPAGENAME}} ==

=== [[APA-stijl]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Geraadpleegd op <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> van {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA-stijl]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA-stijl]] ===
{{SITENAME}}-bijdragers, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [geraadpleegd <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago-stijl]] ===
{{SITENAME}}-bijdragers, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (geraadpleegd <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE-stijl]] ===
{{SITENAME}}-bijdragers. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citaat van <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Beschikbaar op:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook-stijl]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (geraadpleegd op <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-gegevens ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; geraadpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Het volgende kan de voorkeur hebben als de [[LaTeX]]-module \"url\" wordt gebruikt (<code>\\usepackage{url}</code> ergens in de inleiding), die webadressen mooier opgemaakt:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; geraadpleegd <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Norwegian Nynorsk (norsk nynorsk)
 * @author Gunnernett
 * @author Harald Khan
 * @author Jon Harald Søby
 * @author Njardarlogar
 */
$messages['nn'] = array(
	'cite_article_desc' => 'Legg til ei [[Special:Cite|siteringsside]] og ei lenkje i verktøy-menyen',
	'cite_article_link' => 'Siter denne sida',
	'tooltip-cite-article' => 'Informasjon om korleis ein siterer denne sida',
	'cite' => 'Siter',
	'cite_page' => 'Side:',
	'cite_submit' => 'Siter',
);

/** Novial (Novial)
 * @author MF-Warburg
 */
$messages['nov'] = array(
	'cite_article_link' => 'Sita disi artikle',
	'cite' => 'Sita',
);

/** Northern Sotho (Sesotho sa Leboa)
 * @author Mohau
 */
$messages['nso'] = array(
	'cite_page' => 'Letlakala:',
);

/** Occitan (occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'cite_article_desc' => "Apond una pagina especiala [[Special:Cite|citacion]] e un ligam dins la bóstia d'aisinas",
	'cite_article_link' => 'Citar aqueste article',
	'tooltip-cite-article' => 'Informacions sus cossí citar aquesta pagina',
	'cite' => 'Citacion',
	'cite_page' => 'Pagina :',
	'cite_submit' => 'Citar',
	'cite_text' => "__NOTOC__ 
<div class=\"mw-specialcite-bibliographic\">

== Informacions bibliograficas sus {{FULLPAGENAME}} == 
* Nom de la pagina : {{FULLPAGENAME}} 
* Autors : {{canonicalurl:{{FULLPAGENAME}}|action=history}} 
* Editor : {{SITENAME}}, {{int:sitesubtitle}}''.
* Darrièra revision : {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Recuperat : <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanenta : {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identificant d'aquesta version : {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Estils de citacions per {{FULLPAGENAME}} ==

=== [[Estil APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> dempuèi {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Estil MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Estil MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accedit lo <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Estil Chicago]] ===
Contributeurs de {{SITENAME}}, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accedit lo <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Estil CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{int:sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}},  {{CURRENTTIME}} UTC [citat lo <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>].  Disponible sus : 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Estil Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (visitat lo <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Entrada [[BibTeX]] ===

@misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[En linha ; accedit lo <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Se utilizatz lo package URL dins [[LaTeX]] (<code>\\usepackage{url}</code> endacòm dins lo preambul), que balha d'adreças web melhor formatadas, utilizatz lo format seguent :

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[En linha ; accedit lo <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Oriya (ଓଡ଼ିଆ)
 * @author Jnanaranjan Sahu
 * @author Psubhashish
 */
$messages['or'] = array(
	'cite_article_desc' => 'ଏକ [[Special:Cite|ଆଧାର]] ବିଶେଷ ପୃଷ୍ଠା ଓ ଉପକରଣ ପେଡ଼ିର ଲିଙ୍କ ଯୋଡ଼ିଥାଏ',
	'cite_article_link' => 'ଏହି ପୃଷ୍ଠାଟିରେ ପ୍ରମାଣ ଯୋଡ଼ିବେ',
	'tooltip-cite-article' => 'ଏକ ଆଧାର ଦେବା ଉପରେ ଅଧିକ ବିବରଣୀ',
	'cite' => 'ଆଧାର ଦେବେ',
	'cite_page' => 'ପୃଷ୍ଠା:',
	'cite_submit' => 'ଆଧାର ଦେବେ',
	'cite_text' => '__NOTOC__
<div class="mw-specialcite-bibliographic">

== {{FULLPAGENAME}}ର ଅଧାରଗତ ବିବରଣୀ ==


*ପୃଷ୍ଠାନାମ:
*ଲେଖକ:
*ପ୍ରକାଶକ:
*ଶେଷଥର ପୁନରାବୃତିର ତାରିଖ:
*ବ୍ୟବହାର କରାଯାଇଥିବା ତାରିଖ:
*ସ୍ଥାୟୀ URL:
*ପୃଷ୍ଠା ସଂସ୍କରଣ ID:

</div>
<div class="plainlinks mw-specialcite-styles">

== {{FULLPAGENAME}}ର ସଜାଣି ପଦ୍ଧତି ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}

=== [[The MLA style manual|MLA ଶୈଳୀ]] ===
"{{FULLPAGENAME}}." \'\'{{SITENAME}}, {{int:sitesubtitle}}\'\'. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA ଶୈଳୀ]] ===

{{SITENAME}} contributors, \'{{FULLPAGENAME}}\', \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]
=== [[The Chicago Manual of Style|ଚିକାଗୋ ଶୈଳୀ]] ===
{{SITENAME}} contributors, "{{FULLPAGENAME}}," \'\'{{SITENAME}}, {{int:sitesubtitle}},\'\' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE ଶୈଳୀ]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook ଶୈଳୀ]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] ଦାଖଲ ===

  @misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}",
    note = "[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]"
  }

[[LaTeX]] ପ୍ୟାକେଜ url (<code>\\usepackage{url}</code> somewhere in the preamble) ଯାହାକି ଆହୁରି ଅଧିକ ସୁନ୍ଦରଭାବେ ସଜାଯାଇଥିବା ୱେବଠିକଣାକୁ ଯୋଡିଥାଏ ତାକୁ ବ୍ୟବହାର କରିବାବେଳେ, ନିମ୍ନଲିଖିତକୁ ନଜରକୁ ଅଣାଯାଇପାରେ:
@misc{ wiki:xxx,
    author = "{{SITENAME}}",
    title = "{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}",
    year = "{{CURRENTYEAR}}",
    url = "\'\'\'\\url{\'\'\'{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\'\'\'}\'\'\'",
    note = "[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]"
  }


</div> <!--closing div for "plainlinks"-->',
);

/** Ossetic (Ирон)
 * @author Amikeco
 */
$messages['os'] = array(
	'cite_page' => 'Фарс:',
);

/** Pangasinan (Pangasinan)
 */
$messages['pag'] = array(
	'cite_article_link' => 'Bitlaen yan article',
	'cite' => 'Bitlaen',
	'cite_page' => 'Bolong:',
	'cite_submit' => 'Bitlaen',
);

/** Pampanga (Kapampangan)
 */
$messages['pam'] = array(
	'cite_article_link' => 'Banggitan ya ing articulung ini',
	'cite' => 'Banggitan ya',
	'cite_page' => 'Bulung:',
	'cite_submit' => 'Banggitan me',
);

/** Picard (Picard)
 * @author Geoleplubo
 */
$messages['pcd'] = array(
	'cite_article_link' => 'Citer chol pache',
);

/** Deitsch (Deitsch)
 * @author Xqt
 */
$messages['pdc'] = array(
	'cite_page' => 'Blatt:',
);

/** Pälzisch (Pälzisch)
 * @author Manuae
 * @author SPS
 */
$messages['pfl'] = array(
	'cite_article_link' => 'Die Said zidiere',
	'cite' => 'Hilf zum Zidiere',
	'cite_submit' => 'Schbaischere',
);

/** Polish (polski)
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'cite_article_desc' => 'Dodaje stronę specjalną i guzik w toolbarze edycyjnym do obsługi [[Special:Cite|cytowania]]',
	'cite_article_link' => 'Cytowanie tego artykułu',
	'tooltip-cite-article' => 'Informacja o tym jak należy cytować tę stronę',
	'cite' => 'Bibliografia',
	'cite_page' => 'Strona:',
	'cite_submit' => 'stwórz wpis bibliograficzny',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Bèrto 'd Sèra
 * @author Dragonòt
 */
$messages['pms'] = array(
	'cite_article_desc' => "A gionta na pàgina special [[Special:Cite|citassion]] e n'anliura dj'utiss",
	'cite_article_link' => 'Sita sta pàgina-sì',
	'tooltip-cite-article' => 'Anformassion ëd com sité sta pàgina-sì.',
	'cite' => 'Citassion',
	'cite_page' => 'Pàgina da cité:',
	'cite_submit' => 'Pronta la citassion',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Detaj bibliogràfich për {{FULLPAGENAME}} ==

* Nòm ëd la pàgina: {{FULLPAGENAME}}
* Autor: contributor ëd {{SITENAME}}
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data ëd l'ùltima revision: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Date ëd sitassion: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Adrëssa an sl'aragnà përmanenta: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Identificativ dla version ëd la pàgina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stil ëd sitassion për {{FULLPAGENAME}} ==

=== [[stil APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Sità <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> da {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|stil MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|stil MHRA]] ===
{{SITENAME}} contributor, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|stil Chicago]] ===
{{SITENAME}} contributor, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (sità <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|stil CBE/CSE]] ===
{{SITENAME}} contributor. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponìbil da:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|stil Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ùltima vìsita <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Vos [[BibTeX]] ===

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
   note = \"[An linia; trovà <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Quand as deuvra la liura al compless [[LaTeX]] (<code>\\usepackage{url}</code> da chèiche part ant l'achit) che a dovrìa dé dj'adrësse dla Ragnà formatà motobin mej, la manera sì-sota a peul esse preferìa:

  @misc{ wiki:xxx,
   author = \"{{SITENAME}}\",
   title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   year = \"{{CURRENTYEAR}}\",
   url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
   note = \"[An linia; trovà <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Western Punjabi (پنجابی)
 * @author Khalid Mahmood
 */
$messages['pnb'] = array(
	'cite_article_desc' => 'جوڑدا اے اک [[Special:Cite|اتہ پتہ]] خاص صفہ تے اوزار ڈبہ جوڑ۔',
	'cite_article_link' => 'ایس صفے دا اتہ پتہ دیو',
	'tooltip-cite-article' => 'ایس صفے دا کنج اتہ پتہ دیوو دی دس۔',
	'cite' => 'اتہ پتہ',
	'cite_page' => 'صفہ:',
	'cite_submit' => 'اتہ پتہ',
);

/** Pontic (Ποντιακά)
 * @author Sinopeus
 */
$messages['pnt'] = array(
	'cite_page' => 'Σελίδα:',
);

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'cite_article_link' => 'د دې مخ درک',
	'tooltip-cite-article' => 'د دې مخ د درک لګولو مالومات',
	'cite' => 'درک',
	'cite_page' => 'مخ:',
	'cite_submit' => 'درک لگول',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliographic details for {{FULLPAGENAME}} ==

* مخ نوم: {{FULLPAGENAME}}
* ليکوال: {{SITENAME}} ونډه وال
* خپرندوی: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* د وروستۍ مخکتنې نېټه: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Date retrieved: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* تلپاتې تړنه URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* د مخ بڼې پېژند: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Citation styles for {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE style]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may be preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Portuguese (português)
 * @author Hamilton Abreu
 * @author Lijealso
 * @author Malafaya
 * @author 555
 */
$messages['pt'] = array(
	'cite_article_desc' => '[[Special:Cite|Página especial]] que produz uma citação de qualquer outra página na wiki (em vários formatos) e adiciona um link na barra de ferramentas',
	'cite_article_link' => 'Citar esta página',
	'tooltip-cite-article' => 'Informação sobre como citar esta página',
	'cite' => 'Citar',
	'cite_page' => 'Página:',
	'cite_submit' => 'Citar',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Carla404
 * @author Giro720
 */
$messages['pt-br'] = array(
	'cite_article_desc' => 'Adiciona uma página especial de [[Special:Cite|citação]] e link para a caixa de ferramentas',
	'cite_article_link' => 'Citar esta página',
	'tooltip-cite-article' => 'Informação sobre como citar esta página',
	'cite' => 'Citar',
	'cite_page' => 'Página:',
	'cite_submit' => 'Citar',
);

/** Quechua (Runa Simi)
 * @author AlimanRuna
 */
$messages['qu'] = array(
	'cite_article_desc' => "[[Special:Cite|Pukyumanta willanapaq]] sapaq p'anqatam llamk'ana t'asrapi t'inkitapas yapan",
	'cite_article_link' => 'Kay qillqamanta willay',
	'tooltip-cite-article' => "Ima hinam kay p'anqamanta willay",
	'cite' => 'Qillqamanta willay',
	'cite_page' => "P'anqa:",
	'cite_submit' => 'Qillqamanta willay',
);

/** Romansh (rumantsch)
 * @author Kazu89
 */
$messages['rm'] = array(
	'cite_article_link' => 'Citar questa pagina',
	'cite_page' => 'Pagina:',
);

/** Romani (Romani)
 * @author Desiphral
 */
$messages['rmy'] = array(
	'cite_article_link' => 'Prinjardo phandipen ko lekh', # Fuzzy
	'cite' => 'Kana trebul phandipen',
	'cite_submit' => 'Ja',
);

/** Romanian (română)
 * @author Danutz
 * @author Emily
 * @author Firilacroco
 * @author KlaudiuMihaila
 * @author Mihai
 * @author Minisarm
 * @author Stelistcristi
 */
$messages['ro'] = array(
	'cite_article_desc' => 'Adaugă o pagină specială de [[Special:Cite|citare]] și o legătură în trusa de unelte',
	'cite_article_link' => 'Citează acest articol',
	'tooltip-cite-article' => 'Informații cu privire la modul de citare a acestei pagini',
	'cite' => 'Citare',
	'cite_page' => 'Pagină:',
	'cite_submit' => 'Deschide informații',
	'cite_text' => "__NOTOC__ 
<div class=\"mw-specialcite-bibliographic\">
== Detalii bibliografice pentru {{FULLPAGENAME}} == 
* Numele paginii: {{FULLPAGENAME}} 
* Autorul: contribuitorii {{SITENAME}} 
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''. 
* Data ultimei revizuiri: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data preluării: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation> 
* Legătură permanentă: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID-ul versiunii paginii: {{REVISIONID}}

</div> 
<div class=\"plainlinks mw-specialcite-styles\">
== Stiluri de citare pentru {{FULLPAGENAME}} == 

=== Stilul APA === 
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Preluat la <citation>{{CURRENTTIME}} EET, {{CURRENTMONTHNAME}} {{CURRENTDAY}} {{CURRENTYEAR}}</citation> de la {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Stilul MLA === 
„{{FULLPAGENAME}}.” ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;. 

=== Stilul MHRA === 
Contribuitorii {{SITENAME}}, „{{FULLPAGENAME}}”, ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accesat la <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== Stilul Chicago === 
Contribuitorii {{SITENAME}} , „{{FULLPAGENAME}},” ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accesat la <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>). 

=== Stilul CBE/CSE === 
Contribuitorii {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citat în <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponibil la: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}. 

=== Stilul Bluebook === 
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ultima vizită la <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>). 

=== Intrare [[BibTeX]] === 
  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accesat la <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Când se folosește în pachetul [[LaTeX]] expresia url (<code>\\usepackage{url}</code> undeva în preambul) care trebuie să afișeze adrese mai frumos aranjate, următoarea variantă poate fi preferată: 

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accesat la <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing \"Citation styles\" div-->",
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'cite_article_desc' => "Aggiunge 'na pàgena speciele de [[Special:Cite|citaziune]] e collegamende a scatele de le struminde",
	'cite_article_link' => 'Cite sta pàgene',
	'tooltip-cite-article' => "'Mbormaziune sus a cumme se cite sta pàgene",
	'cite' => 'Cite',
	'cite_page' => 'Pàgene:',
	'cite_submit' => 'Cite',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Dettglie bibbliografece pe {{FULLPAGENAME}} ==

* Nome d'a pàgene: {{FULLPAGENAME}}
* Autore: {{SITENAME}} condrebbutore
* Pubblecatore: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Sciurne de l'urtema revisione: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Date recuperate: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL Permanende: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID d'a versione d'a pàgene: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stile de citaziune pe {{FULLPAGENAME}} ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Pigghiate <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> da {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Stile MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Stile MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Stile Chicago]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Stile CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Stile Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Endrate [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Quanne ause 'a URL d'u pacchette [[LaTeX]] (<code>\\usepackage{url}</code> da quaccehparte jndr'à 'u preambole) 'u quale serve pe dà 'nu formate megghie a le indirizze web, le seguende sonde le preferite:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Russian (русский)
 * @author Huuchin
 * @author Kaganer
 * @author Александр Сигачёв
 * @author Ильнар
 */
$messages['ru'] = array(
	'cite_article_desc' => 'Добавляет служебную страницу [[Special:Cite|цитирования]] и ссылку в инструментах',
	'cite_article_link' => 'Цитировать страницу',
	'tooltip-cite-article' => 'Информация о том, как цитировать эту страницу',
	'cite' => 'Цитирование',
	'cite_page' => 'Страница:',
	'cite_submit' => 'Процитировать',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Библиографические данные статьи {{FULLPAGENAME}} ==

* Статья: {{FULLPAGENAME}}
* Автор: {{SITENAME}} авторы
* Опубликовано: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Дата последнего изменения: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Дата загрузки: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Постоянная ссылка: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Идентификатор версии страницы: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Варианты оформления ссылок на статью «{{FULLPAGENAME}}» ==

=== Стиль по [http://protect.gost.ru/document.aspx?control=7&id=173511 ГОСТ 7.0.5—2008] (библиографическая ссылка) ===
{{FULLPAGENAME}} // {{SITENAME}}. [{{REVISIONYEAR}}—{{REVISIONYEAR}}]. Дата обновления: {{#time:d.m.Y|{{REVISIONTIMESTAMP}}}}. URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (дата обращения: {{#time:d.m.Y|{{LOCALTIMESTAMP}}}}).
<div style=\"font-size:smaller; padding-left:2.5em\">
''Примечание:''
* Первое из двух обозначений в квадратных скобках — это год ''создания'' страницы, второе — год ''последнего изменения'' страницы. К сожалению, движок [[MediaWiki]] в настоящее время не позволяет автоматически вставить год ''создания'' в ссылку (сейчас там вместо него также стоит год последнего редактирования). Посмотрите год создания страницы в [{{canonicalurl:{{FULLPAGENAME}}|action=history}} истории правок] и замените эту цифру.
* ''Дата обращения'' в формате ДД.ММ.ГГГГ должна быть сегодняшней. К сожалению, движок MediaWiki из-за кэширования ошибочно показывает не текущую дату, а дату последнего изменения страницы.
</div>

</div>

=== Стиль по [[ГОСТ 7.1|ГОСТ 7.1—2003]] и [[ГОСТ 7.82|ГОСТ 7.82—2001]] (сокращённая библиографическая запись) ===
{{FULLPAGENAME}} [Электронный ресурс] : {{int:Tagline}} : Версия {{REVISIONID}}, сохранённая в  {{CURRENTTIME}} UTC {{CURRENTDAY}} {{CURRENTMONTHNAMEGEN}} {{CURRENTYEAR}} / Авторы Википедии // {{SITENAME}}, {{int:sitesubtitle}}. — Электрон. дан. — Сан-Франциско: Фонд Викимедиа, {{CURRENTYEAR}}. — Режим доступа: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}

=== [[APA style|Стиль APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Стиль MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Стиль MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Чикагский стиль]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Стиль CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{int:sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}},   {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>].  Available from: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited   <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Запись в [[BibTeX]] ===
  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

При использовании [[LaTeX]]-пакета url для более наглядного представления веб-адресов (<code>\\usepackage{url}</code> в преамбуле), вероятно, лучше будет указать:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing div for \"plainlinks\"-->",
);

/** Rusyn (русиньскый)
 * @author Gazeb
 */
$messages['rue'] = array(
	'cite_article_desc' => 'Придасть шпеціалну сторінку [[Special:Cite|Цітації]] і одказ в понуцї інштрументів',
	'cite_article_link' => 'Цітовати сторінку',
	'tooltip-cite-article' => 'Інформації о тім, як цітовати тоту сторінку',
	'cite' => 'Цітованя',
	'cite_page' => 'Сторінка:',
	'cite_submit' => 'Цітовати',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Бібліоґрафічны детайлы к сторінцї {{FULLPAGENAME}} ==

* Назва сторінкы: {{FULLPAGENAME}}
* Автор: Приспівателї {{grammar:2sg|{{SITENAME}}}}
* Выдаватель: ''{{MediaWiki:Sitesubtitle}}''.
* Датум остатнёй управы: {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC
* Датум перевзятя: <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC</citation>
* Тырвалый одказ: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Ідентіфікація ревізії сторінкы: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Способы цітованя сторінкы {{FULLPAGENAME}} ==

=== ISO 690-2 (1)===
Приспівателї {{grammar:2sg|{{SITENAME}}}},'' {{FULLPAGENAME}}'' [online],  {{int:sitesubtitle}}, c{{CURRENTYEAR}}, 
Датум остатнёй ревізії {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC, 
[цітоване <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]
&lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; 

=== ISO 690-2 (2)===
''{{int:sitesubtitle}}: {{FULLPAGENAME}}''  [online]. c{{CURRENTYEAR}} [цітоване <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]. Доступный з WWW: &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; 

=== APA ===
{{FULLPAGENAME}}. ({{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}). ''{{int:sitesubtitle}}''. Здобыто <citation>{{CURRENTTIME}}, {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation> з {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== MLA ===
„{{FULLPAGENAME}}.“ ''{{int:sitesubtitle}}''. {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== MHRA ===
Приспівателї {{grammar:2sg|{{SITENAME}}}}, '{{FULLPAGENAME}}',  ''{{int:sitesubtitle}},'' {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [здобыто <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]

=== Chicago ===
Приспівателї {{grammar:2sg|{{SITENAME}}}}, „{{FULLPAGENAME}},“  ''{{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (здобыто <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>).

=== CBE/CSE ===
Приспівателї {{grammar:2sg|{{SITENAME}}}}. {{FULLPAGENAME}} [Internet].  {{int:sitesubtitle}};  {{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}},   {{CURRENTTIME}} UTC [cited <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>].  Доступне на: 
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== Bluebook ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (остатнїм разом навщівлено <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>).

=== [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; навщівлено <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]\"
  }

Під час хоснованя [[LaTeX]]-ового пакунка url (даґде на початку документа є написано <code>\\usepackage{url}</code>), котрый дакус лїпше форматує вебовы адресы, можете преферовати наступну верзію:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; навщівлено <citation>{{CURRENTDAY}}.&nbsp;{{CURRENTMONTH}}.&nbsp;{{CURRENTYEAR}}</citation>]\"
  }

</div> <!--closing div for \"plainlinks\"-->",
);

/** Aromanian (Armãneashce)
 */
$messages['rup'] = array(
	'cite_article_link' => 'Bagã articlu aistu ca tsitat', # Fuzzy
);

/** Sanskrit (संस्कृतम्)
 * @author Ansumang
 * @author Shubha
 */
$messages['sa'] = array(
	'cite_article_desc' => '[[Special:Cite|बाह्याधारैः]] युक्तं किञ्चन विशेषपृष्ठम् उपकरणपेटिकानुबन्धं च योजयति',
	'cite_article_link' => 'अस्य पृष्ठस्य उल्लेखः क्रियताम्',
	'tooltip-cite-article' => 'अस्य पृष्ठस्य उल्लेखः कथमिति विवरणम्',
	'cite' => 'उदाहरति',
	'cite_page' => 'पृष्ठ:',
	'cite_submit' => 'उदाहरति',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}} इत्यस्य आधारग्नन्थविवरणम् ==

* पृष्ठनाम : {{FULLPAGENAME}}
* लेखकः: {{SITENAME}} योगदातारः
* प्रकाशकः: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* अन्तिमावृत्तेः दिनाङ्कः: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* पुनः प्राप्तस्य दिनाङ्कः: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* शाश्वतं URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* पृष्ठावृत्तेः  ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}}इत्यस्य आधारविन्यासाः ==

=== [[APA style]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA style]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago style]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE style]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook style]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] प्रवेशः ===

  @misc{ wiki:xxx,
    ग्रन्थकर्ता = \"{{SITENAME}}\",
   शीर्षकम् = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
   वर्षम् = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    टिप्पणी = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

[[LaTeX]] अस्य उपयोगावसरे package url (<code>\\usepackage{url}</code> somewhere in the preamble) यच्च समीचीनतया प्रारूपितान् जालसङ्केतान् यच्छति, अधोनिर्दिष्टम् एष्टुं शक्यम्:

  @misc{ wiki:xxx,
    ग्रन्थकर्ता = \"{{SITENAME}}\",
    शीर्षकम् = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    वर्षम् = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    टिप्पणी = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Sakha (саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'cite_article_desc' => 'Аналлаах [[Special:Cite|быһа тардыы]] сирэйин уонна үнүстүрүмүөннэргэ ыйынньык эбэн биэрэр',
	'cite_article_link' => 'Сирэйи цитируйдааһын',
	'tooltip-cite-article' => 'Бу сирэйи хайдах цитируйдуур туһунан',
	'cite' => 'Цитата',
	'cite_page' => 'Сирэй:',
	'cite_submit' => 'Цитаата',
);

/** Sicilian (sicilianu)
 * @author Santu
 */
$messages['scn'] = array(
	'cite_article_desc' => 'Junci na pàggina spiciali pi li [[Special:Cite|cosi di muntuari]] e nu lijami ntê strumenti',
	'cite_article_link' => 'Muntùa sta pàggina',
	'cite' => 'Muntuazzioni',
	'cite_page' => 'Pàggina di muntari',
	'cite_submit' => 'Cria la cosa di muntuari',
);

/** Sindhi (سنڌي)
 */
$messages['sd'] = array(
	'cite' => 'حواليو',
);

/** Samogitian (žemaitėška)
 * @author Hugo.arg
 */
$messages['sgs'] = array(
	'cite' => 'Citoutė',
	'cite_page' => 'Poslapis:',
);

/** Sinhala (සිංහල)
 * @author Budhajeewa
 * @author නන්දිමිතුරු
 */
$messages['si'] = array(
	'cite_article_desc' => '[[Special:Cite|උපහරණ]] විශේෂ පිටුවක් හා මෙවලම්ගොන්න සබැඳියක් එක්කරයි',
	'cite_article_link' => 'මෙම පිටුව උපන්‍යාස කරන්න',
	'tooltip-cite-article' => 'මෙම පිටුව උපුටා දක්වන්නේ කෙසේද යන්න පිළිබඳ තොරතුරු.',
	'cite' => 'උපන්‍යාසය',
	'cite_page' => 'පිටුව:',
	'cite_submit' => 'උපන්‍යාසය',
);

/** Slovak (slovenčina)
 * @author Helix84
 * @author Martin Kozák
 */
$messages['sk'] = array(
	'cite_article_desc' => 'Pridáva špeciálnu stránku [[Special:Cite|Citovať]] a odkaz v nástrojoch',
	'cite_article_link' => 'Citovať túto stránku',
	'tooltip-cite-article' => 'Ako citovať túto stránku',
	'cite' => 'Citovať',
	'cite_page' => 'Stránka:',
	'cite_submit' => 'Citovať',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografické podrobnosti pre článok {{FULLPAGENAME}} ==
* Názov stránky: {{FULLPAGENAME}}
* Autor: prispievatelia {{SITENAME}}
* Vydavateľ: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Dátum poslednej revízie: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Dátum získania: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanentný odkaz: {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID verzie stránky: {{REVISIONID}}
</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Štýly citácie pre článok {{FULLPAGENAME}} ==
=== [[:en:APA style|štýl APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Získané <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> z {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:The MLA style manual|štýl MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA style]] ===
prispievatelia {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[:en:The Chicago Manual of Style|štýl Chicago]] ===
prispievatelia {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (prístup <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[:en:Council of Science Editors|štýl CBE/CSE]] ===
prispievatelia {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Dostupné na: {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[:en:Bluebook|štýl Bluebook]] ===
{{FULLPAGENAME}}, {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (posledná návšteva <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== záznam [[:en:BibTeX|BibTeX]] ===
  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    rok = \"{{CURRENTYEAR}}\", url = \"{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    poznámka = \"[Online; prístup <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Pri použití balíka url v [[LaTeX]]e (<code>\\usepackage{url}</code> niekde v úvode), čo dá oveľa krajšie formátované webové adresy, preferuje sa nasledovné:
  @misc{ wiki:xxx,
    autor = \"{{SITENAME}}\",
    názov = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    rok = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    poznámka = \"[Online; prístup <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\" 
  }
</div> <!--closing div for \"plainlinks\"-->",
);

/** Slovenian (slovenščina)
 * @author Dbc334
 * @author Smihael
 */
$messages['sl'] = array(
	'cite_article_desc' => 'Doda [[Special:Cite|posebno stran za navedbo vira]] in povezavo v orodno vrstico',
	'cite_article_link' => 'Navedba strani',
	'tooltip-cite-article' => 'Informacije o tem, kako navajati to stran',
	'cite' => 'Navedi',
	'cite_page' => 'Stran:',
	'cite_submit' => 'Navedi',
);

/** Southern Sami (Åarjelsaemien)
 * @author M.M.S.
 */
$messages['sma'] = array(
	'cite_page' => 'Bielie:', # Fuzzy
);

/** Shona (chiShona)
 */
$messages['sn'] = array(
	'cite_article_link' => 'Ita cite nyaya iyi', # Fuzzy
);

/** Albanian (shqip)
 * @author Olsi
 */
$messages['sq'] = array(
	'cite_article_desc' => 'Shton një faqe speciale [[Special:Cite|citimi]] dhe një lidhje veglash.',
	'cite_article_link' => 'Cito artikullin',
	'tooltip-cite-article' => 'Informacion mbi mënyrën e citimit të kësaj faqeje',
	'cite' => 'Citate',
	'cite_page' => 'Faqja:',
	'cite_submit' => 'Citoje',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Të dhënat bibliografike për «{{FULLPAGENAME}}» ==
* Emri i faqes: {{FULLPAGENAME}}
* Autori: Redaktorët e {{SITENAME}}-s
* Publikuesi: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data e versionit të fundit: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* E marrë më: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Lidhja e përhershme: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Nr i versionit të faqes: {{REVISIONID}}
</div>

<div class=\"plainlinks mw-specialcite-styles\">

== Stile të ndryshme citimi për «{{FULLPAGENAME}}» ==

=== [[Stili citimit APA|APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Stili citimit MLA|MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Stili citimit MHRA|MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Stili i citimit Chicago|Chicago]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Stili i citimit CBE/CSE|CBE/CSE]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Stili i citimit Bluebook|Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Stili i citimit BibTeX|BibTeX]] ===
@misc{ wiki:xxx,
	author = \"{{SITENAME}}\",
	title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
	year = \"{{CURRENTYEAR}}\",
	url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
	note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
} 

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may preferred:

@misc{ wiki:xxx,
	author = \"{{SITENAME}}\",
	title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
	year = \"{{CURRENTYEAR}}\",
	url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
	note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
}
</div><!--closing div for \"plainlinks\"-->", # Fuzzy
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎)
 * @author Millosh
 * @author Rancher
 * @author Sasa Stefanovic
 * @author Жељко Тодоровић
 * @author Михајло Анђелковић
 */
$messages['sr-ec'] = array(
	'cite_article_desc' => 'Додаје посебну страницу за [[Special:Cite|цитирање]] и везу с алаткама',
	'cite_article_link' => 'Библиографски подаци',
	'tooltip-cite-article' => 'Информације о томе како цитирати ову страну',
	'cite' => 'цитат',
	'cite_page' => 'Страница:',
	'cite_submit' => 'цитат',
);

/** Serbian (Latin script) (srpski (latinica)‎)
 * @author Liangent
 * @author Michaello
 * @author Жељко Тодоровић
 */
$messages['sr-el'] = array(
	'cite_article_desc' => 'Dodaje specijalnu stranu za [[Special:Cite|citiranje]] i vezu ka oruđima.',
	'cite_article_link' => 'citiranje ove strane',
	'tooltip-cite-article' => 'Informacije o tome kako citirati ovu stranu',
	'cite' => 'citat',
	'cite_page' => 'Stranica:',
	'cite_submit' => 'citat',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'cite_article_desc' => 'Föiget ju [[Special:Cite|Zitierhilfe]]-Spezioalsiede un n Link in dän Kasten Reewen bietou',
	'cite_article_link' => 'Disse Siede zitierje',
	'cite' => 'Zitierhälpe',
	'cite_page' => 'Siede:',
	'cite_submit' => 'anwiese',
);

/** Sundanese (Basa Sunda)
 * @author Kandar
 */
$messages['su'] = array(
	'cite_article_desc' => 'Nambahkeun kaca husus [[Special:Cite|cutatan]] & tumbu toolbox',
	'cite_article_link' => 'Cutat kaca ieu',
	'tooltip-cite-article' => 'Émbaran ngeunaan cara ngarujuk ieu kaca',
	'cite' => 'Cutat',
	'cite_page' => 'Kaca:',
	'cite_submit' => 'Cutat',
);

/** Swedish (svenska)
 * @author Lejonel
 * @author Per
 * @author Sannab
 * @author WikiPhoenix
 */
$messages['sv'] = array(
	'cite_article_desc' => 'Lägger till en specialsida för [[Special:Cite|källhänvisning]] och en länk i verktygslådan',
	'cite_article_link' => 'Citera denna artikel',
	'tooltip-cite-article' => 'Information om hur denna sida kan citeras',
	'cite' => 'Citera',
	'cite_page' => 'Sida:',
	'cite_submit' => 'Citera',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Bibliografiska detaljer för {{FULLPAGENAME}} ==

* Sidans namn: {{FULLPAGENAME}}
* Författare: {{SITENAME}} contributors
* Utgivare: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Datum för senaste version: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Datum mottaget: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Permanent adress: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Sidans version-ID: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Referensstilar för {{FULLPAGENAME}} ==

=== [[APA style|APA-stil]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Hämtat <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> från {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|MLA-stil]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|MHRA-stil]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Chicago-stil]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|CBE/CSE-stil]] ===
{{SITENAME}} contributors. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Available from:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Bluebook-stil]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]]-uppslag ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; hämtades <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

När man ska använda [[LaTeX]]-paketadressen (<code>\\usepackage{url}</code> någonstans i ingressen) som brukar ge mycket finare formaterade webbadresser, föredras följande:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; hämtades <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--avslutande div för \"plainlinks\"-->",
);

/** Swahili (Kiswahili)
 * @author Lloffiwr
 * @author Stephenwanjau
 */
$messages['sw'] = array(
	'cite_article_link' => 'Taja ukurasa huu',
	'tooltip-cite-article' => 'Taarifa juu ya njia ya kutaja ukurasa huu',
	'cite' => 'Taja',
	'cite_page' => 'Ukurasa:',
	'cite_submit' => 'Taja',
);

/** Säggssch (Säggssch)
 * @author Thogo
 */
$messages['sxu'] = array(
	'cite_article_link' => 'Zidier dän ardiggl hier', # Fuzzy
	'cite' => 'Zidierhilfe',
	'cite_submit' => 'Zidierhilfe',
);

/** Silesian (ślůnski)
 * @author Herr Kriss
 * @author Timpul
 */
$messages['szl'] = array(
	'cite_article_link' => 'Cytuj ta zajta',
	'cite_page' => 'Zajta:',
);

/** Tamil (தமிழ்)
 * @author Shanmugamp7
 * @author TRYPPN
 * @author Trengarasu
 */
$messages['ta'] = array(
	'cite_article_desc' => 'கருவிப் பெட்டியில் [[Special:Cite|மேற்கோள்]] காடுவதற்கான இணைப்பை ஏற்படுத்துகிறது',
	'cite_article_link' => 'இப்பக்கத்தை மேற்கோள் காட்டு',
	'tooltip-cite-article' => 'இப்பக்கத்தை எப்படி மேற்கோளாகக் காட்டுவது என்பது பற்றிய விவரம்',
	'cite' => 'மேற்கோள் காட்டு',
	'cite_page' => 'பக்கம்:',
	'cite_submit' => 'மேற்கோள் காட்டு',
);

/** Telugu (తెలుగు)
 * @author Mpradeep
 * @author Veeven
 */
$messages['te'] = array(
	'cite_article_desc' => '[[Special:Cite|ఉదహరింపు]] అనే ప్రత్యేక పేజీని & పరికర పెట్టె లింకునీ చేరుస్తుంది',
	'cite_article_link' => 'ఈ వ్యాసాన్ని ఉదహరించండి',
	'tooltip-cite-article' => 'ఈ పేజీని ఎలా ఉదహరించాలి అన్నదానిపై సమాచారం',
	'cite' => 'ఉదహరించు',
	'cite_page' => 'పేజీ:',
	'cite_submit' => 'ఉదహరించు',
);

/** Tetum (tetun)
 * @author MF-Warburg
 */
$messages['tet'] = array(
	'cite_article_desc' => 'Kria pájina espesíal ba [[Special:Cite|sitasaun]] ho ligasaun iha kaixa besi nian',
	'cite_article_link' => "Sita pájina ne'e",
	'tooltip-cite-article' => "Informasaun kona-ba sita pájina ne'e",
	'cite' => 'Sita',
	'cite_page' => 'Pájina:',
	'cite_submit' => 'Sita',
);

/** Tajik (Cyrillic script) (тоҷикӣ)
 * @author Ibrahim
 */
$messages['tg-cyrl'] = array(
	'cite_article_desc' => 'Саҳифаи вижае барои [[Special:Cite|ёдкард]] изофа мекунад ва пайванде ба ҷаъбаи абзор меафзояд',
	'cite_article_link' => 'Ёд кардани пайванди ин мақола',
	'cite' => 'Ёд кардани ин мақола',
	'cite_page' => 'Саҳифа:',
	'cite_submit' => 'Ёд кардан',
);

/** Tajik (Latin script) (tojikī)
 * @author Liangent
 */
$messages['tg-latn'] = array(
	'cite_article_desc' => "Sahifai viƶae baroi [[Special:Cite|jodkard]] izofa mekunad va pajvande ba ça'bai abzor meafzojad",
	'cite_article_link' => 'Jod kardani pajvandi in maqola',
	'cite' => 'Jod kardani in maqola',
	'cite_page' => 'Sahifa:',
	'cite_submit' => 'Jod kardan',
);

/** Thai (ไทย)
 * @author Octahedron80
 * @author Passawuth
 */
$messages['th'] = array(
	'cite_article_desc' => 'เพิ่มหน้า[[Special:Cite|อ้างอิง]]พิเศษและลิงก์บนกล่องเครื่องมือ',
	'cite_article_link' => 'อ้างอิงหน้านี้',
	'tooltip-cite-article' => 'ข้อมูลเกี่ยวกับวิธีการอ้างอิงหน้านี้',
	'cite' => 'อ้างอิง',
	'cite_page' => 'หน้า:',
	'cite_submit' => 'อ้างอิง',
);

/** Turkmen (Türkmençe)
 * @author Hanberke
 */
$messages['tk'] = array(
	'cite_article_desc' => '[[Special:Cite|Sitirle]] ýörite sahypasyny we gural sandygy çykgydyny goşýar',
	'cite_article_link' => 'Sahypany sitirle',
	'tooltip-cite-article' => 'Bu sahypany nähili sitirlemelidigi hakda maglumat',
	'cite' => 'Sitirle',
	'cite_page' => 'Sahypa:',
	'cite_submit' => 'Sitirle',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'cite_article_desc' => 'Nagdaragdag ng isang natatanging pahinang [[Special:Cite|pampagtutukoy]] at kawing sa kahon (lalagyan) ng kagamitan',
	'cite_article_link' => 'Tukuyin ang pahinang ito',
	'tooltip-cite-article' => 'Kabatiran kung paano tutukuyin ang pahinang ito',
	'cite' => 'Tukuyin',
	'cite_page' => 'Pahina:',
	'cite_submit' => 'Tukuyin',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Mga detalyeng pangtalaaklatan para sa {{FULLPAGENAME}} ==

* Pangalan ng pahina: {{FULLPAGENAME}}
* May-akda: {{SITENAME}} contributors
* Tagapaglathala: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Petsa ng huling pagbabago: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Petsa ng pagbawi: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Pamalagiang URL: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID ng Bersiyon ng Pahina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Mga estilo ng pagbanggit para sa {{FULLPAGENAME}} ==

=== [[Estilo ng APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Nabawi noong <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Estilo ng MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Estilo ng MHRA]] ===
{{SITENAME}} contributors, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [napuntahan noong <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Estilo ng Chicago]] ===
{{SITENAME}} contributors, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (napuntahan noong <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Estilo ng CBE/CSE]] ===
Mga tagapag-ambag sa {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [pagbanggit <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Makukuha mula sa: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Estilo ng Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (huling dinalaw noong <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Lahok sa [[BibTeX]] ===

  @misc{ wiki:xxx,
    may-akda = \"{{SITENAME}}\",
    pamagat = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    taon = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    tala = \"[Nasa linya; napuntahan noong <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Kapag ginagamit ang pakete ng url ng [[LaTeX]] (<code>\\usepackage{url}</code> saan man sa loob ng punong-sabi) na may gawi na makapagbigay ng lalo pang may mahusay na kaanyuan na mga tirahang pangsangkalambatan, ang mga sumusunod ay maaaring mas nanaisin:

  @misc{ wiki:xxx,
    may-akda = \"{{SITENAME}}\",
    pamagat = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    taon = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    tala = \"[Nasa linya; napuntahan noong <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** толышә зывон (толышә зывон)
 * @author Гусейн
 */
$messages['tly'] = array(
	'cite_page' => 'Сәһифә:',
);

/** Tswana (Setswana)
 */
$messages['tn'] = array(
	'cite_article_link' => 'Nopola mokwalo o', # Fuzzy
);

/** Tongan (lea faka-Tonga)
 */
$messages['to'] = array(
	'cite_article_link' => 'Lau ki he kupú ni', # Fuzzy
	'cite' => 'Lau ki he',
);

/** Turkish (Türkçe)
 * @author Erkan Yilmaz
 * @author Joseph
 * @author Srhat
 * @author Uğur Başak
 */
$messages['tr'] = array(
	'cite_article_desc' => '[[Special:Cite|Alıntı]] özel sayfa ve araç kutusu linkini ekler',
	'cite_article_link' => 'Sayfayı kaynak göster',
	'tooltip-cite-article' => 'Bu sayfanın nasıl alıntı yapılacağı hakkında bilgi',
	'cite' => 'Kaynak göster',
	'cite_page' => 'Sayfa:',
	'cite_submit' => 'Belirt',
	'cite_text' => "__NOTOC__
<div style=\"width: 90%; text-align: center; font-size: 85%; margin: 10px auto;\">İçindekiler:  [[#APA stil|APA]] | [[#MLA stil|MLA]] | [[#MHRA stil|MHRA]] | [[#Chicago stil|Chicago]] | [[#CBE/CSE stil|CSE]] | [[#Bluebook stil|Bluebook]] | [[#BibTeX stil|BibTeX]]</div>

'''NOTE:''' Most teachers and professionals do not consider encyclopedias citable reference material for most purposes.  Wikipedia articles should be used for background information, and as a starting point for further research, but not as a final source for important facts.

As with any [[Vikipedi:Vikipedi kim yazar|community-built]] reference, there is a possibility for error in Wikipedia's content — please check your facts against multiple sources and read our [[Vikipedi:Genel_Bilgi_Paktı|disclaimers]] for more information.

<div style=\"border: 1px solid grey; background: #E6E8FA; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== \"{{FULLPAGENAME}}\" sayfasının [[bibliyografya|bibliyografik]] detayları ==

* Sayfanın adı: {{FULLPAGENAME}}
* Yazar(lar): Vikipedi'de katkıda bulunanlar, bak [{{fullurl:{{FULLPAGENAME}}|action=history}} sayfanın geçmişi]
* Editör: ''{{SITENAME}}, {{MedyaViki:Sitesubtitle}}''. 
* Son düzenleme tarih: {{CURRENTDAY}}. {{CURRENTMONTHNAME}}
* Son isteme tarih: {{CURRENTYEAR}}, {{CURRENTTIME}} ([[UTC]])
* Geçerli URL: {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Sayfanın versiyon no.: {{REVISIONID}}

</div>
<div class=\"plainlinks\" style=\"border: 1px solid grey; width: 90%; padding: 15px 30px 15px 30px; margin: 10px auto;\">

== \"{{FULLPAGENAME}}\" sayfanın kaynak olarak gösterim imkanları ==

=== [[APA]] stil ===
Wikipedia contributors ({{CURRENTYEAR}}). {{FULLPAGENAME}}.  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. Retrieved <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> from {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[MLA]] stil ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA]] stil ===
Wikipedia contributors, '{{FULLPAGENAME}}',  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accessed <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Chicago]] stil ===
Wikipedia contributors, \"{{FULLPAGENAME}},\"  ''{{SITENAME}}, {{MediaWiki:Sitesubtitle}},'' {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accessed <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[CBE/CSE]] stil ===
Wikipedia contributors. {{FULLPAGENAME}} [Internet].  {{SITENAME}}, {{MediaWiki:Sitesubtitle}};  {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}},   {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>].  Available from: 
{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook]] stil ===
{{FULLPAGENAME}}, {{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (last visited <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] stil ===

  @misc{ wiki:xxx,
    yazar(lar) = \"{{SITENAME}}\",
    başlık = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    yıl = \"{{CURRENTYEAR}}\",
    url = \"{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    not = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

When using the [[LaTeX]] package url (<code>\\usepackage{url}</code> somewhere in the preamble) which tends to give much more nicely formatted web addresses, the following may preferred:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{MediaWiki:Sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{fullurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accessed <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing \"Citation styles\" div-->

<noinclude>
[[de:MediaWiki:Cite_text]]
[[en:MediaWiki:Cite text]]
</noinclude>", # Fuzzy
);

/** Turoyo (Ṫuroyo)
 * @author Ariyo
 */
$messages['tru'] = array(
	'cite_page' => 'Faṭo:',
);

/** Tsonga (Xitsonga)
 * @author Thuvack
 */
$messages['ts'] = array(
	'cite_page' => 'Tluka:',
);

/** Tatar (Cyrillic script) (татарча)
 * @author Ильнар
 */
$messages['tt-cyrl'] = array(
	'cite_article_desc' => 'Махсус [[Special:Cite|күчермәләү]] битен һәм җиһазларга сылтамалар өсти',
	'cite_article_link' => 'Бу битне күчермәләү',
	'tooltip-cite-article' => 'Бу битне ничек күчермәләү турындагы мәгълүмат',
	'cite' => 'Күчермәләү',
	'cite_page' => 'Бит:',
	'cite_submit' => 'Күчермәләү',
);

/** Central Atlas Tamazight (ⵜⴰⵎⴰⵣⵉⵖⵜ)
 * @author Tifinaghes
 */
$messages['tzm'] = array(
	'cite_page' => 'ⵜⴰⵙⵏⴰ:',
);

/** Udmurt (удмурт)
 * @author ОйЛ
 */
$messages['udm'] = array(
	'cite_article_link' => 'Кызьы со статьяез цитировать кароно',
);

/** Uyghur (Arabic script) (ئۇيغۇرچە)
 * @author Sahran
 */
$messages['ug-arab'] = array(
	'cite_page' => 'بەت:',
);

/** Uyghur (Latin script) (Uyghurche)
 * @author Jose77
 */
$messages['ug-latn'] = array(
	'cite_article_link' => 'Bu maqalini ishliting',
	'cite_page' => 'Bet:',
);

/** Ukrainian (українська)
 * @author Ahonc
 * @author Prima klasy4na
 * @author Ата
 */
$messages['uk'] = array(
	'cite_article_desc' => 'Додає спеціальну сторінку [[Special:Cite|цитування]] і посилання в інструментах',
	'cite_article_link' => 'Цитувати сторінку',
	'tooltip-cite-article' => 'Інформація про те, як цитувати цю сторінку',
	'cite' => 'Цитування',
	'cite_page' => 'Сторінка:',
	'cite_submit' => 'Процитувати',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Бібліографічні дані статті {{FULLPAGENAME}} ==

* Назва: {{FULLPAGENAME}}
* Автор: {{SITENAME}} contributors
* Опубліковано: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Дата останньої зміни: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Дата цитування: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* Постійне посилання: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID версії сторінки: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Варіанти оформлення посилань на {{FULLPAGENAME}} ==

=== [[Стиль APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Цитовано <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> з {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Стиль MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Стиль MHRA]] ===
Дописувачі {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [цитовано <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Стиль Chicago]] ===
Дописувачі {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (цитовано <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Стиль CBE/CSE]] ===
Дописувачі {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [cited <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Доступно з:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Стиль Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (останній перегляд <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== Запис [[BibTeX]] ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Онлайн; цитовано <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

При використанні [[LaTeX]]-пакета url (<code>\\usepackage{url}</code> у преамбулі), який тяжіє до кращого форматування веб-адрес, мабуть, краще буде вказати таке:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Онлайн; цитовано <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Urdu (اردو)
 */
$messages['ur'] = array(
	'cite_article_link' => 'مضمون کا حوالہ دیں',
	'cite' => 'حوالہ',
	'cite_page' => 'صفحہ:',
);

/** Uzbek (oʻzbekcha)
 * @author CoderSI
 */
$messages['uz'] = array(
	'cite_article_link' => 'Sahifadan matn parchasi ajratish',
);

/** vèneto (vèneto)
 * @author Candalua
 * @author GatoSelvadego
 */
$messages['vec'] = array(
	'cite_article_desc' => 'Zonta na pagina speciale par le [[Special:Cite|citazion]] e un colegamento nei strumenti',
	'cite_article_link' => 'Cita sta pagina',
	'tooltip-cite-article' => 'Informassion su come citar sta pagina',
	'cite' => 'Citazion',
	'cite_page' => 'Pagina da citar:',
	'cite_submit' => 'Crea la citazion',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Detaji bibliografisi par {{FULLPAGENAME}} ==

* Titoło pàjina: {{FULLPAGENAME}}
* Autor: contributori {{SITENAME}}
* Editor: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Data de l'ultema modifega: {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC
* Data estrasion: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}} {{CURRENTTIME}} UTC</citation>
* URL permanente: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* ID version pàjina: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Stiłi citasion par {{FULLPAGENAME}} ==

=== [[APA style|Stiłe APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Estratto il <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> da {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[The MLA style manual|Stiłe MLA]] ===
\"{{FULLPAGENAME}}.\" ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[MHRA Style Guide|Stiłe MHRA]] ===
Contributori {{SITENAME}}, '{{FULLPAGENAME}}', ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [accesso il <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[The Chicago Manual of Style|Stiłe Chicago]] ===
Contributori {{SITENAME}}, \"{{FULLPAGENAME}},\" ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (accesso il <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Council of Science Editors|Stiłe CBE/CSE]] ===
Contributori {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [citato il <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Disponibile su:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Stiłe Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ultima visita il <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Online; accesso il <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Cuando che se dopara el pacheto [[LaTeX]] par url (<code>\\usepackage{url}</code> da calche parte inte'l preanboło) che in xenere el da indirisi web formatai in modo mejor, xe preferibiłe doparar el seguente còdexe:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Online; accesso il <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Veps (vepsän kel’)
 * @author Triple-ADHD-AS
 * @author Игорь Бродский
 */
$messages['vep'] = array(
	'cite_article_desc' => 'Ližadab [[Special:Cite|citiruindan]] specialižen lehtpolen da kosketusen azegištos',
	'cite_article_link' => "Citiruida nece lehtpol'",
	'tooltip-cite-article' => "Informacii siš, kut pidab citiruida nece lehtpol'.",
	'cite' => 'Citiruind',
	'cite_page' => 'Lehtpol’:',
	'cite_submit' => 'Citiruida',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'cite_article_desc' => 'Thêm trang đặc biệt để [[Special:Cite|trích dẫn bài viết]] và đặt liên kết trong thanh công cụ',
	'cite_article_link' => 'Trích dẫn trang này',
	'tooltip-cite-article' => 'Hướng dẫn cách trích dẫn trang này',
	'cite' => 'Trích dẫn',
	'cite_page' => 'Trang:',
	'cite_submit' => 'Trích dẫn',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== Chi tiết ghi chú của {{FULLPAGENAME}} ==

* Tên trang: {{FULLPAGENAME}}
* Tác giả: {{SITENAME}} contributors
* Nhà xuất bản: ''{{SITENAME}}, {{int:sitesubtitle}}''.
* Ngày sửa cuối: {{CURRENTDAY}} {{CURRENTMONTHNAME}} năm {{CURRENTYEAR}} lúc {{CURRENTTIME}} UTC
* Ngày truy cập: <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} năm {{CURRENTYEAR}} lúc {{CURRENTTIME}} UTC</citation>
* URL thường trực: {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* Mã số phiên bản trang: {{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== Các văn phong ghi chú phổ biến cho {{FULLPAGENAME}} ==

=== [[Văn phong APA]] ===
{{FULLPAGENAME}}. ({{CURRENTYEAR}}, {{CURRENTDAY}} {{CURRENTMONTHNAME}}). ''{{SITENAME}}, {{int:sitesubtitle}}''. Lấy vào <citation>{{CURRENTTIME}}, {{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation> từ {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Cẩm nang Văn phong MLA|Văn phong MLA]] ===
“{{FULLPAGENAME}}.” ''{{SITENAME}}, {{int:sitesubtitle}}''. {{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC. <citation>{{CURRENTDAY}} {{CURRENTMONTHABBREV}} {{CURRENTYEAR}}, {{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;.

=== [[Hướng dẫn Văn phong MHRA|Văn phong MHRA]] ===
Những người đóng góp vào {{SITENAME}}, ‘{{FULLPAGENAME}}’, ''{{SITENAME}}, {{int:sitesubtitle}},'' {{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}, {{CURRENTTIME}} UTC, &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt; [truy cập ngày <citation>{{CURRENTDAY}} {{CURRENTMONTHNAME}} {{CURRENTYEAR}}</citation>]

=== [[Cẩm nang Văn phong Chicago|Văn phong Chicago]] ===
Những người đóng góp vào {{SITENAME}}, “{{FULLPAGENAME}},” ''{{SITENAME}}, {{int:sitesubtitle}},'' {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (truy cập ngày <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[Hội đồng Chủ bút Khoa học|Văn phong CBE/CSE]] ===
Những người đóng góp vào {{SITENAME}}. {{FULLPAGENAME}} [Internet]. {{SITENAME}}, {{int:sitesubtitle}}; {{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}, {{CURRENTTIME}} UTC [ghi chú ngày <citation>{{CURRENTYEAR}} {{CURRENTMONTHABBREV}} {{CURRENTDAY}}</citation>]. Có sẵn tại:
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}.

=== [[Bluebook|Văn phong Bluebook]] ===
{{FULLPAGENAME}}, {{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}} (ghé thăm lần cuối ngày <citation>{{CURRENTMONTHNAME}} {{CURRENTDAY}}, {{CURRENTYEAR}}</citation>).

=== [[BibTeX]] entry ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[Trực tuyến; truy cập ngày <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }

Khi sử dụng gói <code>url</code> của [[LaTeX]] (có <code>\\usepackage{url}</code> ở đâu đó phía đầu văn bản), gói này hay trang trí các địa chỉ Web một cách đẹp đẽ hơn, bạn có thể muốn sử dụng đoạn mã sau:

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[Trực tuyến; truy cập ngày <citation>{{CURRENTDAY}}-{{CURRENTMONTHNAME}}-{{CURRENTYEAR}}</citation>]\"
  }


</div> <!-- div kết thúc “plainlinks” -->",
);

/** Volapük (Volapük)
 * @author Malafaya
 * @author Smeira
 */
$messages['vo'] = array(
	'cite_article_desc' => 'Läükon padi patik [[Special:Cite|saitama]] sa yüm ad stumem',
	'cite_article_link' => 'Saitön padi at',
	'cite' => 'Saitön',
	'cite_page' => 'Pad:',
	'cite_submit' => 'Saitön',
);

/** Walloon (walon)
 * @author Srtxg
 */
$messages['wa'] = array(
	'cite_page' => 'Pådje:',
);

/** Wu (吴语)
 */
$messages['wuu'] = array(
	'cite_article_link' => '引用该篇文章',
	'cite' => '引用',
	'cite_page' => '页面:',
	'cite_submit' => '引用',
);

/** Kalmyk (хальмг)
 * @author Huuchin
 */
$messages['xal'] = array(
	'cite_article_link' => 'Тер халхиг эшллх',
);

/** Yiddish (ייִדיש)
 * @author פוילישער
 */
$messages['yi'] = array(
	'cite_article_desc' => 'לייגט צו א [[Special:Cite|ציטיר]] באַזונדערן בלאַט און געצייגקאַסן לינק',
	'cite_article_link' => 'ציטירן דעם דאזיגן בלאט',
	'tooltip-cite-article' => 'אינפֿאָרמאַציע ווי אַזוי צו ציטירן דעם בלאַט',
	'cite' => 'ציטירן',
	'cite_page' => 'בלאט:',
	'cite_submit' => 'ציטירן',
);

/** Yoruba (Yorùbá)
 * @author Demmy
 */
$messages['yo'] = array(
	'cite_page' => 'Ojúewé:',
);

/** Cantonese (粵語)
 */
$messages['yue'] = array(
	'cite_article_desc' => '加一個[[Special:Cite|引用]]特別頁同埋一個工具箱連結',
	'cite_article_link' => '引用呢篇文',
	'cite' => '引用文章',
	'cite_page' => '版：',
	'cite_submit' => '引用',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Hzy980512
 * @author Xiaomingyan
 */
$messages['zh-hans'] = array(
	'cite_article_desc' => '添加[[Special:Cite|引用]]特殊页面和工具箱链接',
	'cite_article_link' => '引用本页',
	'tooltip-cite-article' => '关于如何引用本页的信息',
	'cite' => '引用页面',
	'cite_page' => '页面：',
	'cite_submit' => '引用',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}}的文献详细信息 ==

* 页面名称：{{FULLPAGENAME}}
* 作者：{{SITENAME}}编者
* 出版者：{{SITENAME}}，{{int:sitesubtitle}}．
* 最新版本日期：{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（协调世界时）
* 查阅日期：<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（协调世界时）</citation>
* 永久链接：{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* 页面版本号：{{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}}的参考文献格式 ==

=== GB7714格式 ===
{{SITENAME}}编者．{{FULLPAGENAME}}[G/OL]．{{SITENAME}}，{{int:sitesubtitle}}，{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}［<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}</citation>］．{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}．

=== APA格式 ===
{{FULLPAGENAME}}．（{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日）．''{{SITENAME}}，{{int:sitesubtitle}}''．于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}</citation>查阅自{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}．

=== MLA格式 ===
“{{FULLPAGENAME}}．”''{{SITENAME}}，{{int:sitesubtitle}}''．{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（协调世界时）．<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;．

=== MHRA格式 ===
{{SITENAME}}编者，‘{{FULLPAGENAME}}’，''{{SITENAME}}，{{int:sitesubtitle}}''，{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（协调世界时），&lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;［于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>查阅］

=== 芝加哥格式 ===
{{SITENAME}}编者，“{{FULLPAGENAME}}，”''{{SITENAME}}，{{int:sitesubtitle}}''，{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}（于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>查阅）．

=== CBE/CSE格式 ===
{{SITENAME}}编者．{{FULLPAGENAME}}［互联网］．{{SITENAME}}，{{int:sitesubtitle}}；{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（协调世界时）［引用于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>］．可访问自：
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}．

=== Bluebook格式 ===
{{FULLPAGENAME}}，{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}（最新访问于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>）．

=== BibTeX记录 ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[在线资源；访问于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>]\"
  }

使用LaTeX包装的链接（开头某处的<code>\\usepackage{url}</code>）将提供更好的网址格式，推荐选用下列格式：
  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[在线资源；访问于<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Frankou
 * @author Waihorace
 */
$messages['zh-hant'] = array(
	'cite_article_desc' => '增加[[Special:Cite|引用]]特殊頁面以及工具箱連結',
	'cite_article_link' => '引用此文',
	'tooltip-cite-article' => '關於如何引用此頁的資訊',
	'cite' => '引用文章',
	'cite_page' => '頁面：',
	'cite_submit' => '引用',
	'cite_text' => "__NOTOC__
<div class=\"mw-specialcite-bibliographic\">

== {{FULLPAGENAME}}的文獻詳細資訊 ==

* 頁面名稱：{{FULLPAGENAME}}
* 作者：{{SITENAME}}編者
* 出版者：{{SITENAME}}，{{int:sitesubtitle}}．
* 最新版本日期：{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（協調世界時）
* 查閲日期：<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（協調世界時）</citation>
* 永久連結：{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}
* 頁面版本號：{{REVISIONID}}

</div>
<div class=\"plainlinks mw-specialcite-styles\">

== {{FULLPAGENAME}}的參考文獻格式 ==

=== APA格式 ===
{{FULLPAGENAME}}．（{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日）．''{{SITENAME}}，{{int:sitesubtitle}}''．於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}</citation>查閲自{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}．

=== MLA格式 ===
「{{FULLPAGENAME}}」．''{{SITENAME}}，{{int:sitesubtitle}}''．{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（協調世界時）．<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}</citation> &lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;．

=== MHRA格式 ===
{{SITENAME}}編者，『{{FULLPAGENAME}}』，''{{SITENAME}}，{{int:sitesubtitle}}''，{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（協調世界時），&lt;{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}&gt;［於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>查閲］

=== 芝加哥格式 ===
{{SITENAME}}編者，「{{FULLPAGENAME}}」，''{{SITENAME}}，{{int:sitesubtitle}}''，{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}（於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>查閲）．

=== CBE/CSE格式 ===
{{SITENAME}}編者．{{FULLPAGENAME}}［網際網絡］．{{SITENAME}}，{{int:sitesubtitle}}；{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日{{CURRENTTIME}}（協調世界時）［引用於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>］．可訪問自：
{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}．

=== Bluebook格式 ===
{{FULLPAGENAME}}，{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}（最新訪問於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>）．

=== BibTeX記錄 ===

  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}\",
    note = \"[線上資源；訪問於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>]\"
  }

使用LaTeX包裝的連結（開頭某處的<code>\\usepackage{url}</code>）將提供更好的網址格式，推薦選用下列格式：
  @misc{ wiki:xxx,
    author = \"{{SITENAME}}\",
    title = \"{{FULLPAGENAME}} --- {{SITENAME}}{,} {{int:sitesubtitle}}\",
    year = \"{{CURRENTYEAR}}\",
    url = \"'''\\url{'''{{canonicalurl:{{FULLPAGENAME}}|oldid={{REVISIONID}}}}'''}'''\",
    note = \"[線上資源；訪問於<citation>{{CURRENTYEAR}}年{{CURRENTMONTH}}月{{CURRENTDAY}}日</citation>]\"
  }


</div> <!--closing div for \"plainlinks\"-->",
);

<?php
/**
 * Internationalisation file for extension PdfHandler.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

$messages['en'] = array(
	'pdf-desc'           => 'Handler for viewing PDF files in image mode.',
	'pdf_no_metadata'    => 'Cannot get metadata from PDF.',
	'pdf_page_error'     => 'Page number not in range.',
	'exif-pdf-producer'  => 'Conversion program',
	'exif-pdf-version'   => 'Version of PDF format',
	'exif-pdf-encrypted' => 'Encrypted',
	'exif-pdf-pagesize'  => 'Page size',
);

/** Message documentation (Message documentation)
 * @author Purodha
 * @author Shirayuki
 * @author The Evil IP address
 */
$messages['qqq'] = array(
	'pdf-desc' => '{{desc|name=Pdf Handler|url=http://www.mediawiki.org/wiki/Extension:PdfHandler}}',
	'pdf_no_metadata' => 'Error message given when metadata cannot be retrieved from a PDF file',
	'pdf_page_error' => 'Error message given when a PDF does not have the requested page number',
	'exif-pdf-producer' => 'The label used in the metadata table at the bottom of the file description page for the program used to convert this PDF file into a PDF.

This is separate from the program used to create the original file (Which is labeled by {{msg-mw|Exif-software}}).',
	'exif-pdf-version' => 'Label for the version of the pdf file format in the metadata table at the bottom of an image description page. Usually a number between 1.2 and 1.6',
	'exif-pdf-encrypted' => 'Label for field in metadata table at bottom of an image description page to denote if the PDF file is encrypted. The value of the field this references is either "no" (most common) or something like "yes (print:yes copy:no change:no addNotes:no)"',
	'exif-pdf-pagesize' => 'Label for the field in the metadata table at the bottom of an image description page to denote the size of the pages in the pdf. If there is more than one size of page used in this document, each size is listed once.',
);

/** Afrikaans (Afrikaans)
 * @author Naudefj
 * @author පසිඳු කාවින්ද
 */
$messages['af'] = array(
	'pdf-desc' => 'Handler vir die lees van PDF-lêers in beeld af',
	'pdf_no_metadata' => 'Kan nie metadata uit PDF kry nie',
	'pdf_page_error' => 'Bladsynommer kom nie in dokument voor nie',
);

/** Gheg Albanian (Gegë)
 * @author Mdupont
 */
$messages['aln'] = array(
	'pdf-desc' => 'Mbajtës për shikimin PDF files në imazh mode',
	'pdf_no_metadata' => 'Nuk mund të merrni nga metadata PDF',
	'pdf_page_error' => 'numrin e faqes nuk është në varg',
);

/** Aragonese (aragonés)
 * @author Juanpabl
 */
$messages['an'] = array(
	'pdf-desc' => 'Maneyador ta veyer fichers PDF en modo imachen',
	'pdf_no_metadata' => "No s'obtenioron metadatos d'o PDF",
	'pdf_page_error' => 'Numero de pachina difuera de rango',
);

/** Arabic (العربية)
 * @author Meno25
 * @author Mido
 * @author أحمد
 */
$messages['ar'] = array(
	'pdf-desc' => 'معالج عرض ملفات PDF في طور الصور',
	'pdf_no_metadata' => 'تعذّر استخراج البيانات الفوقية من ملف PDF',
	'pdf_page_error' => 'رقم الصفحة خارج عن النطاق',
	'exif-pdf-producer' => 'برمجية التحويل',
	'exif-pdf-version' => 'إصدارة صيغة PDF',
	'exif-pdf-encrypted' => 'مُعمّى',
	'exif-pdf-pagesize' => 'حجم الصفحة',
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Meno25
 */
$messages['arz'] = array(
	'pdf-desc' => 'متحكم لرؤية ملفات PDF فى نمط صورة',
	'pdf_no_metadata' => 'لم يمكن أخذ معلومات ميتا من PDF',
	'pdf_page_error' => 'رقم الصفحة ليس فى النطاق',
);

/** Assamese (অসমীয়া)
 * @author Bishnu Saikia
 */
$messages['as'] = array(
	'pdf-desc' => 'পিডিএফ ফাইল ছবি হিচাপে ব্যৱহাৰৰ পদ্ধতি',
	'pdf_no_metadata' => 'পি.ডি.এফ.ৰ পৰা মেটাডাটা উপলদ্ধ নহয়',
	'pdf_page_error' => 'পৃষ্ঠাৰ নম্বৰ সীমাৰ ভিতৰত নাই',
	'exif-pdf-producer' => 'ৰূপান্তৰক প্ৰগ্ৰাম',
	'exif-pdf-version' => 'পি.ডি.এফ. ৰূপত সংস্কৰণ',
	'exif-pdf-pagesize' => 'পৃষ্ঠাৰ আকাৰ',
);

/** Asturian (asturianu)
 * @author Xuacu
 */
$messages['ast'] = array(
	'pdf-desc' => "Xestor pa ver los ficheros PDF en mou d'imaxe",
	'pdf_no_metadata' => 'Nun se pudieron sacar los metadatos del PDF',
	'pdf_page_error' => 'El númberu de la páxina nun ta nel rangu',
	'exif-pdf-producer' => 'Programa de conversión',
	'exif-pdf-version' => 'Versión del formatu PDF',
	'exif-pdf-encrypted' => 'Cifráu',
	'exif-pdf-pagesize' => 'Tamañu de la páxina',
);

/** South Azerbaijani (تورکجه)
 * @author Amir a57
 */
$messages['azb'] = array(
	'exif-pdf-pagesize' => 'صحیفه اولچوسو',
);

/** Bashkir (башҡортса)
 * @author Assele
 */
$messages['ba'] = array(
	'pdf-desc' => 'PDF файлдарҙы рәсемдәр рәүешендә ҡарау өсөн эшкәртеүсе ҡорал',
	'pdf_no_metadata' => 'PDF-тан мета-мәғлүмәтте алыу мөмкин түгел',
	'pdf_page_error' => 'Бит һаны биттәр һанынан ашҡан',
);

/** Bikol Central (Bikol Central)
 * @author Geopoet
 */
$messages['bcl'] = array(
	'pdf-desc' => 'An tagapagkapot para sa pagtatanaw kan PDF na mga sagunson na yaon sa moda nin imahe.',
	'pdf_no_metadata' => 'Dae makakakua nin datos na meta gikan sa PDF.',
	'pdf_page_error' => 'An numero kan pahina dae tabi abot.',
	'exif-pdf-producer' => 'Programa nin kombersyon',
	'exif-pdf-version' => 'Bersyon kan PDF pormat',
	'exif-pdf-encrypted' => 'Enkriptado',
	'exif-pdf-pagesize' => 'Sukol kan pahina',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Jim-by
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'pdf-desc' => 'Апрацоўшчык для прагляду PDF-файлаў у выглядзе выяваў',
	'pdf_no_metadata' => 'Немагчыма атрымаць мэта-зьвесткі з PDF-файла',
	'pdf_page_error' => 'Нумар старонкі паза дыяпазонам',
	'exif-pdf-producer' => 'Праграма канвэртацыі',
	'exif-pdf-version' => 'Вэрсія фармату PDF',
	'exif-pdf-encrypted' => 'Зашыфравана',
	'exif-pdf-pagesize' => 'Памер старонкі',
);

/** Bulgarian (български)
 * @author DCLXVI
 * @author Stanqo
 * @author Turin
 */
$messages['bg'] = array(
	'pdf_no_metadata' => 'невъзможно е да бъдат извлечени метаданни от PDF',
	'pdf_page_error' => 'Номерът на страница е извън обхвата',
	'exif-pdf-encrypted' => 'Криптиране',
	'exif-pdf-pagesize' => 'Размер на страницата',
);

/** Bengali (বাংলা)
 * @author Nasir8891
 * @author Wikitanvir
 */
$messages['bn'] = array(
	'pdf-desc' => 'পিডিএফ ফাইল ছবি হিসাবে ব্যবহারের পদ্ধতি',
	'pdf_no_metadata' => 'পিডিএফ থেকে মেটাডেটা পাওয়া যায়নি',
	'pdf_page_error' => 'পাতার নম্বর সীমার মধ্যে নেই',
);

/** Breton (brezhoneg)
 * @author Fohanno
 * @author Fulup
 */
$messages['br'] = array(
	'pdf-desc' => 'Maveg evit gwelet ar restroù PDF e mod skeudenn',
	'pdf_no_metadata' => 'Dibosupl tapout meta-roadennoù digant ar restr PDF',
	'pdf_page_error' => "N'emañ ket niverenn ar bajenn er skeuliad",
	'exif-pdf-producer' => 'Program amdreiñ',
	'exif-pdf-pagesize' => 'Ment ar bajenn',
);

/** Bosnian (bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'pdf-desc' => 'Uređivač za pregled PDF datoteka u modu za slike',
	'pdf_no_metadata' => 'Ne mogu se naći metapodaci u PDFu',
	'pdf_page_error' => 'Broj stranice nije u rasponu',
);

/** Catalan (català)
 * @author Aleator
 */
$messages['ca'] = array(
	'pdf-desc' => 'Gestor per a visualitzar arxius PDF en mode imatge',
	'pdf_no_metadata' => "No s'han pogut obtenir metadades del PDF",
	'pdf_page_error' => "Número de pàgina fora d'abast",
);

/** Chechen (нохчийн)
 * @author Sasan700
 * @author Умар
 */
$messages['ce'] = array(
	'pdf-desc' => 'Хьажа аттон кечйо PDF-файлаш суьрта куьцехь',
	'pdf_no_metadata' => 'схьацаэцало чура бух оцу PDF',
	'pdf_page_error' => 'Агlон терахь дозан чулацамца дац',
	'exif-pdf-pagesize' => 'АгӀона барам',
);

/** Sorani Kurdish (کوردی)
 * @author Calak
 */
$messages['ckb'] = array(
	'exif-pdf-pagesize' => 'قەبارەی پەڕە',
);

/** Czech (česky)
 * @author Matěj Grabovský
 * @author Mormegil
 */
$messages['cs'] = array(
	'pdf-desc' => 'Ovladač pro prohlížení PDF souborů jako obrázků',
	'pdf_no_metadata' => 'Z PDF se nepodařilo získat metadata',
	'pdf_page_error' => 'Číslo stránky mimo rozsah',
	'exif-pdf-producer' => 'Konverzní program',
	'exif-pdf-version' => 'Verze formátu PDF',
	'exif-pdf-encrypted' => 'Šifrovaný',
	'exif-pdf-pagesize' => 'Velikost stránky',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'pdf-desc' => 'Teclyn i weld ffeiliau PDF ar lun delwedd',
	'pdf_no_metadata' => "Yn methu cael y metadata o'r PDF",
	'pdf_page_error' => "Nid yw'r rhif hwn oddi mewn i ystod rhifau'r tudalennau",
	'exif-pdf-producer' => 'Rhaglen trosi',
	'exif-pdf-version' => 'Fersiwn y fformat PDF',
	'exif-pdf-encrypted' => 'Amgriptiwyd',
	'exif-pdf-pagesize' => 'Maint y dudalen',
);

/** Danish (dansk)
 * @author Peter Alberti
 */
$messages['da'] = array(
	'pdf-desc' => 'Håndtering af PDF-visning i billedtilstand',
	'pdf_no_metadata' => 'Kan ikke hente metadata fra PDF',
	'pdf_page_error' => 'Sidetallet er større end antallet af sider i dokumentet',
	'exif-pdf-producer' => 'Konverteringsprogram',
	'exif-pdf-version' => 'Version af PDF-format',
	'exif-pdf-encrypted' => 'Krypteret',
	'exif-pdf-pagesize' => 'Sidestørrelse',
);

/** German (Deutsch)
 * @author Kghbln
 * @author Metalhead64
 * @author Raimond Spekking
 */
$messages['de'] = array(
	'pdf-desc' => 'Stellt eine Schnittstelle zur Ansicht von PDF-Dateien im Bildermodus bereit',
	'pdf_no_metadata' => 'Keine Metadaten im PDF vorhanden.',
	'pdf_page_error' => 'Seitenzahl außerhalb des Dokumentes.',
	'exif-pdf-producer' => 'Umwandlungsprogramm',
	'exif-pdf-version' => 'Version des PDF-Formats',
	'exif-pdf-encrypted' => 'Verschlüsselt',
	'exif-pdf-pagesize' => 'Seitengröße',
);

/** Swiss High German (Schweizer Hochdeutsch)
 * @author Geitost
 */
$messages['de-ch'] = array(
	'pdf_page_error' => 'Seitenzahl ausserhalb des Dokumentes.',
);

/** Zazaki (Zazaki)
 * @author Aspar
 * @author Erdemaslancan
 */
$messages['diq'] = array(
	'pdf-desc' => 'şuxulnayoxo ke dosyayê PDFyan modê mocnayiş de mocneno',
	'pdf_no_metadata' => 'PDF ra metadata nêgeriyeno',
	'pdf_page_error' => 'numreyê peli benate de niyo',
	'exif-pdf-producer' => 'Programa çerxiney',
	'exif-pdf-version' => 'Versiyona babet da PDF',
	'exif-pdf-encrypted' => 'Kodıno',
	'exif-pdf-pagesize' => 'Ebata perer',
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'pdf-desc' => 'Źěłowy rěd za woglědowanje PDF-datajow we wobrazowem modusu',
	'pdf_no_metadata' => 'Metadaty njedaju se z PDF dobyś',
	'pdf_page_error' => 'Bokowe cysło zwenka wobcerka',
	'exif-pdf-producer' => 'Konwertěrowański program',
	'exif-pdf-version' => 'Wersija PDF-formata',
	'exif-pdf-encrypted' => 'Skoděrowany',
	'exif-pdf-pagesize' => 'Wjelikosć boka',
);

/** Greek (Ελληνικά)
 * @author Omnipaedista
 */
$messages['el'] = array(
	'pdf-desc' => 'Διαχειριστής για την εμφάνιση αρχείων PDF σε μορφή εικόνας',
	'pdf_no_metadata' => 'Αδύνατη η απόκτηση μεταδεδομένων από PDF',
	'pdf_page_error' => 'Αριθμός σελίδας εκτός ορίου',
);

/** British English (British English)
 * @author Shirayuki
 */
$messages['en-gb'] = array(
	'exif-pdf-producer' => 'Conversion programme',
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'pdf-desc' => 'Ilo por vidi PDF-dosierojn en bilda reĝimo',
	'pdf_no_metadata' => 'Ne povas preni metadatenon el PDF',
	'pdf_page_error' => 'Paĝnombro ekster valida intervalo',
	'exif-pdf-version' => 'Versio de PDF-formato',
	'exif-pdf-encrypted' => 'Ĉifrita',
	'exif-pdf-pagesize' => 'Grandeco de paĝo',
);

/** Spanish (español)
 * @author Armando-Martin
 * @author Sanbec
 */
$messages['es'] = array(
	'pdf-desc' => 'Manejador para ver archivos PDF en modo imagen',
	'pdf_no_metadata' => 'No se obtuvieron metadatos del PDF',
	'pdf_page_error' => 'Número de página fuera de rango',
	'exif-pdf-producer' => 'Programa de conversión',
	'exif-pdf-version' => 'Versión del formato PDF',
	'exif-pdf-encrypted' => 'Cifrado',
	'exif-pdf-pagesize' => 'Tamaño de página',
);

/** Estonian (eesti)
 * @author Avjoska
 * @author Pikne
 */
$messages['et'] = array(
	'pdf-desc' => 'Töötleja PDF-failide piltidena kuvamiseks',
	'pdf_no_metadata' => 'Ei õnnestu PDF-faili meta-andmeid saada',
	'pdf_page_error' => 'Leheküljenumber pole vahemikus.',
	'exif-pdf-producer' => 'Teisendusprogramm',
	'exif-pdf-version' => 'PDF-vormingu versioon',
	'exif-pdf-encrypted' => 'Krüptitud',
	'exif-pdf-pagesize' => 'Lehe suurus',
);

/** Persian (فارسی)
 * @author Ebraminio
 * @author Huji
 * @author Reza1615
 * @author Sahim
 * @author Wayiran
 */
$messages['fa'] = array(
	'pdf-desc' => 'گرداننده‌ای برای مشاهدهٔ پرونده‌های پی‌دی‌اف در حالت تصویر',
	'pdf_no_metadata' => 'نمی‌توان فراداده‌ها را از پی‌دی‌اف گرفت',
	'pdf_page_error' => 'شماره صفحه در محدوده نیست',
	'exif-pdf-producer' => 'برنامهٔ مبدل',
	'exif-pdf-version' => 'نسخهٔ قالب پی‌دی‌اف',
	'exif-pdf-encrypted' => 'رمز شده',
	'exif-pdf-pagesize' => 'حجم صفحه',
);

/** Finnish (suomi)
 * @author Crt
 * @author Kulmalukko
 * @author Nike
 * @author VezonThunder
 * @author Vililikku
 */
$messages['fi'] = array(
	'pdf-desc' => 'Käsittelijä PDF-tiedostojen katsomiseen kuvatilassa.',
	'pdf_no_metadata' => 'Metatietojen hakeminen PDF-tiedostosta epäonnistui',
	'pdf_page_error' => 'Sivunumero ei ole alueella.',
	'exif-pdf-producer' => 'Muunto-ohjelma',
	'exif-pdf-version' => 'PDF-muodon versio',
	'exif-pdf-encrypted' => 'Salattu',
	'exif-pdf-pagesize' => 'Sivun koko',
);

/** French (français)
 * @author Crochet.david
 * @author Gomoko
 * @author Grondin
 * @author Verdy p
 */
$messages['fr'] = array(
	'pdf-desc' => 'Gestionnaire permettant de visualiser les fichiers PDF en mode image',
	'pdf_no_metadata' => 'Impossible d’obtenir les métadonnées du fichier PDF',
	'pdf_page_error' => 'Le numéro de page est hors de l’étendue.',
	'exif-pdf-producer' => 'Programme de conversion',
	'exif-pdf-version' => 'Version du format PDF',
	'exif-pdf-encrypted' => 'Crypté',
	'exif-pdf-pagesize' => 'Taille de la page',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'pdf-desc' => 'Utilitèro por vêre los fichiérs PDF en fôrma émâge.',
	'pdf_no_metadata' => 'Pôt pas avêr les mètabalyês du fichiér PDF.',
	'pdf_page_error' => 'Lo numerô de pâge est en defôr de la portâ.',
);

/** Galician (galego)
 * @author Alma
 * @author Toliño
 */
$messages['gl'] = array(
	'pdf-desc' => 'Manipulador para ver ficheiros PDF no modo de imaxe',
	'pdf_no_metadata' => 'Non se puideron obter os metadatos do PDF.',
	'pdf_page_error' => 'O número da páxina non está no rango.',
	'exif-pdf-producer' => 'Programa de conversión',
	'exif-pdf-version' => 'Versión en formato PDF',
	'exif-pdf-encrypted' => 'Cifrado',
	'exif-pdf-pagesize' => 'Tamaño da páxina',
);

/** Ancient Greek (Ἀρχαία ἑλληνικὴ)
 * @author Omnipaedista
 */
$messages['grc'] = array(
	'pdf_no_metadata' => 'Ἀδύνατον τὸ ἀποκομίζειν μεταδεδομένα ἐκ PDF',
	'pdf_page_error' => 'Ἀριθμὸς δέλτου ἐκτὸς ἐμβελείας',
);

/** Swiss German (Alemannisch)
 * @author Als-Holder
 */
$messages['gsw'] = array(
	'pdf-desc' => 'Schnittstell fir d Aasicht vu PDF-Dateien im Bilder-Modus',
	'pdf_no_metadata' => 'Kei Metadate im PDF vorhande.',
	'pdf_page_error' => 'Sytezahl usserhalb vum Dokumänt.',
	'exif-pdf-producer' => 'Umwandligsprogramm',
	'exif-pdf-version' => 'Version vum PDF-Format',
	'exif-pdf-encrypted' => 'Verschlisslet',
	'exif-pdf-pagesize' => 'Sytegreßi',
);

/** Gujarati (ગુજરાતી)
 * @author KartikMistry
 * @author Sushant savla
 */
$messages['gu'] = array(
	'pdf-desc' => 'PDF ફાઈલોને ચિત્ર સ્વરૂપે જોવાનું સાધન',
	'pdf_no_metadata' => 'PDFમાંથી મેટા ડાટા ન મેળવી શકાયો',
	'pdf_page_error' => 'પાનાં ક્રમાંક અવધિમાં નથી',
);

/** Hebrew (עברית)
 * @author Amire80
 * @author Rotemliss
 * @author YaronSh
 */
$messages['he'] = array(
	'pdf-desc' => 'טיפול בצפייה בקובצי PDF במצב תמונה',
	'pdf_no_metadata' => 'לא ניתן לאחזר את נתוני המסמך מה־PDF',
	'pdf_page_error' => 'מספר הדף אינו בטווח',
	'exif-pdf-producer' => 'תוכנת המרה',
	'exif-pdf-version' => 'הגרסה של תסדיר PDF',
	'exif-pdf-encrypted' => 'מוצפן',
	'exif-pdf-pagesize' => 'גודל דף',
);

/** Hindi (हिन्दी)
 * @author Kaustubh
 */
$messages['hi'] = array(
	'pdf-desc' => 'चित्र मोड में पीडीएफ फ़ाईल देखनेके लिये आवश्यक प्रणाली',
	'pdf_no_metadata' => 'पीडीएफ से मेटाडाटा ले नहीं पायें',
	'pdf_page_error' => 'पन्ने का क्रमांक सीमामें नहीं हैं',
);

/** Croatian (hrvatski)
 * @author Ex13
 */
$messages['hr'] = array(
	'pdf-desc' => 'Program za gledanje PDF datoteka u slikovnom modu',
	'pdf_no_metadata' => 'Nije moguće dobiti metapodatke iz PDF',
	'pdf_page_error' => 'Broj stranice nije u opsegu',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'pdf-desc' => 'Program za wobhladowanje datajow PDF we wobrazowym modusu',
	'pdf_no_metadata' => 'W PDF žane metadaty njejsu.',
	'pdf_page_error' => 'Ličba strony zwonka dokumenta.',
	'exif-pdf-producer' => 'Konwertowanski program',
	'exif-pdf-version' => 'Wersija PDF-formata',
	'exif-pdf-encrypted' => 'Zaklučowany',
	'exif-pdf-pagesize' => 'Wulkosć strony',
);

/** Hungarian (magyar)
 * @author Dani
 * @author Dj
 */
$messages['hu'] = array(
	'pdf-desc' => 'PDF fájlok megjelenítse képként',
	'pdf_no_metadata' => 'nem sikerült lekérni a PDF metaadatait',
	'pdf_page_error' => 'Az oldalszám a tartományon kívül esik',
	'exif-pdf-producer' => 'Konvertáló program',
	'exif-pdf-version' => 'PDF formátum verziója',
	'exif-pdf-encrypted' => 'Titkosított',
	'exif-pdf-pagesize' => 'Lapméret',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'pdf-desc' => 'Gestor pro visualisar files PDF in modo de imagine',
	'pdf_no_metadata' => 'Non pote obtener metadatos ab PDF',
	'pdf_page_error' => 'Numero de pagina foras del intervallo',
);

/** Indonesian (Bahasa Indonesia)
 * @author Bennylin
 */
$messages['id'] = array(
	'pdf-desc' => 'Yang menangani tampilan berkas PDF dalam mode gambar',
	'pdf_no_metadata' => 'Tidak dapat membaca metadata dari PDF',
	'pdf_page_error' => 'Nomor halaman di luar batas',
);

/** Iloko (Ilokano)
 * @author Lam-ang
 */
$messages['ilo'] = array(
	'pdf-desc' => 'Panagtengngel para iti panagkita kadagiti PDF a papeles iti moda a ladawan',
	'pdf_no_metadata' => 'Saan a makaala ti metadata manipud idiay PDF.',
	'pdf_page_error' => 'Saan a masakupan ti numero ti panid.',
	'exif-pdf-producer' => 'Konbersion a programa',
	'exif-pdf-version' => 'Bersion iti PDF a pormat',
	'exif-pdf-encrypted' => 'Naenkripto',
	'exif-pdf-pagesize' => 'Kadakkel ti panid',
);

/** Italian (italiano)
 * @author Beta16
 * @author Darth Kule
 */
$messages['it'] = array(
	'pdf-desc' => 'Gestore per la visualizzazione di file PDF in modalità immagine',
	'pdf_no_metadata' => 'Impossibile ottenere i metadati da PDF',
	'pdf_page_error' => "Numero di pagina non compreso nell'intervallo",
	'exif-pdf-producer' => 'Programma di conversione',
	'exif-pdf-version' => 'Versione del formato PDF',
	'exif-pdf-encrypted' => 'Crittografato',
	'exif-pdf-pagesize' => 'Dimensioni pagina',
);

/** Japanese (日本語)
 * @author Fryed-peach
 * @author Shirayuki
 */
$messages['ja'] = array(
	'pdf-desc' => '画像モードで PDF ファイルを表示するためのハンドラー',
	'pdf_no_metadata' => 'PDF ファイルからメタデータを取得できません',
	'pdf_page_error' => 'ページ番号が正しい範囲内にありません。',
	'exif-pdf-producer' => '変換プログラム',
	'exif-pdf-version' => 'PDF 形式のバージョン',
	'exif-pdf-encrypted' => '暗号化済み',
	'exif-pdf-pagesize' => 'ページのサイズ',
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 * @author NoiX180
 */
$messages['jv'] = array(
	'pdf-desc' => 'Sing nadhangi kanggo ndelok berkas PDF mawa modé gambar',
	'pdf_no_metadata' => 'Ora bisa olèh metadata saka PDF',
	'pdf_page_error' => 'Nomèr kaca nèng njaba wates',
);

/** Georgian (ქართული)
 * @author BRUTE
 * @author David1010
 */
$messages['ka'] = array(
	'pdf-desc' => 'დამამუშავებელი PDF-ფაილების სურათების სახით დასათვალიერებლად',
	'pdf_no_metadata' => 'შეუძლებელია PDF-დან მეტამონაცემების მიღება',
	'pdf_page_error' => 'გვერდის ნომერი არ არის დიაპაზონში',
	'exif-pdf-producer' => 'პროგრამის გარდაქმნა',
	'exif-pdf-version' => 'ვერსია PDF ფორმატში',
	'exif-pdf-encrypted' => 'დაშიფრული',
	'exif-pdf-pagesize' => 'გვერდის ზომა',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 * @author Lovekhmer
 * @author Thearith
 * @author គីមស៊្រុន
 */
$messages['km'] = array(
	'pdf-desc' => 'កម្មវិធីសំរាប់បើកមើលឯកសារ PDF ជាទំរង់រូបភាព',
	'pdf_no_metadata' => 'មិនអាចទទួលយកទិន្នន័យមេតាពី PDF បានទេ',
	'pdf_page_error' => 'គ្មានលេខទំព័រ ក្នុងដែនកំណត់',
);

/** Korean (한국어)
 * @author Kwj2772
 * @author 아라
 */
$messages['ko'] = array(
	'pdf-desc' => 'PDF 파일을 이미지 방식으로 볼 수 있게 하는 핸들러',
	'pdf_no_metadata' => 'PDF 파일에서 메타데이터를 추출할 수 없습니다.',
	'pdf_page_error' => '쪽수가 범위 안에 있지 않습니다.',
	'exif-pdf-producer' => '변환 프로그램',
	'exif-pdf-version' => 'PDF 형식 버전',
	'exif-pdf-encrypted' => '암호화함',
	'exif-pdf-pagesize' => '문서 크기',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'pdf-desc' => 'Määd et möjjelesch, PDF-Dateie wie Bellder ze beloore.',
	'pdf_no_metadata' => 'Kann de Metta-Date nit fun dä PDF-Datei holle.',
	'pdf_page_error' => 'En Sigge-Nommer es ußerhallef',
	'exif-pdf-producer' => 'Ömwandelongsprojramm',
	'exif-pdf-version' => 'PDF-Fommaat-Version',
	'exif-pdf-encrypted' => 'Verschlößelt',
	'exif-pdf-pagesize' => 'Dä Sigg(e) ier Jrüüße', # Fuzzy
);

/** Kyrgyz (Кыргызча)
 * @author Chorobek
 */
$messages['ky'] = array(
	'pdf-desc' => 'PDF файлдарды сүрөт түрүндө көрсөткүч',
	'pdf_no_metadata' => 'PDF-тин метамаалыматтарын алуу мүмкүн эмес',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'pdf-desc' => '"Programm" den et erméiglecht PDF-Fichieren als Bild ze kucken',
	'pdf_no_metadata' => 'Meta-Informatiounen aus dem PDF Dokument kënnen net gelies ginn',
	'pdf_page_error' => "D'Säitenzuel ass net an dem Beräich.",
	'exif-pdf-version' => 'Versioun vum PDF-Format',
	'exif-pdf-encrypted' => 'Verschlësselt',
	'exif-pdf-pagesize' => 'Gréisst vun der Säit',
);

/** Limburgish (Limburgs)
 * @author Ooswesthoesbes
 */
$messages['li'] = array(
	'pdf-desc' => "Hanjeltj PDF-bestenj aaf en maak 't meugelik die es aafbeildjing te zeen",
	'pdf_no_metadata' => 'Kèn gein metadata vanne PDF kriege',
	'pdf_page_error' => 'paginanómmer besteit neet',
);

/** Lithuanian (lietuvių)
 * @author Matasg
 */
$messages['lt'] = array(
	'pdf-desc' => 'Įrankis PDF failų peržiūrai vaizdo režime',
	'pdf_no_metadata' => 'Nepavyko gauti metaduomenų iš PDF',
	'pdf_page_error' => 'Puslapis numeris nėra diapazone',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 * @author Brest
 */
$messages['mk'] = array(
	'pdf-desc' => 'Ракувач за прегледување PDF податотеки во сликовен режим',
	'pdf_no_metadata' => 'Не може да се земат метаподатоци од PDF',
	'pdf_page_error' => 'Бројот на страница е надвор од опсег',
	'exif-pdf-producer' => 'Програм за претворање',
	'exif-pdf-version' => 'Верзија на PDF-форматот',
	'exif-pdf-encrypted' => 'Шифрирано',
	'exif-pdf-pagesize' => 'Големина на страницата',
);

/** Malayalam (മലയാളം)
 * @author Praveenp
 * @author Shijualex
 */
$messages['ml'] = array(
	'pdf-desc' => 'പി.ഡി.എഫ്. പ്രമാണങ്ങൾ ചിത്രരൂപത്തിൽ കാണുന്നതിനുള്ള കൈകാര്യോപകരണം',
	'pdf_no_metadata' => 'PDF-ൽ നിന്നു മെറ്റാഡാറ്റ ലഭിച്ചില്ല',
	'pdf_page_error' => 'താളിന്റെ ക്രമസംഖ്യ പരിധിയിലധികമാണ്',
	'exif-pdf-producer' => 'പരിവർത്തന പ്രോഗ്രാം',
	'exif-pdf-version' => 'പി.ഡി.എഫ്. തരത്തിന്റെ പതിപ്പ്',
	'exif-pdf-encrypted' => 'നിഗൂഢീകരിക്കപ്പെട്ടത്',
	'exif-pdf-pagesize' => 'താളിന്റെ വലിപ്പം',
);

/** Marathi (मराठी)
 * @author Kaustubh
 * @author Sankalpdravid
 */
$messages['mr'] = array(
	'pdf-desc' => 'चित्र मोडमध्ये पीडीएफ संचिका पाहण्यासाठी आवश्यक प्रणाली',
	'pdf_no_metadata' => 'पीडीएफमधून मेटाडाटा घेऊ शकत नाही',
	'pdf_page_error' => 'पान क्रमांक सीमेमध्ये नाही',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 */
$messages['ms'] = array(
	'pdf-desc' => 'Pengendali untuk melihat fail PDF dalam mod imej',
	'pdf_no_metadata' => 'Metadata tidak boleh diperoleh dari PDF',
	'pdf_page_error' => 'Nombor halaman tiada dalam julat',
	'exif-pdf-producer' => 'Program penukaran',
	'exif-pdf-version' => 'Versi format PDF',
	'exif-pdf-encrypted' => 'Disulitkan',
	'exif-pdf-pagesize' => 'Saiz halaman',
);

/** Maltese (Malti)
 * @author Chrisportelli
 */
$messages['mt'] = array(
	'pdf_page_error' => 'In-numru tal-paġna ma jinsabx fl-intervall',
);

/** Norwegian Bokmål (norsk bokmål)
 * @author Jsoby
 */
$messages['nb'] = array(
	'pdf-desc' => 'Håndtering av PDF-visning i bildemodus',
	'pdf_no_metadata' => 'kan ikke hente metadata fra PDF',
	'pdf_page_error' => 'Sidenummer overstiger antall sider i dokumentet',
	'exif-pdf-producer' => 'Koverteringsprogram',
	'exif-pdf-version' => 'Versjon av PDF-format',
	'exif-pdf-encrypted' => 'Kryptert',
	'exif-pdf-pagesize' => 'Sidestørrelse',
);

/** Dutch (Nederlands)
 * @author Siebrand
 * @author Wiki13
 */
$messages['nl'] = array(
	'pdf-desc' => 'Handelt pdfbestanden af en maakt het mogelijk ze als afbeeldingen te bekijken',
	'pdf_no_metadata' => 'De metadata van het pdfbestand kan niet uitgelezen worden',
	'pdf_page_error' => 'Het paginanummer ligt niet binnen het bereik',
	'exif-pdf-producer' => 'Conversieprogramma',
	'exif-pdf-version' => 'Versie van pdfopmaak',
	'exif-pdf-encrypted' => 'Versleuteld',
	'exif-pdf-pagesize' => 'Papierformaat',
);

/** Norwegian Nynorsk (norsk nynorsk)
 * @author Harald Khan
 * @author Njardarlogar
 */
$messages['nn'] = array(
	'pdf-desc' => 'Handering av PDF-vising i biletmodus',
	'pdf_no_metadata' => 'Kan ikkje henta metadata frå PDF',
	'pdf_page_error' => 'Sidenummer overstig talet på sider i dokumentet',
);

/** Occitan (occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'pdf-desc' => 'Utilitari per visualizar los fichièrs PDF en mòde imatge',
	'pdf_no_metadata' => 'Pòt pas obténer las metadonadas del fichièr PDF',
	'pdf_page_error' => 'Lo numèro de pagina es pas dins la gama.',
	'exif-pdf-producer' => 'Programa de conversion',
	'exif-pdf-version' => 'Version del format PDF',
	'exif-pdf-encrypted' => 'Chifrat',
	'exif-pdf-pagesize' => 'Talha de la pagina',
);

/** Oriya (ଓଡ଼ିଆ)
 * @author Jnanaranjan Sahu
 * @author Psubhashish
 */
$messages['or'] = array(
	'pdf-desc' => 'PDF ଫାଇଲକୁ ଛବି ମୋଡ଼ରେ ଦେଖିବାର ପରିଚାଳକ',
	'pdf_no_metadata' => 'ପି.ଡ଼ି.ଏଫ.ରୁ ମେଟାଡାଟା ବାହାର କରିପାରିଲୁଁ ନାହିଁ',
	'pdf_page_error' => 'ପୃଷ୍ଠା ସଂଖ୍ୟା ସୀମା ଭିତରେ ନାହିଁ',
	'exif-pdf-producer' => 'ବଦଳ କାର୍ଯ୍ୟ',
	'exif-pdf-version' => 'PDF ପ୍ରକାରର ସଂସ୍କରଣ',
	'exif-pdf-encrypted' => 'ଏନକ୍ରିପ୍ଟ ହୋଇଥିବା',
	'exif-pdf-pagesize' => 'ପୃଷ୍ଠା ଆକାର',
);

/** Deitsch (Deitsch)
 * @author Xqt
 */
$messages['pdc'] = array(
	'pdf_no_metadata' => 'Keene Meta-Daade im PDF',
);

/** Polish (polski)
 * @author Holek
 * @author Matma Rex
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'pdf-desc' => 'Konwerter graficznego podglądu plików PDF',
	'pdf_no_metadata' => 'nie można pobrać metadanych z pliku PDF',
	'pdf_page_error' => 'Numer strony poza zakresem',
	'exif-pdf-producer' => 'Program użyty do konwersji',
	'exif-pdf-version' => 'Wersja formatu PDF',
	'exif-pdf-encrypted' => 'Zaszyfrowany',
	'exif-pdf-pagesize' => 'Wymiary strony',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Dragonòt
 */
$messages['pms'] = array(
	'pdf-desc' => 'Ël gestor për vëdde ij file PDF an manera image',
	'pdf_no_metadata' => 'as peulo nen pijesse ij metadat dal PDF',
	'pdf_page_error' => "Ël nùmer ëd pàgina a l'é pa ant ël range",
	'exif-pdf-producer' => 'Programa ëd conversion',
	'exif-pdf-version' => 'Version dël formà PDF',
	'exif-pdf-encrypted' => 'Criptà',
	'exif-pdf-pagesize' => 'Dimension dla pàgina',
);

/** Western Punjabi (پنجابی)
 * @author Khalid Mahmood
 */
$messages['pnb'] = array(
	'pdf-desc' => 'پی ڈی ایف  فائلاں امیج موڈ چ ویکھن لئی ہینڈلر',
	'pdf_no_metadata' => 'پی ڈی ایف توں میٹاڈیٹا نئیں مل سکیا۔',
	'pdf_page_error' => 'صفہ نمبر ولگن چ نئیں۔',
);

/** Portuguese (português)
 * @author Hamilton Abreu
 * @author Malafaya
 */
$messages['pt'] = array(
	'pdf-desc' => 'Manuseador de visionamento de ficheiros PDF em modo de imagem',
	'pdf_no_metadata' => 'não foi possível obter os metadados do PDF',
	'pdf_page_error' => 'Número de página fora do intervalo',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Eduardo.mps
 * @author 555
 */
$messages['pt-br'] = array(
	'pdf-desc' => 'Ferramenta de visualização de arquivos PDF em modo de imagem',
	'pdf_no_metadata' => 'Não foi possível obter os metadados do PDF',
	'pdf_page_error' => 'Número de página fora do intervalo',
	'exif-pdf-producer' => 'Programa de conversão',
	'exif-pdf-version' => 'Versão do formato PDF',
	'exif-pdf-encrypted' => 'Criptografado',
	'exif-pdf-pagesize' => 'Tamanho da página',
);

/** Romanian (română)
 * @author Stelistcristi
 */
$messages['ro'] = array(
	'pdf-desc' => 'Operator pentru vizualizarea fișierelor PDF în modul de imagine',
	'pdf_no_metadata' => 'Nu se poate obține metadate din PDF',
	'pdf_page_error' => 'Numărul paginii nu e în șir',
	'exif-pdf-pagesize' => 'Dimensiunea paginii',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'pdf-desc' => 'Gestore pe vedè le file PDF in mode immaggine',
	'pdf_no_metadata' => "Non ge pozze pigghià le metadata da 'u PDF",
	'pdf_page_error' => "Numere de pàgene fore da l'indervalle",
	'exif-pdf-producer' => 'Programme de conversione',
	'exif-pdf-version' => "Versione d'u formate PDF",
	'exif-pdf-encrypted' => 'Criptate',
	'exif-pdf-pagesize' => "Dimenzione d'a pàgene",
);

/** Russian (русский)
 * @author DCamer
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'pdf-desc' => 'Обработчик для просмотра PDF-файлов в виде изображений',
	'pdf_no_metadata' => 'невозможно получить метаданные из PDF',
	'pdf_page_error' => 'Номер страницы вне диапазона',
	'exif-pdf-producer' => 'Программа преобразования',
	'exif-pdf-version' => 'Версия в формате PDF',
	'exif-pdf-encrypted' => 'Шифрование',
	'exif-pdf-pagesize' => 'Размер страницы',
);

/** Rusyn (русиньскый)
 * @author Gazeb
 */
$messages['rue'] = array(
	'pdf-desc' => 'Овладач про перегляд PDF файлів як образків',
	'pdf_no_metadata' => 'Не годен обтримати метадата з PDF',
	'pdf_page_error' => 'Чісло сторінкы не є в россягу',
);

/** Sanskrit (संस्कृतम्)
 * @author Shubha
 */
$messages['sa'] = array(
	'pdf-desc' => 'सुलेख(PDF)सञ्चिकाः चित्रदशायां दर्शनाय अपेक्षिता प्रणाली',
	'pdf_no_metadata' => 'सुलेखात् मेटादत्तांशः प्राप्तुम् अशक्यः',
	'pdf_page_error' => 'पृष्ठक्रमाङ्कः सीमायां न विद्यते',
);

/** Sakha (саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'pdf-desc' => 'PDF билэлэри ойуу курдук көрдөрөөччү',
	'pdf_no_metadata' => 'PDF-тан мета дааннайдарын ылар кыах суох',
	'pdf_page_error' => 'Сирэй нүөмэрэ диапазоҥҥа киирбэт',
);

/** Sinhala (සිංහල)
 * @author Budhajeewa
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'pdf-desc' => 'PDF ගොනු රූප මාදිලියෙන් හසුරුවනය',
	'pdf_no_metadata' => 'PDF ගොනුවෙන් මෙටාදත්ත ගත නොහැක',
	'pdf_page_error' => 'පිටු අංකය නිවැරදි පරාසයේ නොමැත',
	'exif-pdf-producer' => 'හැරවුම් වැඩසටහන',
	'exif-pdf-version' => 'PDF ආකෘතියේ අනුවාදය',
	'exif-pdf-encrypted' => 'ගුප්තකේතීකරණය වූ',
	'exif-pdf-pagesize' => 'පිටු ප්‍රමාණය',
);

/** Slovak (slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'pdf-desc' => 'Obsluha zobrazovania PDF súborov v režime obrázkov',
	'pdf_no_metadata' => 'nie je možné získať metadáta z PDF',
	'pdf_page_error' => 'Číslo stránky nie je v intervale',
);

/** Slovenian (slovenščina)
 * @author Dbc334
 */
$messages['sl'] = array(
	'pdf-desc' => 'Upravljavec ogledovanja datotek PDF v slikovnem načinu',
	'pdf_no_metadata' => 'Ne morem pridobiti metapodatkov iz PDF',
	'pdf_page_error' => 'Številka strani ni v dosegu',
	'exif-pdf-producer' => 'Pretvorbeni program',
	'exif-pdf-version' => 'Različica oblike PDF',
	'exif-pdf-encrypted' => 'Šifrirano',
	'exif-pdf-pagesize' => 'Velikost strani',
);

/** Albanian (shqip)
 * @author Olsi
 */
$messages['sq'] = array(
	'pdf-desc' => 'Mbajtës për pamjen e skedave PDF në mënyrën e figurave',
	'pdf_no_metadata' => 'Nuk mund të merren metadata nga PDF',
	'pdf_page_error' => 'Numri i faqes nuk është në varg',
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎)
 * @author Rancher
 * @author Михајло Анђелковић
 */
$messages['sr-ec'] = array(
	'pdf-desc' => 'Програм за прегледање PDF докумената у сликовном режиму',
	'pdf_no_metadata' => 'Не могу да преузмем метаподатке из PDF-а',
	'pdf_page_error' => 'Број страница ван опсега',
);

/** Serbian (Latin script) (srpski (latinica)‎)
 * @author Michaello
 */
$messages['sr-el'] = array(
	'pdf-desc' => 'Handler za pregled PDF fajlova kao slika',
	'pdf_no_metadata' => 'Ne mogu se dobiti meta-podaci iz PDF-a',
	'pdf_page_error' => 'Broj strane izlazi van opsega',
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'pdf-desc' => 'Snitsteede foar dät Bekiekjen fon PDF-Doatäie in dän Bielde-Modus',
	'pdf_no_metadata' => 'Neen Metadoaten in dät PDF deer.',
	'pdf_page_error' => 'Siedentaal buute Riege.',
);

/** Swedish (svenska)
 * @author Ainali
 * @author M.M.S.
 */
$messages['sv'] = array(
	'pdf-desc' => 'Hantering av PDF-visning i bildläge',
	'pdf_no_metadata' => 'Kan inte hämta metadata från PDF',
	'pdf_page_error' => 'Sidnummer överstiger antal sidor i dokumentet',
	'exif-pdf-producer' => 'Konverteringsprogram',
	'exif-pdf-version' => 'Version av PDF-format',
	'exif-pdf-encrypted' => 'Krypterad',
	'exif-pdf-pagesize' => 'Sidstorlek',
);

/** Tamil (தமிழ்)
 * @author Shanmugamp7
 * @author TRYPPN
 * @author மதனாஹரன்
 */
$messages['ta'] = array(
	'pdf-desc' => 'PDF கோப்புகளை உருவ முறையில் பார்க்க கையாளுனர்',
	'pdf_no_metadata' => 'PDF இருந்து மேல்தரவை பெற இயலவில்லை',
	'pdf_page_error' => 'பக்கத்தின் எண் குறிப்பிட்ட வரையறையில் இல்லை',
	'exif-pdf-producer' => 'மாற்றனிரல்',
	'exif-pdf-pagesize' => 'பக்க அளவு',
);

/** Telugu (తెలుగు)
 * @author Veeven
 */
$messages['te'] = array(
	'pdf_page_error' => 'పుట సంఖ్య అవధిలో లేదు',
);

/** Turkmen (Türkmençe)
 * @author Hanberke
 */
$messages['tk'] = array(
	'pdf-desc' => 'PDF faýllaryny görkeziş režiminde görkezmek üçin işleýji',
	'pdf_no_metadata' => 'PDF-den meta-maglumat alyp bolanok',
	'pdf_page_error' => 'Sahypa belgisi diapazonda däl',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'pdf-desc' => 'Tagapaghawak para sa pagtanaw ng mga talaksang PDF na nasa modalidad na panglarawan',
	'pdf_no_metadata' => 'Hindi makuha ang dato ng meta mula sa PDF',
	'pdf_page_error' => 'Wala sa sakop ang bilang ng pahina',
);

/** Turkish (Türkçe)
 * @author Joseph
 */
$messages['tr'] = array(
	'pdf-desc' => 'PDF dosyalarını görüntü modunda görüntülemek için işleyici',
	'pdf_no_metadata' => "PDF'den metadata alınamıyor",
	'pdf_page_error' => 'Sayfa numarası aralıkta değil',
);

/** Uyghur (Arabic script) (ئۇيغۇرچە)
 * @author Sahran
 */
$messages['ug-arab'] = array(
	'exif-pdf-encrypted' => 'شىفىرلانغان',
	'exif-pdf-pagesize' => 'بەت چوڭلۇقى',
);

/** Ukrainian (українська)
 * @author Base
 * @author Prima klasy4na
 */
$messages['uk'] = array(
	'pdf-desc' => 'Оброблювач для перегляду PDF-файлів в режимі зображень',
	'pdf_no_metadata' => 'Не виходить отримати метадані з PDF',
	'pdf_page_error' => 'Номер сторінки не в діапазоні',
	'exif-pdf-producer' => 'програма конвертації',
	'exif-pdf-version' => 'Версія формату PDF',
	'exif-pdf-encrypted' => 'Зашифровано',
	'exif-pdf-pagesize' => 'Розмір сторінки',
);

/** Urdu (اردو)
 * @author පසිඳු කාවින්ද
 */
$messages['ur'] = array(
	'pdf_page_error' => 'صفحہ نمبر رینج میں نہیں',
);

/** vèneto (vèneto)
 * @author Candalua
 * @author GatoSelvadego
 */
$messages['vec'] = array(
	'pdf-desc' => 'Handler par vardar i file PDF in modalità imagine',
	'pdf_no_metadata' => 'No se riesse a recuperar i metadati dal PDF',
	'pdf_page_error' => "Nùmaro de pagina mia conpreso in te l'intervalo",
	'exif-pdf-producer' => 'Programa de conversion',
	'exif-pdf-version' => 'Version del formato PDF',
	'exif-pdf-encrypted' => 'Critigrafà',
	'exif-pdf-pagesize' => 'Dimension pàjina',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'pdf-desc' => 'Bộ xử lý để xem tập tin PDF ở dạng hình ảnh',
	'pdf_no_metadata' => 'Không thấy truy xuất siêu dữ liệu từ PDF',
	'pdf_page_error' => 'Số trang không nằm trong giới hạn',
	'exif-pdf-producer' => 'Chương trình chuyển đổi',
	'exif-pdf-version' => 'Phiên bản định dạng PDF',
	'exif-pdf-encrypted' => 'Mã hóa',
	'exif-pdf-pagesize' => 'Kích thước trang',
);

/** Yoruba (Yorùbá)
 * @author Demmy
 */
$messages['yo'] = array(
	'pdf_no_metadata' => 'Dátà-àtẹ̀yìnwá kó ṣe é mú láti inú PDF',
);

/** Cantonese (粵語)
 */
$messages['yue'] = array(
	'pdf-desc' => '響圖像模式睇PDF檔嘅處理器',
	'pdf_no_metadata' => '唔能夠響PDF度拎metadata',
	'pdf_page_error' => '頁數唔響範圍度',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Shirayuki
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'pdf-desc' => '在图像模式中查看PDF文件的处理器。',
	'pdf_no_metadata' => '无法在PDF中获取元数据。',
	'pdf_page_error' => '页数不在范围内。',
	'exif-pdf-producer' => '转换程序',
	'exif-pdf-version' => 'PDF格式的版本',
	'exif-pdf-encrypted' => '加密',
	'exif-pdf-pagesize' => '页面大小',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Justincheng12345
 * @author Mark85296341
 * @author Simon Shek
 */
$messages['zh-hant'] = array(
	'pdf-desc' => '在圖片模式中查看PDF檔案的處理器',
	'pdf_no_metadata' => '無法在 PDF 中擷取元數據',
	'pdf_page_error' => '頁數不在範圍中',
	'exif-pdf-producer' => '轉換程式',
	'exif-pdf-version' => 'PDF格式的版本',
	'exif-pdf-encrypted' => '加密',
	'exif-pdf-pagesize' => '頁面大小',
);

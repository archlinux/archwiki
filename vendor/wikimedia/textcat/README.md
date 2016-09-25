# TextCat

     /\_/\
    ( . . )
    =\_v_/=

This is a PHP port of the TextCat language guesser utility.

Please see http://odur.let.rug.nl/~vannoord/TextCat/ for the original one.

## Contents

The package contains the classifier class itself and two tools—for classifying the texts and for generating the ngram database.
The code now assumes the text encoding is UTF-8, since it's easier to extract ngrams this way.
Also, everybody uses UTF-8 now and I, for one, welcome our new UTF-8-encoded overlords.

### Classifier

The classifier is the script `catus.php`, which can be run as:

    echo "Bonjour tout le monde, ceci est un texte en français" | php catus.php -d LM

or

    php catus.php -d LM -l "Bonjour tout le monde, ceci est un texte en français"

The output would be the list of the languages, e.g.:

    fr OR ro

Please note that the provided collection of language models includes a model for Oriya (ଓଡ଼ିଆ), which has the language code `or`, so results like `or OR sco OR ro OR nl` are possible.

### Generator

To generate the language model database from a set of texts, use the script `felis.php`. It can be run as:

    php felis.php INPUTDIR OUTPUTDIR

And will read texts from `INPUTDIR` and generate ngrams files in `OUTPUTDIR`.
The files in `INPUTDIR` are assumed to have names like `LANGUAGE.txt`, e.g. `english.txt`, `german.txt`, `klingon.txt`, etc.

If you are working with sizable corpora (e.g., millions of characters), you should set `$minFreq` in `TextCat.php` to a reasonably small value, like `10`, to trim the very long tail of infrequent ngrams before they are sorted. This reduces the CPU and memory requirements for generating the language models. When *evaluating* texts, `$minFreq` should be set back to `0` unless your input texts are fairly large.

## Models

The package comes with a default language model database in the `LM` directory and a query-based language model database in the `LM-query` directory. However, model performance will depend a lot on the text corpus it will be applied to, as well as specific modifications—e.g. capitalization, diacritics, etc. Currently the library does not modify or normalize either training texts or classified texts in any way, so usage of custom language models may be more appropriate for specific applications.

Model names use [Wikipedia language codes](https://en.wikipedia.org/wiki/List_of_Wikipedias), which are often but not guaranteed to be the same as [ISO 639 language codes](https://en.wikipedia.org/wiki/ISO_639).

When detecting languages, you will generally get better results when you can limit the number of language models in use. For example, if there is virtually no chance that your text could be in Irish Gaelic, including the Irish Gaelic language model (`ga`) only increases the likelihood of mis-identification. This is particularly true for closely related languages (e.g., the Romance languages, or English/`en` and Scots/`sco`).

Limiting the number of language models used also generally improves performance. You can copy your desired language models into a new directory (and use `-d` with `catus.php`) or specify your desired languages on the command line (use `-c` with `catus.php`).

### Wiki-Text models

The 70 language models in `LM` are based on text extracted from randomly chosen articles from the Wikipedia for that language. The languages included were chosen based on a number of criteria, including the number of native speakers of the language, the number of queries to the various wiki projects in the language (not just Wikipedia), the list of languages supported by the original TextCat, and the size of Wikipedia in the language (i.e., the size of the collection from which to draw a training corpus).

The training corpus for each language was originally made up of ~2.7 to ~2.8M million characters, excluding markup. The texts were then lightly preprocessed. Preprocessing steps taken include: HTML Tags were removed. Lines were sorted and `uniq`-ed (so that Wikipedia idiosyncrasies—like "References", "See Also", and "This article is a stub"—are not over-represented, and so that articles randomly selected more than once were reduced to one copy). For corpora in Latin character sets, lines containing no Latin characters were removed. For corpora in non-Latin character sets, lines containing only Latin characters, numbers, and punctuation were removed. This character-set-based filtering removed from dozens to thousands of lines from the various corpora. For corpora in multiple character sets (e.g., Serbo-Croatian/`sh`, Serbian/`sr`, Turkmen/`tk`), no such character-set-based filtering was done. The final size of the training corpora ranged from ~1.8M to ~2.8M characters.

These models have not been tested and are provided as-is. We may add new models or remove poorly-performing models in the future.

These models have 4000 ngrams. The best number of ngrams to use for language identification is application-dependent. For larger texts (e.g., containing hundreds of words per sample), significantly smaller ngram sets may be best. You can set the number to be used by changing `$maxNgrams` in `TextCat.php` or in `felis.php`, or using `-t` with `catus.php`.

### Wiki Query Models.

The 19 language models in `LM-query` are based on query data from Wikipedia which is less formal (e.g., fewer diacritics are used in languages that have them) and has a different distribution of words than general text. The original set of languages considered was based on the number of queries across all wiki projects for a particular week. The text has been preprocessed and many queries were removed from the training sets according to a process similar to that used on the Wiki-Text models above.

In general, query data is much messier than Wiki-Text—including junk text and queries in unexpected languages—but the overall performance on query strings, at least for English Wikipedia—is better.

The final set of models provided is based in part on their performance on English Wikipedia queries (the first target for language ID using TextCat). For more details see our [initial report](https://www.mediawiki.org/wiki/User:TJones_(WMF)/Notes/Language_Detection_with_TextCat) on TextCat. More languages will be added in the future based on additional performance evaluations.

These models have 5000 ngrams. The best number of ngrams to use for language identification is application-dependent. For larger texts (e.g., containing hundreds of words per sample), significantly smaller ngram sets may be best. For short query seen on English Wikipedia strings, a model size of 3000 ngrams has worked best. You can set the number to be used by changing `$maxNgrams` in `TextCat.php` or in `felis.php`, or using `-t` with `catus.php`.


[![Build Status](https://travis-ci.org/smalyshev/textcat.svg?branch=master)](https://travis-ci.org/smalyshev/textcat)

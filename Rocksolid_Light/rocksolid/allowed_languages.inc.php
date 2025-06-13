<?php
/**
 * Rocksolid Light - Allowed Languages Configuration
 *
 * This file contains the definitive list of allowed languages for the system.
 * Only languages listed in this array can be selected by users.
 * This provides better security than regex validation alone.
 */

// Hardcoded array of allowed languages for security
$ALLOWED_LANGUAGES = array(
    'akan.lang' => 'Akan',
    'albanian.lang' => 'Albanian',
    'amharic.lang' => 'Amharic',
    'arabic.lang' => 'Arabic',
    'armenian.lang' => 'Armenian',
    'awadhi.lang' => 'Awadhi',
    'azerbaijani.lang' => 'Azerbaijani',
    'basque.lang' => 'Basque',
    'bengali.lang' => 'Bengali',
    'bhojpuri.lang' => 'Bhojpuri',
    'bosanski.lang' => 'Bosnian',
    'breton.lang' => 'Breton',
    'bulgarian.lang' => 'Bulgarian',
    'burmese.lang' => 'Burmese',
    'catalan.lang' => 'Catalan',
    'cebuano.lang' => 'Cebuano',
    'chinese_simplified.lang' => 'Chinese Simplified',
    'chinese_traditional.lang' => 'Chinese Traditional',
    'croatian.lang' => 'Croatian',
    'czech.lang' => 'Czech',
    'danish.lang' => 'Danish',
    'deutsch.lang' => 'German',
    'dutch.lang' => 'Dutch',
    'english.lang' => 'English',
    'esperanto.lang' => 'Esperanto',
    'estonian.lang' => 'Estonian',
    'faroese.lang' => 'Faroese',
    'filipino.lang' => 'Filipino',
    'finnish.lang' => 'Finnish',
    'francais.lang' => 'French',
    'fula.lang' => 'Fula',
    'galician.lang' => 'Galician',
    'greek.lang' => 'Greek',
    'guarani.lang' => 'Guarani',
    'gujarati.lang' => 'Gujarati',
    'hausa.lang' => 'Hausa',
    'hebrew.lang' => 'Hebrew',
    'hindi.lang' => 'Hindi',
    'hungarian.lang' => 'Hungarian',
    'icelandic.lang' => 'Icelandic',
    'igbo.lang' => 'Igbo',
    'indonesian.lang' => 'Indonesian',
    'irish.lang' => 'Irish',
    'italiano.lang' => 'Italian',
    'japanese.lang' => 'Japanese',
    'javanese.lang' => 'Javanese',
    'kannada.lang' => 'Kannada',
    'kanuri.lang' => 'Kanuri',
    'kazakh.lang' => 'Kazakh',
    'khmer.lang' => 'Khmer',
    'kinyarwanda.lang' => 'Kinyarwanda',
    'konkani.lang' => 'Konkani',
    'korean.lang' => 'Korean',
    'kurdish.lang' => 'Kurdish',
    'lao.lang' => 'Lao',
    'latvian.lang' => 'Latvian',
    'lithuanian.lang' => 'Lithuanian',
    'luxembourgish.lang' => 'Luxembourgish',
    'macedonian.lang' => 'Macedonian',
    'maithili.lang' => 'Maithili',
    'malayalam.lang' => 'Malayalam',
    'malay.lang' => 'Malay',
    'maltese.lang' => 'Maltese',
    'manipuri.lang' => 'Manipuri',
    'marathi.lang' => 'Marathi',
    'mongolian.lang' => 'Mongolian',
    'montenegrin.lang' => 'Montenegrin',
    'nepali.lang' => 'Nepali',
    'norsk.lang' => 'Norwegian',
    'odia.lang' => 'Odia',
    'oromo.lang' => 'Oromo',
    'pashto.lang' => 'Pashto',
    'persian.lang' => 'Persian',
    'polish.lang' => 'Polish',
    'portuguese_brazilian.lang' => 'Portuguese Brazilian',
    'portugues.lang' => 'Portuguese',
    'punjabi.lang' => 'Punjabi',
    'quechua.lang' => 'Quechua',
    'romanian.lang' => 'Romanian',
    'russian.lang' => 'Russian',
    'sardinian.lang' => 'Sardinian',
    'serbian.lang' => 'Serbian',
    'shona.lang' => 'Shona',
    'sindhi.lang' => 'Sindhi',
    'sinhala.lang' => 'Sinhala',
    'slovak.lang' => 'Slovak',
    'slovenski.lang' => 'Slovenian',
    'somali.lang' => 'Somali',
    'spanish.lang' => 'Spanish',
    'sudanese_arabic.lang' => 'Sudanese Arabic',
    'swahili.lang' => 'Swahili',
    'swedish.lang' => 'Swedish',
    'tagalog.lang' => 'Tagalog',
    'tamazight.lang' => 'Tamazight',
    'tamil.lang' => 'Tamil',
    'telugu.lang' => 'Telugu',
    'thai.lang' => 'Thai',
    'tibetan.lang' => 'Tibetan',
    'tigrinya.lang' => 'Tigrinya',
    'tswana.lang' => 'Tswana',
    'turkish.lang' => 'Turkish',
    'ukrainian.lang' => 'Ukrainian',
    'urdu.lang' => 'Urdu',
    'uzbek.lang' => 'Uzbek',
    'vietnamese.lang' => 'Vietnamese',
    'welsh.lang' => 'Welsh',
    'wolof.lang' => 'Wolof',
    'xhosa.lang' => 'Xhosa',
    'yoruba.lang' => 'Yoruba',
    'zulu.lang' => 'Zulu'
);

/**
 * Check if a language is allowed
 * @param string $language_file The language filename (e.g., 'english.lang')
 * @return bool True if language is allowed, false otherwise
 */
if (!function_exists('is_language_allowed')) {
    function is_language_allowed($language_file) {
        global $ALLOWED_LANGUAGES;
        return isset($ALLOWED_LANGUAGES[$language_file]);
    }
}

/**
 * Get the display name for a language
 * @param string $language_file The language filename (e.g., 'english.lang')
 * @return string The display name or the filename if not found
 */
if (!function_exists('get_language_display_name')) {
    function get_language_display_name($language_file) {
        global $ALLOWED_LANGUAGES;
        return $ALLOWED_LANGUAGES[$language_file] ?? $language_file;
    }
}

/**
 * Get all allowed languages
 * @return array Array of language_file => display_name
 */
if (!function_exists('get_allowed_languages')) {
    function get_allowed_languages() {
        global $ALLOWED_LANGUAGES;
        return $ALLOWED_LANGUAGES;
    }
}
?>

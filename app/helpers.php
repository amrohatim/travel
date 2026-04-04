<?php

use ArPHP\I18N\Arabic;

if (! function_exists('arabic_text')) {
    function arabic_text(?string $text): string
    {
        $value = $text ?? '';
        if (trim($value) === '') {
            return '';
        }

        static $arabic = null;
        if ($arabic === null) {
            $arabic = new Arabic('Glyphs');
        }

        return $arabic->utf8Glyphs($value, 50, true, true);
    }
}

<?php

if (! function_exists('str_word_count_utf8')) {
    /** Word count that understands Arabic (str_word_count does not). */
    function str_word_count_utf8(?string $text): int
    {
        if ($text === null || trim($text) === '') {
            return 0;
        }

        return count(preg_split('/[\s\x{200B}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}

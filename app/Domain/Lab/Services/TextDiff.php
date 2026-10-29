<?php

namespace App\Domain\Lab\Services;

/** «ما الذي تغيّر؟» — a line-based diff between two asset versions (LCS; assets are short). */
class TextDiff
{
    /** @return array{added: string[], removed: string[], unchanged: int, summary: string} */
    public static function lines(string $old, string $new): array
    {
        $a = array_values(array_filter(array_map('rtrim', preg_split('/\R/u', $old) ?: []), fn ($l) => $l !== ''));
        $b = array_values(array_filter(array_map('rtrim', preg_split('/\R/u', $new) ?: []), fn ($l) => $l !== ''));
        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $added = [];
        $removed = [];
        $unchanged = 0;
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $unchanged++;
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $removed[] = $a[$i++];
            } else {
                $added[] = $b[$j++];
            }
        }
        while ($i < $n) {
            $removed[] = $a[$i++];
        }
        while ($j < $m) {
            $added[] = $b[$j++];
        }
        $summary = $added === [] && $removed === [] ? 'لا تغيير في النص.' : sprintf('%d سطراً أُضيف، %d سطراً حُذف، %d بلا تغيير.', count($added), count($removed), $unchanged);

        return ['added' => $added, 'removed' => $removed, 'unchanged' => $unchanged, 'summary' => $summary];
    }
}

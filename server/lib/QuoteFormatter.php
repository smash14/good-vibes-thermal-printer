<?php

// Wraps quote text to a maximum line length, preferring to break on spaces.
// Kept as its own class (rather than a method on QuoteRepository) because
// it's a pure text-formatting rule with no file I/O — easy to reason about
// and reuse on its own.
final class QuoteFormatter
{
    public static function wrap(string $text, int $maxLength): string
    {
        $lines = explode("\n", $text);
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) <= $maxLength) {
                $result[] = $line;
                continue;
            }

            while (strlen($line) > $maxLength) {
                $substring = substr($line, 0, $maxLength);
                $lastSpace = strrpos($substring, ' ');

                if ($lastSpace === false) {
                    // No space to break on — keep the word intact.
                    $result[] = $line;
                    $line = '';
                    break;
                }

                $result[] = substr($line, 0, $lastSpace);
                $line = trim(substr($line, $lastSpace));
            }

            if ($line !== '') {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }
}

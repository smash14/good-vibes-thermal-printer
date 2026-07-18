<?php

// Shorthand for escaping values before printing them into HTML.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

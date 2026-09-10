<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * One custom property as authored in app.css.
 *
 * The stylesheet is the second home of every design value, so the pair has to
 * be checked rather than trusted: a value corrected in PHP but not in CSS would
 * leave the suite green while the app rendered the old one.
 */
final class Stylesheet
{
    /** A `#rrggbb` value. */
    public static function hex(string $selector, string $property): string
    {
        return self::read($selector, $property, '(#[0-9a-f]{6})');
    }

    /** Any value — shadows, lengths, filter functions. Returned verbatim. */
    public static function raw(string $selector, string $property): string
    {
        return self::read($selector, $property, '([^;]+)');
    }

    private static function read(string $selector, string $property, string $pattern): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/app.css')));

        preg_match_all('/(?<selector>[^{}]+)\{(?<body>[^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $lines = array_filter(array_map(trim(...), explode("\n", $block['selector'])));

            if (end($lines) !== $selector) {
                continue;
            }

            if (preg_match('/' . preg_quote($property, '/') . ':\s*' . $pattern . ';/', $block['body'], $value) === 1) {
                return trim($value[1]);
            }
        }

        throw new RuntimeException("app.css declares no {$property} on {$selector}.");
    }
}

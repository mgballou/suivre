<?php

declare(strict_types=1);

namespace App\Services\Food\Data;

use App\Models\FoodItem;
use Illuminate\Support\Str;

/**
 * One product record from an Open Food Facts export, reduced to the six fields
 * the catalog import actually reads.
 *
 * The export carries around two hundred fields per product — nutriments, photo
 * URLs, edit history, per-language name variants. Parsing straight into a
 * narrow DTO means the rest never reaches the domain, and the tag-derivation
 * rules have a typed surface to read rather than a raw decoded array.
 *
 * Every tag field arrives as a list of locale-prefixed slugs (`en:milk`,
 * `en:united-kingdom`); the prefix is Open Food Facts' own convention, not a
 * language the caller has to care about.
 */
readonly class OpenFoodFactsProduct
{
    /**
     * @param  array<int, string>  $allergenTags
     * @param  array<int, string>  $ingredientTags
     * @param  array<int, string>  $additiveTags
     * @param  array<int, string>  $countryTags
     * @param  array<int, string>  $categoryTags
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $allergenTags,
        public array $ingredientTags,
        public array $additiveTags,
        public array $countryTags,
        public array $categoryTags,
    ) {}

    public function isFromCountry(string $country): bool
    {
        return self::matches($this->countryTags, $country);
    }

    public function isInCategory(string $category): bool
    {
        return self::matches($this->categoryTags, $category);
    }

    /**
     * Reads one line of a JSONL export, or returns null when the line cannot
     * become a catalog entry.
     *
     * Null rather than an exception because unusable lines are ordinary at this
     * scale, not exceptional: the dump is crowd-sourced, and a run over millions
     * of products meets truncated JSON, products with no name in any language,
     * and barcodes recorded as empty strings. Each is counted as skipped and the
     * import carries on.
     */
    public static function fromJsonLine(string $line): ?self
    {
        $decoded = json_decode($line, associative: true);

        if (! is_array($decoded)) {
            return null;
        }

        $code = self::text($decoded, 'code');
        $name = self::text($decoded, 'product_name');

        if ($name === '') {
            $name = self::text($decoded, 'product_name_en');
        }

        // A name that normalizes away entirely ("---", "?!") would collide with
        // every other such product on the empty string the catalog dedups by.
        if ($code === '' || $name === '' || FoodItem::normalizeName($name) === '') {
            return null;
        }

        return new self(
            code: $code,
            name: $name,
            allergenTags: self::tags($decoded, 'allergens_tags'),
            ingredientTags: self::tags($decoded, 'ingredients_tags'),
            additiveTags: self::tags($decoded, 'additives_tags'),
            countryTags: self::tags($decoded, 'countries_tags'),
            categoryTags: self::tags($decoded, 'categories_tags'),
        );
    }

    /**
     * @param  array<mixed>  $decoded
     */
    private static function text(array $decoded, string $key): string
    {
        $value = $decoded[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<int, string>
     */
    private static function tags(array $decoded, string $key): array
    {
        $value = $decoded[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $strings = array_filter($value, is_string(...));

        return array_values(array_map(
            static fn (string $tag): string => Str::lower(trim($tag)),
            $strings,
        ));
    }

    /**
     * Compares on the part after the locale prefix, so a filter reads the way
     * someone would type it (`united-kingdom`) as well as the way the export
     * writes it (`en:united-kingdom`).
     *
     * @param  array<int, string>  $tags
     */
    private static function matches(array $tags, string $needle): bool
    {
        $wanted = Str::afterLast(Str::lower(trim($needle)), ':');

        foreach ($tags as $tag) {
            if (Str::afterLast($tag, ':') === $wanted) {
                return true;
            }
        }

        return false;
    }
}

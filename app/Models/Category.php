<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryGroup;
use Carbon\CarbonImmutable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A curated trigger category — the authoritative, global tag vocabulary that
 * food entries are classified against (D9). Categories are operator-owned
 * reference data managed in the Filament backstage: global, never user-scoped,
 * and deliberately stable so correlation stays statistically honest.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CategoryGroup $group
 * @property string|null $description
 * @property string|null $research_source
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'group', 'description', 'research_source'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => CategoryGroup::class,
        ];
    }
}

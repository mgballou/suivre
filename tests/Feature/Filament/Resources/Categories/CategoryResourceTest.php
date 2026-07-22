<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Resources\Categories;

use App\Enums\CategoryGroup;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Categories\Pages\ViewCategory;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Livewire\Livewire;

beforeEach(function (): void {
    // Curating the taxonomy is an administrator surface, so the acting user is one.
    $this->actingAs(User::factory()->admin()->createQuietly());
});

it('lists categories', function (): void {
    $categories = Category::factory()->count(3)->createQuietly();

    Livewire::test(ListCategories::class)
        ->assertOk()
        ->assertCanSeeTableRecords($categories);
});

it('exposes the row actions on the list page', function (): void {
    $category = Category::factory()->createQuietly();

    Livewire::test(ListCategories::class)
        ->assertActionExists(TestAction::make(ViewAction::class)->table($category))
        ->assertActionExists(TestAction::make(EditAction::class)->table($category));
});

it('bulk deletes categories from the list page', function (): void {
    $categories = Category::factory()->count(2)->createQuietly();

    Livewire::test(ListCategories::class)
        ->selectTableRecords($categories->modelKeys())
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertNotified();

    foreach ($categories as $category) {
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
});

it('filters the table by group', function (): void {
    $allergen = Category::factory()->group(CategoryGroup::Allergen)->createQuietly();
    $research = Category::factory()->group(CategoryGroup::Research)->createQuietly();

    Livewire::test(ListCategories::class)
        ->filterTable('group', [CategoryGroup::Allergen->value])
        ->assertCanSeeTableRecords([$allergen])
        ->assertCanNotSeeTableRecords([$research]);
});

it('creates a category', function (): void {
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Histamine',
            'slug' => 'histamine',
            'group' => CategoryGroup::Research->value,
            'description' => 'Histamine-rich and histamine-liberating foods.',
            'research_source' => 'https://example.test/histamine',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('categories', [
        'slug' => 'histamine',
        'group' => CategoryGroup::Research->value,
    ]);
});

it('validates the create form', function (array $data, array $errors): void {
    Category::factory()->createQuietly(['slug' => 'taken-slug']);

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Nightshade',
            'slug' => 'nightshade',
            'group' => CategoryGroup::Research->value,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`slug` is required' => [['slug' => null], ['slug' => 'required']],
    '`slug` is unique' => [['slug' => 'taken-slug'], ['slug' => 'unique']],
    '`slug` is alpha-dash' => [['slug' => 'not a slug'], ['slug' => 'alpha_dash']],
    '`group` is required' => [['group' => null], ['group' => 'required']],
]);

it('loads an existing category into the edit form', function (): void {
    $category = Category::factory()->group(CategoryGroup::Ingredient)->createQuietly();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $category->name,
            'slug' => $category->slug,
            'group' => CategoryGroup::Ingredient,
        ]);
});

it('updates a category', function (): void {
    $category = Category::factory()->group(CategoryGroup::Allergen)->createQuietly();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm([
            'name' => 'Tree nuts',
            'group' => CategoryGroup::Allergen->value,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Tree nuts',
    ]);
});

it('deletes a category from the edit page', function (): void {
    $category = Category::factory()->createQuietly();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

it('renders a category in the infolist', function (): void {
    $category = Category::factory()->group(CategoryGroup::Research)->createQuietly();

    Livewire::test(ViewCategory::class, ['record' => $category->getRouteKey()])
        ->assertOk()
        ->assertSee($category->name)
        ->assertSee(CategoryGroup::Research->getLabel());
});

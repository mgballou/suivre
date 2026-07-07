# Testing Filament Actions

## Overview

For standard Filament action testing documentation (table actions, bulk actions, schema actions, modals, validation, halted actions, etc.), use `search-docs` with queries like `['testing actions', 'TestAction', 'callAction']`.

This guideline covers project-specific conventions only.

## Prefer Action Classes Over Strings

When a custom action class exists, always use the class in `TestAction::make()` instead of a string name. This improves IDE support and refactoring safety.

@verbatim
<code-snippet name="Prefer action class over string" lang="php">
// ✅ Preferred - use action class
TestAction::make(SendInvoiceAction::class)->table($invoice)

// ❌ Avoid - string names when class exists
TestAction::make('send')->table($invoice)
</code-snippet>
@endverbatim

## Variable Assignment for Repeated Actions

**IMPORTANT**: When asserting the same action multiple times in a single test, assign the `TestAction` to a variable to avoid recreation:

@verbatim
<code-snippet name="Single action variable assignment" lang="php">
use App\Filament\Resources\Invoices\Actions\SendInvoiceAction;
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

$invoice = Invoice::factory()->createQuietly();

$action = TestAction::make(SendInvoiceAction::class)->table($invoice);

livewire(EditInvoice::class)
    ->assertActionExists($action)
    ->assertActionVisible($action);
</code-snippet>
@endverbatim

## Multiple Actions Naming Convention

When multiple actions exist in a single test, name variables descriptively—but only if used more than once:

@verbatim
<code-snippet name="Multiple action variables" lang="php">
use App\Filament\Resources\Invoices\Actions\EditInvoiceAction;
use App\Filament\Resources\Invoices\Actions\DeleteInvoiceAction;
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

$invoice = Invoice::factory()->createQuietly();

$editAction = TestAction::make(EditInvoiceAction::class)->table($invoice);

livewire(ListInvoices::class)
    // Used once - inline is fine
    ->assertActionExists(TestAction::make(DeleteInvoiceAction::class)->table($invoice))
    // Used multiple times - use variable
    ->assertActionExists($editAction)
    ->assertActionVisible($editAction);
</code-snippet>
@endverbatim

## Making Custom Actions Testable by Class Name

For custom action classes with a static `make()` method, add the `#[ActionName]` attribute or a `getDefaultName()` method so Filament can discover the action name without instantiation:

@verbatim
<code-snippet name="ActionName attribute for static make() pattern" lang="php">
use Filament\Actions\Action;
use Filament\Actions\ActionName;

#[ActionName('send')]
class SendInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('send')
            ->requiresConfirmation()
            ->action(fn () => /* ... */);
    }
}
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="getDefaultName for classes extending Action" lang="php">
use Filament\Actions\Action;

class SendInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'send';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation()
            ->action(fn () => /* ... */);
    }
}
</code-snippet>
@endverbatim

## Schema Extraction

- Always extract Filament `form()`, `table()`, and `infolist()` definitions into separate classes, regardless of size.
- Use a consistent naming scheme:
    - `{Model}Form` for form schemas
    - `{ModelPlural}Table` for tables
    - `{Model}Infolist` for infolists
- Place these classes in a resource-specific folder structure: `App/Filament/Resources/{Resource}/Schemas/`.
- Each class must expose a static `configure()` method.
- Call the `configure()` method from the resource to apply the schema or table.
- Do not use a required base class or interface, so `configure()` can accept custom parameters for flexible reuse.

## Filament Enum Badges

- When an enum implements `HasLabel`, `HasColor`, and `HasIcon`, use `->badge()` only on `TextColumn`/`TextEntry`. Filament auto-resolves label, color, and icon from the enum contracts when the model column is cast to the enum. Do not add manual `formatStateUsing()`, `color()`, or `icon()` closures — they are redundant and will drift from the enum's source of truth.

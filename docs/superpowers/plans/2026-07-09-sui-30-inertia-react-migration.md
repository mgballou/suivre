# SUI-30 — Migrate the user-facing app to Inertia + React + shadcn/ui

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Livewire 4 + Flux user-facing layer with Inertia + React 19 + TypeScript + shadcn/ui, preserving every existing screen and behaviour exactly.

**Architecture:** The app was scaffolded from Laravel's **Livewire** starter kit; the **React** starter kit is its sibling and shares the same Fortify backend, the same route names (`profile.edit`, `security.edit`, `appearance.edit`, `well-known.passkeys`) and the same `App\Concerns\{Profile,Password}ValidationRules` traits. This migration is therefore *delete and adopt*, not *port*. Server-side write endpoints that currently live inside Livewire single-file components move into the kit's `Settings\{Profile,Security}Controller` + form requests. Filament stays on `/admin`, on Livewire, untouched.

**Tech Stack:** Laravel 13, Fortify 1.37, Inertia 3 (`inertiajs/inertia-laravel`), Wayfinder (`laravel/wayfinder`), React 19, TypeScript 5.7, shadcn/ui (new-york, neutral), Tailwind 4, Vite 8, Pest 4 / PHPUnit-style classes, PHPStan level 9.

**Reference checkout:** the official kit is cloned at
`~/scratch/react-starter-kit`, pinned at commit **`a01c2cb`** (2026-06-30). Every "copy from the kit" instruction below means: copy from that checkout at that commit. Referred to hereafter as **`$KIT`**.

---

## Global Constraints

- **Herd only.** `herd php artisan …`, `herd composer …`. Never bare `php`/`composer`.
- **Worktree.** All work happens in `~/projects/sui-30-migrate-user-facing-app-to-inertia-react-shadcnui`. Serve at `https://sui-30.suivre.test`.
- **No product surface changes.** If a screenshot of `/settings/security` looks different, the ticket overreached. Font stays **Instrument Sans** — the spec's Inter is a token change belonging to Ticket B (SUI-31).
- **Keep `livewire/livewire` and `livewire/blaze`.** Filament needs them. Only `livewire/flux` is removed.
- **`/admin` is untouched.** `AdminPanelProvider` enumerates its middleware explicitly and never references the `web` group, so `HandleInertiaRequests` cannot reach it. Do not modify anything under `app/Filament` or `app/Providers/Filament`.
- **House PHP style on all adopted code.** The kit ships none of it. Every adopted PHP file gains `declare(strict_types=1);`, explicit return types, typed closure params, imported FQCNs, and `config()->string(...)` / `config()->boolean(...)` instead of bare `config()`.
- **PHPStan level 9, no baseline.** Fix causes, never suppress.
- **`herd composer check`** = Pint + PHPStan + Pest, and by Task 6 also `wayfinder:generate` + `tsc --noEmit`. Keep green.
- **Run `npm run build` before Pest whenever a new Inertia page is added.** `app.blade.php` code-splits per page via `@vite([… "resources/js/pages/{$page['component']}.tsx"])`, so a page absent from the Vite manifest makes the route 500 with `ViteException: Unable to locate file in Vite manifest`. This bites in Tasks 2, 3 and 4.
- **Tests mirror source paths, PHPUnit-style classes** (matching the existing suite), `declare(strict_types=1)`, `namespace Tests\Feature\…`.
- **Commits:** never add a `Co-Authored-By: Claude` trailer.

## Decisions taken before planning

1. **Business logic placement.** Adopt the kit's controllers as-is (hardened to house style). Extract **no** Actions in this ticket. Rationale: `app/Filament/Resources` is empty, so nothing has a second call site; `destroy`'s apparent complexity is HTTP session housekeeping (`Auth::logout()`, `session()->invalidate()`, `regenerateToken()`), not domain. The one genuine domain rule — *changing your email resets verification* — is the first extraction candidate when a Filament `UserResource` lands. Record this in the plan, not in code comments.
2. **Wayfinder: adopt.** Kit pages import `@/routes/profile` and `@/actions/App/Http/Controllers/Settings/ProfileController`. Generated dirs are gitignored, so `wayfinder:generate` must precede `tsc`.
3. **Quality gate: `tsc` only.** No ESLint/Prettier — Pint governs PHP and there is no JS logic worth linting until Ticket B.
4. **Timezone UI: shadcn `Select`.** All 419 `timezone_identifiers_list()` options, matching today's Flux `<select>`. Radix renders a hidden native `<select>` when `name` is set, so it submits inside Inertia's `<Form>` with no `useState`.

## File Structure

**Created (authored by us):**
| Path | Responsibility |
|---|---|
| `app/Http/Middleware/HandleInertiaRequests.php` | Root view + shared props |
| `app/Http/Middleware/HandleAppearance.php` | Shares `appearance` cookie into the root view |
| `app/Http/Controllers/Settings/ProfileController.php` | `edit` / `update` / `destroy` |
| `app/Http/Controllers/Settings/SecurityController.php` | `edit` / `update` (password) |
| `app/Http/Requests/Settings/ProfileUpdateRequest.php` | name + email + **timezone** |
| `app/Http/Requests/Settings/ProfileDeleteRequest.php` | current password |
| `app/Http/Requests/Settings/PasswordUpdateRequest.php` | current password + new password |
| `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php` | `InteractsWithTwoFactorState` |
| `resources/views/app.blade.php` | Inertia root template |
| `tests/Feature/Filament/AdminPanelTest.php` | Guards the `/admin` exit criterion |

**Copied from `$KIT` verbatim, then hardened:** `resources/js/**` (`app.tsx`, `components/`, `components/ui/`, `hooks/`, `layouts/`, `lib/`, `pages/`, `types/`), `resources/css/app.css`, `tsconfig.json`, `components.json`, `vite.config.ts`.

**Modified:** `composer.json`, `package.json`, `.gitignore`, `bootstrap/app.php`, `routes/web.php`, `routes/settings.php`, `app/Providers/FortifyServiceProvider.php`, `.ai/guidelines/architecture.blade.php`, `CLAUDE.md` (regenerated), `tests/Feature/{DashboardTest,Auth/*,Settings/*}`.

**Deleted:** `resources/views/{pages,layouts,flux,components,partials}/**`, `resources/views/{welcome,dashboard}.blade.php`, `resources/js/{app.js,passkeys.js}`, `.claude/skills/{fluxui-development,livewire-development}/`, and the `livewire/flux` composer dependency.

**Untouched:** `app/Services/**`, `app/Models/**`, `app/Filament/**`, `app/Providers/Filament/**`, `config/fortify.php` (its `features` array and `home => '/dashboard'` already match the kit; `home` is repointed to `/calendar` in Ticket B).

---

## Task 1: Inertia + React toolchain, root view, middleware

Brings the app up on Inertia with `/` and `/dashboard` rendering React, while the Livewire auth/settings pages still work. Nothing is deleted yet.

**Files:**
- Modify: `composer.json`, `package.json`, `.gitignore`, `bootstrap/app.php`, `routes/web.php`, `vite.config.ts` (replacing `vite.config.js`)
- Create: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Middleware/HandleAppearance.php`, `resources/views/app.blade.php`, `tsconfig.json`, `components.json`
- Copy from `$KIT`: `resources/js/{app.tsx,components,hooks,layouts,lib,types}`, `resources/js/pages/{welcome.tsx,dashboard.tsx}`, `resources/css/app.css`
- Delete: `vite.config.js`
- Test: `tests/Feature/DashboardTest.php`

> **Asset ordering constraint.** The surviving Blade pages still depend on the old Vite entries:
> `resources/views/partials/head.blade.php` `@vite`s `resources/js/app.js`, and
> `components/passkey-{verify,registration}.blade.php` each `@vite` `resources/js/passkeys.js`.
> Removing either entry before its last Blade consumer is deleted throws
> `ViteException: Unable to locate file in Vite manifest`. So `app.js` and `passkeys.js` stay in
> the Vite input through this task, and `resources/css/app.css` keeps its Flux import.
> They are retired in Tasks 2, 4 and 5 as their consumers disappear.

**Interfaces:**
- Produces: root view `app`; shared Inertia props `name: string`, `auth.user: User|null`, `sidebarOpen: bool`. Path alias `@/*` → `./resources/js/*`. Inertia components resolved by name from `resources/js/pages/<name>.tsx`.

- [ ] **Step 1: Write the failing test**

Replace `tests/Feature/DashboardTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    }

    public function test_the_welcome_page_renders_an_inertia_component(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('welcome'));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `herd php artisan test --compact --filter=DashboardTest`
Expected: FAIL — `Class "Inertia\Testing\AssertableInertia" not found`.

- [ ] **Step 3: Install the server-side dependencies**

```bash
herd composer require inertiajs/inertia-laravel:^3.0 laravel/wayfinder:^0.1.14
```

- [ ] **Step 4: Install the client-side dependencies**

```bash
npm install \
  @inertiajs/react@^3.0.0 @inertiajs/vite@^3.0.0 \
  @vitejs/plugin-react@^5.2.0 babel-plugin-react-compiler@^1.0.0 \
  react@^19.2.0 react-dom@^19.2.0 \
  @radix-ui/react-avatar@^1.1.3 @radix-ui/react-checkbox@^1.1.4 \
  @radix-ui/react-collapsible@^1.1.3 @radix-ui/react-dialog@^1.1.6 \
  @radix-ui/react-dropdown-menu@^2.1.6 @radix-ui/react-label@^2.1.2 \
  @radix-ui/react-navigation-menu@^1.2.5 @radix-ui/react-select@^2.1.6 \
  @radix-ui/react-separator@^1.1.2 @radix-ui/react-slot@^1.2.3 \
  @radix-ui/react-toggle@^1.1.2 @radix-ui/react-toggle-group@^1.1.2 \
  @radix-ui/react-tooltip@^1.1.8 \
  class-variance-authority@^0.7.1 clsx@^2.1.1 tailwind-merge@^3.0.1 \
  tw-animate-css@^1.4.0 lucide-react@^0.475.0 input-otp@^1.4.2 sonner@^2.0.0

npm install -D @laravel/vite-plugin-wayfinder@^0.1.3 \
  typescript@^5.7.2 @types/react@^19.2.0 @types/react-dom@^19.2.0 @types/node@^22.13.5
```

Note `typescript`, `@types/*` sit in `dependencies` in the kit; `devDependencies` is correct here and `tsc` still resolves.

- [ ] **Step 5: Copy the build + TS config**

```bash
KIT=~/scratch/react-starter-kit
cp "$KIT/tsconfig.json" tsconfig.json
cp "$KIT/components.json" components.json
rm vite.config.js
```

Create `vite.config.ts` — the kit's, except it keeps our `server` block and **temporarily retains the
`app.js` and `passkeys.js` entries** that the surviving Blade pages still `@vite`:

```ts
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                // Retired in Task 5 (app.js) and Task 4 (passkeys.js), once the
                // last Blade page that @vite's them is deleted.
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 6: Copy the React source tree**

Leave `resources/js/app.js` in place — `partials/head.blade.php` still `@vite`s it until Task 5.

```bash
KIT=~/scratch/react-starter-kit
cp "$KIT/resources/js/app.tsx" resources/js/app.tsx
cp -R "$KIT/resources/js/components" resources/js/components
cp -R "$KIT/resources/js/hooks"      resources/js/hooks
cp -R "$KIT/resources/js/layouts"    resources/js/layouts
cp -R "$KIT/resources/js/lib"        resources/js/lib
cp -R "$KIT/resources/js/types"      resources/js/types
mkdir -p resources/js/pages
cp "$KIT/resources/js/pages/welcome.tsx"   resources/js/pages/welcome.tsx
cp "$KIT/resources/js/pages/dashboard.tsx" resources/js/pages/dashboard.tsx
cp "$KIT/resources/css/app.css" resources/css/app.css
```

Then re-add the Flux stylesheet import to `resources/css/app.css`, directly under
`@import 'tailwindcss';`, so the surviving Livewire pages stay styled until Task 5 removes it:

```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';
```

Strip every `/* @chisel-* */` and `/* @end-chisel-* */` marker comment from the copied files — they are scaffolding directives for `laravel/chisel`, not code. Keep the code between them (registration, email verification, 2FA, passkeys and password confirmation are all enabled here).

Add `timezone` to the `User` type in `resources/js/types/index.d.ts`, immediately after `email: string;`:

```ts
    timezone: string;
```

- [ ] **Step 7: Add the Inertia root view**

Create `resources/views/app.blade.php` by copying `$KIT/resources/views/app.blade.php` verbatim. It supplies the pre-paint dark-mode script, `@fonts`, `@viteReactRefresh` and the per-page code-split `@vite([... "resources/js/pages/{$page['component']}.tsx"])`.

- [ ] **Step 8: Add the two middleware, hardened for PHPStan level 9**

`app/Http/Middleware/HandleAppearance.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
```

`app/Http/Middleware/HandleInertiaRequests.php` — note `config()->string(...)` and the explicit `sidebarOpen` cast, both required at level 9:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config()->string('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
```

- [ ] **Step 9: Register the middleware**

`bootstrap/app.php` — replace the empty `withMiddleware` closure:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
```

Add the imports `App\Http\Middleware\HandleAppearance`, `App\Http\Middleware\HandleInertiaRequests`, `Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets`.

- [ ] **Step 10: Point `/` and `/dashboard` at Inertia**

`routes/web.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__ . '/settings.php';
```

- [ ] **Step 11: Ignore Wayfinder's generated output**

Append to `.gitignore`:

```
/resources/js/actions
/resources/js/routes
/resources/js/wayfinder
```

- [ ] **Step 12: Generate routes and build**

```bash
herd php artisan wayfinder:generate --with-form
npm run build
```

`--with-form` emits the `.form()` variants the kit's pages call (`ProfileController.update.form()`). It writes `resources/js/{wayfinder,actions,routes}`, all three gitignored in Step 11.

- [ ] **Step 13: Run the test and watch it pass**

Run: `herd php artisan test --compact --filter=DashboardTest`
Expected: PASS, 3 tests.

- [ ] **Step 14: Confirm Livewire settings pages still render**

Run: `herd php artisan test --compact --filter='ProfileUpdateTest|SecurityTest'`
Expected: PASS. Inertia and Livewire coexist at this point; nothing is deleted yet.

- [ ] **Step 15: Commit**

```bash
git add -A
git commit -m "Install Inertia, Wayfinder and the React toolchain"
```

---

## Task 2: Move Fortify's auth views onto Inertia

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php:51-57`
- Copy from `$KIT`: `resources/js/pages/auth/*.tsx`
- Delete: `resources/views/pages/auth/**`, `resources/views/components/passkey-verify.blade.php`, `resources/views/components/auth-{header,session-status}.blade.php`
- Test: `tests/Feature/Auth/{AuthenticationTest,RegistrationTest,PasswordResetTest,PasswordConfirmationTest,EmailVerificationTest,TwoFactorChallengeTest}.php`

> `passkey-verify.blade.php` is consumed only by `pages/auth/{login,confirm-password}.blade.php`, both deleted here, so it goes now. **`resources/js/passkeys.js` and `passkey-registration.blade.php` must stay** — `pages/settings/⚡security.blade.php:305` still renders `<x-passkey-registration />` until Task 4.

**Interfaces:**
- Consumes: `resources/js/app.tsx`'s layout resolver — any component named `auth/*` is wrapped in `AuthLayout`.
- Produces: Inertia components `auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password`, `auth/verify-email`, `auth/confirm-password`, `auth/two-factor-challenge`. Props: `login` → `canResetPassword: bool`, `status: ?string`; `reset-password` → `email`, `token`, `passwordRules`; `register` → `passwordRules`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Auth/AuthenticationTest.php` — replace `test_login_screen_can_be_rendered`:

```php
    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/login')
                ->where('canResetPassword', true)
            );
    }
```

Import `Inertia\Testing\AssertableInertia as Assert`.

- [ ] **Step 2: Run it and watch it fail**

Run: `herd php artisan test --compact --filter=test_login_screen_can_be_rendered`
Expected: FAIL — the response is a Livewire-rendered Blade page, not an Inertia response.

- [ ] **Step 3: Copy the auth pages**

```bash
KIT=~/scratch/react-starter-kit
cp -R "$KIT/resources/js/pages/auth" resources/js/pages/auth
```

Strip the `@chisel` marker comments. The passkey and 2FA React components (`passkey-register.tsx`, `passkey-verify.tsx`, `two-factor-setup-modal.tsx`, `two-factor-recovery-codes.tsx`, `manage-passkeys.tsx`, `manage-two-factor.tsx`) already arrived with `resources/js/components` in Task 1.

- [ ] **Step 4: Repoint Fortify's view responses**

`app/Providers/FortifyServiceProvider.php` — replace `configureViews()`:

```php
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }
```

Add imports: `Illuminate\Validation\Rules\Password`, `Inertia\Inertia`, `Laravel\Fortify\Features`. `Illuminate\Http\Request` is already imported.

- [ ] **Step 5: Delete the Livewire auth pages**

```bash
rm -rf resources/views/pages/auth
rm -f resources/views/components/passkey-verify.blade.php \
      resources/views/components/auth-header.blade.php \
      resources/views/components/auth-session-status.blade.php
```

The React auth pages import `@laravel/passkeys` directly, so `passkey-verify.blade.php` has no consumer left. Do **not** delete `resources/js/passkeys.js` or `passkey-registration.blade.php` yet — `⚡security.blade.php` still renders the latter, which `@vite`s the former. Both go in Task 4.

Confirm nothing dangles:

```bash
grep -rn "x-passkey-verify\|auth-header\|auth-session-status" resources/ || echo "clean"
```
Expected: `clean`.

- [ ] **Step 6: Update the remaining auth tests**

In each of `RegistrationTest`, `PasswordResetTest`, `PasswordConfirmationTest`, `EmailVerificationTest`, `TwoFactorChallengeTest`, replace any assertion that a screen "can be rendered" with an `assertInertia` component check. Example, `RegistrationTest`:

```php
    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
    }
```

Component names: `auth/register`, `auth/forgot-password`, `auth/reset-password`, `auth/confirm-password`, `auth/verify-email`, `auth/two-factor-challenge`. Leave every POST/redirect assertion exactly as it is — Fortify's write endpoints are unchanged.

- [ ] **Step 7: Run the auth suite**

Run: `herd php artisan test --compact --filter='Tests\\Feature\\Auth'`
Expected: PASS, all auth tests.

- [ ] **Step 8: Verify by hand**

```bash
npm run build
```
Visit `https://sui-30.suivre.test/login` and `https://sui-30.suivre.test/register`. Both render React. Log in with a seeded user; you land on `/dashboard`.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "Move Fortify auth views onto Inertia + React"
```

---

## Task 3: Profile settings, with timezone preserved

The kit has no timezone field. SUI-1 added one and it must survive.

**Files:**
- Create: `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Requests/Settings/ProfileUpdateRequest.php`, `app/Http/Requests/Settings/ProfileDeleteRequest.php`
- Copy from `$KIT`, then modify: `resources/js/pages/settings/profile.tsx`
- Modify: `routes/settings.php`
- Delete: `resources/views/pages/settings/⚡profile.blade.php`, `⚡delete-user-form.blade.php`, `⚡delete-user-modal.blade.php`
- Test: `tests/Feature/Settings/ProfileUpdateTest.php`

**Interfaces:**
- Consumes: `App\Concerns\ProfileValidationRules::{profileRules,timezoneRules}` (already present, unchanged).
- Produces: routes `profile.edit` (GET), `profile.update` (PATCH), `profile.destroy` (DELETE). Inertia component `settings/profile` with props `mustVerifyEmail: bool`, `status: ?string`, `timezones: array<int, string>`.

- [ ] **Step 1: Write the failing test**

Replace `tests/Feature/Settings/ProfileUpdateTest.php` entirely. Note the timezone cases carried over from the Livewire original:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->has('timezones')
            );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'timezone' => $user->timezone,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_timezone_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => 'America/New_York',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('America/New_York', $user->refresh()->timezone);
    }

    public function test_timezone_must_be_a_recognised_identifier(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => 'Mars/Olympus_Mons',
            ])
            ->assertSessionHasErrors('timezone');

        $this->assertSame('UTC', $user->refresh()->timezone);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'timezone' => $user->timezone,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `herd php artisan test --compact --filter=ProfileUpdateTest`
Expected: FAIL — `Route [profile.update] not defined`.

- [ ] **Step 3: Write the form requests**

`app/Http/Requests/Settings/ProfileUpdateRequest.php` — the timezone rule is the delta from the kit. `$this->user()` is typed `?Authenticatable`, so narrow it for PHPStan:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            ...$this->profileRules($user->id),
            'timezone' => $this->timezoneRules(),
        ];
    }
}
```

`app/Http/Requests/Settings/ProfileDeleteRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Settings/ProfileController.php`. `timezones` feeds the Select; `destroy` keeps its session housekeeping inline (see "Decisions taken", item 1):

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

- [ ] **Step 5: Wire the routes**

`routes/settings.php` — replace the profile block (leave the `.well-known` route and the security/appearance routes for Task 4):

```php
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
```

Import `App\Http\Controllers\Settings\ProfileController`.

- [ ] **Step 6: Copy the page and add the timezone Select**

```bash
KIT=~/scratch/react-starter-kit
mkdir -p resources/js/pages/settings
cp "$KIT/resources/js/pages/settings/profile.tsx" resources/js/pages/settings/profile.tsx
```

Strip the `@chisel` markers. Change the component signature to accept `timezones`:

```tsx
export default function Profile({
    mustVerifyEmail,
    status,
    timezones,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    timezones: string[];
}) {
```

Add these imports:

```tsx
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
```

Insert this block inside the `<Form>` render prop, after the email field's `<InputError />` and before the email-verification notice. Radix emits a hidden native `<select name="timezone">` because `name` is set, so it submits with no controlled state:

```tsx
<div className="grid gap-2">
    <Label htmlFor="timezone">Timezone</Label>

    <Select name="timezone" defaultValue={auth.user.timezone}>
        <SelectTrigger id="timezone" className="mt-1 block w-full">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            {timezones.map((timezone) => (
                <SelectItem key={timezone} value={timezone}>
                    {timezone}
                </SelectItem>
            ))}
        </SelectContent>
    </Select>

    <p className="text-sm text-muted-foreground">
        Your day starts at midnight in this timezone.
    </p>

    <InputError className="mt-2" message={errors.timezone} />
</div>
```

- [ ] **Step 7: Delete the Livewire profile pages**

```bash
rm -f "resources/views/pages/settings/⚡profile.blade.php" \
      "resources/views/pages/settings/⚡delete-user-form.blade.php" \
      "resources/views/pages/settings/⚡delete-user-modal.blade.php"
```

- [ ] **Step 8: Run the test and watch it pass**

```bash
herd php artisan wayfinder:generate --with-form
herd php artisan test --compact --filter=ProfileUpdateTest
```
Expected: PASS, 7 tests.

- [ ] **Step 9: Verify by hand**

`npm run build`, then visit `https://sui-30.suivre.test/settings/profile`. The timezone select lists 419 entries, defaults to the user's timezone, and saves. Deleting the account still asks for the password.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "Move profile settings onto Inertia, preserving the timezone field"
```

---

## Task 4: Security and appearance settings

**Files:**
- Create: `app/Http/Controllers/Settings/SecurityController.php`, `app/Http/Requests/Settings/PasswordUpdateRequest.php`, `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`
- Copy from `$KIT`: `resources/js/pages/settings/{security.tsx,appearance.tsx}`
- Modify: `routes/settings.php`, `vite.config.ts` (drop the `passkeys.js` entry)
- Delete: `resources/views/pages/settings/⚡security.blade.php`, `⚡appearance.blade.php`, `⚡two-factor-setup-modal.blade.php`, `two-factor/⚡recovery-codes.blade.php`, `resources/views/pages/settings/layout.blade.php`, `resources/views/partials/settings-heading.blade.php`, `resources/views/components/passkey-registration.blade.php`, `resources/js/passkeys.js`
- Test: `tests/Feature/Settings/SecurityTest.php`

**Interfaces:**
- Consumes: `App\Concerns\PasswordValidationRules::{currentPasswordRules,passwordRules}`; `Laravel\Fortify\InteractsWithTwoFactorState::ensureStateIsValid()`.
- Produces: routes `security.edit` (GET, `RequirePassword`), `user-password.update` (PUT, `throttle:6,1`), `appearance.edit` (GET). Inertia component `settings/security` with props `canManageTwoFactor`, `canManagePasskeys`, `passkeys`, `passwordRules`, and — only when 2FA is manageable — `twoFactorEnabled`, `requiresConfirmation`.

- [ ] **Step 1: Write the failing test**

Replace `tests/Feature/Settings/SecurityTest.php`. The last test preserves the Livewire original's 2FA-abandonment case, now driven through `TwoFactorAuthenticationRequest::ensureStateIsValid()` on the GET:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);
    }

    public function test_security_settings_page_can_be_rendered(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManagePasskeys', true)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', true)
                ->where('twoFactorEnabled', false)
            );
    }

    public function test_security_settings_page_requires_password_confirmation_when_enabled(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('security.edit'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_security_settings_page_renders_without_two_factor_when_feature_is_disabled(): void
    {
        config()->set('fortify.features', []);

        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->where('canManagePasskeys', false)
                ->where('passkeys', [])
                ->where('canManageTwoFactor', false)
                ->missing('twoFactorEnabled')
                ->missing('requiresConfirmation')
            );
    }

    public function test_two_factor_authentication_disabled_when_confirmation_abandoned_between_requests(): void
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('twoFactorEnabled', false));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('security.edit'));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

Run: `herd php artisan test --compact --filter=SecurityTest`
Expected: FAIL — `Route [user-password.update] not defined`.

- [ ] **Step 3: Write the form requests**

`app/Http/Requests/Settings/PasswordUpdateRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
```

`app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Settings/SecurityController.php`. The kit's untyped `fn ($passkey)` closure fails PHPStan level 9 — type it:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

class SecurityController extends Controller
{
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn (Passkey $passkey): array => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at?->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['password' => $request->string('password')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
```

The model is `Laravel\Passkeys\Passkey` — Fortify's `PasskeyAuthenticatable` trait merely re-exports `laravel/passkeys`'s, whose `passkeys(): HasMany` resolves `Passkeys::passkeyModel()`. Because that relation carries no generic annotation, PHPStan may not narrow the mapped collection to `Passkey`; if it reports the closure param as incompatible, add `/** @var Collection<int, Passkey> $passkeys */` over the `->get()` result rather than loosening the closure's type hint.

- [ ] **Step 5: Wire the routes**

`routes/settings.php` — extend the `['auth', 'verified']` group added in Task 3:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
```

Add imports `App\Http\Controllers\Settings\SecurityController` and `Illuminate\Auth\Middleware\RequirePassword`. Leave the existing `.well-known/passkey-endpoints` route exactly as it is — it already points at `security.edit`.

- [ ] **Step 6: Copy the pages, delete the Livewire ones**

```bash
KIT=~/scratch/react-starter-kit
cp "$KIT/resources/js/pages/settings/security.tsx"   resources/js/pages/settings/security.tsx
cp "$KIT/resources/js/pages/settings/appearance.tsx" resources/js/pages/settings/appearance.tsx

rm -f "resources/views/pages/settings/⚡security.blade.php" \
      "resources/views/pages/settings/⚡appearance.blade.php" \
      "resources/views/pages/settings/⚡two-factor-setup-modal.blade.php" \
      "resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php" \
      "resources/views/pages/settings/layout.blade.php" \
      "resources/views/partials/settings-heading.blade.php" \
      "resources/views/components/passkey-registration.blade.php" \
      "resources/js/passkeys.js"
rmdir "resources/views/pages/settings/two-factor" "resources/views/pages/settings" "resources/views/pages" 2>/dev/null || true
```

Strip the `@chisel` markers from both copied pages.

`⚡security.blade.php` was the last consumer of `<x-passkey-registration />`, which was the last consumer of `resources/js/passkeys.js`. Drop that Vite entry now, or the next `npm run build` fails on a missing input file — remove this line from `vite.config.ts`:

```ts
                'resources/js/passkeys.js',
```

Confirm nothing dangles:

```bash
grep -rn "passkeys.js\|x-passkey-registration" resources/ vite.config.ts || echo "clean"
```
Expected: `clean`. (`resources/js/app.js` remains until Task 5.)

- [ ] **Step 7: Run the test and watch it pass**

```bash
herd php artisan wayfinder:generate --with-form
herd php artisan test --compact --filter=SecurityTest
```
Expected: PASS, 6 tests.

- [ ] **Step 8: Verify by hand**

`npm run build`. Visit `/settings/security` — it should demand password confirmation, then show *Update password*, *Two-factor authentication* with an *Enable 2FA* control, and *Passkeys* with an empty state. Register a passkey and enable 2FA end to end. Visit `/settings/appearance` and toggle light/dark/system.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "Move security and appearance settings onto Inertia"
```

---

## Task 5: Remove Flux and the Blade view layer

Nothing references `resources/views/{layouts,flux,components,partials}` any more. `livewire/livewire` and `livewire/blaze` stay — Filament depends on them.

**Files:**
- Delete: `resources/views/layouts/**`, `resources/views/flux/**`, `resources/views/components/**`, `resources/views/partials/**`, `resources/views/welcome.blade.php`, `resources/views/dashboard.blade.php`, `resources/js/app.js`
- Modify: `composer.json` (drop `livewire/flux`), `vite.config.ts` (drop the `app.js` entry), `resources/css/app.css` (drop the Flux import)
- Create: `tests/Feature/Filament/AdminPanelTest.php`

**Interfaces:**
- Produces: nothing consumed downstream. `resources/views/` retains only `app.blade.php`.

- [ ] **Step 1: Write the failing test**

`/admin` is an exit criterion with no test guarding it today. Create `tests/Feature/Filament/AdminPanelTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_an_authenticated_user_can_reach_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run it and watch it pass**

Run: `herd php artisan test --compact --filter=AdminPanelTest`
Expected: PASS — this test characterises behaviour that must survive the deletion. It is the safety net, not a red test. If it fails *now*, stop: `/admin` was already broken before this task.

- [ ] **Step 3: Delete the Blade view layer**

```bash
rm -rf resources/views/layouts resources/views/flux resources/views/components resources/views/partials
rm -f  resources/views/welcome.blade.php resources/views/dashboard.blade.php resources/js/app.js
```

`partials/head.blade.php` was the last consumer of `resources/js/app.js`. Remove that Vite entry too, or `npm run build` fails on a missing input — delete this line from `vite.config.ts`:

```ts
                'resources/js/app.js',
```

The `input` array is now exactly `['resources/css/app.css', 'resources/js/app.tsx']`, matching the kit.

Confirm only `app.blade.php` remains:

```bash
find resources/views -type f
```
Expected: exactly `resources/views/app.blade.php`.

- [ ] **Step 4: Drop Flux**

Remove the Flux stylesheet import added in Task 1 Step 6 — delete this line from `resources/css/app.css`:

```css
@import '../../vendor/livewire/flux/dist/flux.css';
```

Then remove the package:

```bash
herd composer remove livewire/flux
```

Then confirm nothing references it:

```bash
grep -rni "flux" app/ config/ resources/ routes/ tests/ vite.config.ts --exclude-dir=node_modules || echo "clean"
```
Expected: `clean`. `@fluxAppearance` lived in the deleted `partials/head.blade.php`; `Flux::toast` lived in the deleted Livewire components.

Check whether `composer remove` also pulled `livewire/livewire`:

```bash
herd composer show livewire/livewire livewire/blaze
```
Both must still be installed — Filament requires them. If Composer removed `livewire/livewire` as an orphaned transitive dependency, re-require it explicitly: `herd composer require livewire/livewire:^4.1 livewire/blaze:^1.0`.

- [ ] **Step 5: Run the whole suite**

```bash
npm run build
herd composer check
```
Expected: Pint clean, PHPStan level 9 clean, all Pest tests pass. Fix any PHPStan finding at its cause — remember there is no baseline.

- [ ] **Step 6: Verify `/admin` by hand**

Visit `https://sui-30.suivre.test/admin`. The Filament panel renders, amber-themed, exactly as before.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Remove Flux and the Blade view layer; guard /admin with a test"
```

---

## Task 6: Fold `tsc` into the quality gate

**Files:**
- Modify: `package.json`, `composer.json`

**Interfaces:**
- Produces: `npm run types:check`; `herd composer check` gains `wayfinder:generate` + `types:check`.

- [ ] **Step 1: Add the npm script**

In `package.json`, add to `scripts`:

```json
"types:check": "tsc --noEmit"
```

- [ ] **Step 2: Run it and watch it fail or pass**

```bash
rm -rf resources/js/routes resources/js/actions resources/js/wayfinder
npm run types:check
```
Expected: FAIL — `Cannot find module '@/routes/profile'`. This proves the ordering constraint: Wayfinder's generated files are gitignored, so a clean checkout cannot typecheck until they are generated. That is precisely why `wayfinder:generate` must precede `types:check` in the gate.

- [ ] **Step 3: Generate, then typecheck**

```bash
herd php artisan wayfinder:generate --with-form
npm run types:check
```
Expected: PASS, no output. Fix any type errors introduced by the timezone prop in Task 3 (`timezones: string[]`) or the `timezone` field added to the `User` type in Task 1.

- [ ] **Step 4: Fold both into `composer check`**

In `composer.json`, replace the `check` script:

```json
"check": [
    "@lint",
    "@types:check",
    "@php artisan wayfinder:generate --with-form",
    "npm run types:check",
    "@php artisan test"
]
```

`@types:check` is the existing PHPStan alias; `npm run types:check` is the new `tsc` step. Leave the `test` script (used by `ci:check`) alone — CI runs `npm ci && npm run build` before it, which generates the Wayfinder files via the Vite plugin.

- [ ] **Step 5: Run the gate**

```bash
herd composer check
```
Expected: Pint clean → PHPStan clean → Wayfinder generates → `tsc` clean → Pest green.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Fold wayfinder:generate and tsc into composer check"
```

---

## Task 7: Rewrite the UI guidelines and retire the dead skills

**Files:**
- Modify: `.ai/guidelines/architecture.blade.php` (the "User-facing layer" section), `CLAUDE.md` (regenerated)
- Delete: `.claude/skills/fluxui-development/`, `.claude/skills/livewire-development/`

**Interfaces:**
- Produces: nothing consumed by code. `CLAUDE.md` is regenerated from `.ai/guidelines/**` by Laravel Boost.

- [ ] **Step 1: Rewrite the UI section**

In `.ai/guidelines/architecture.blade.php`, replace the `## User-facing layer (Livewire + Flux + PWA)` section with:

```markdown
## User-facing layer (Inertia + React + shadcn/ui + PWA)

- **Inertia 3 + React 19 + TypeScript.** Pages live in `resources/js/pages/`, resolved by
  component name (`settings/profile` → `resources/js/pages/settings/profile.tsx`). Keep state
  server-side; validate and authorize inside Actions and Form Requests exactly as an HTTP
  request would.
- **shadcn/ui** (new-york, neutral, `--radius: 0.5rem`) in `resources/js/components/ui/`,
  unmodified where possible. Product components live in `resources/js/components/suivre/`.
- **Wayfinder** generates typed route helpers into `resources/js/{routes,actions}` — gitignored,
  regenerated by `herd php artisan wayfinder:generate --with-form` and by the Vite plugin.
  Import routes from `@/routes/*`, never hard-code URL strings.
- **Tailwind v4** for layout. Match sibling components before writing custom styles.
- Controllers stay thin. Extract an Action when the logic is (1) complex enough to warrant its
  own class, (2) needed by both the Filament backstage and the user app, or (3) reused across
  call sites within one of them. Do not extract on principle alone.
- Delivered as an installable **PWA**, online-first for MVP.
- Insights/visualisations are a first-class product surface — keep them easy and central.
- **Filament 5 remains on Livewire**, on `/admin`, and is untouched by any of this. `livewire/livewire`
  and `livewire/blaze` stay installed for it.
```

Also update the `## Anti-patterns` list: replace "Business logic in a controller, Filament/Livewire component, Job, or Listener" with "Business logic in a Filament component, Job, or Listener — or in a controller once it meets the extraction criteria above."

- [ ] **Step 2: Retire the dead skills**

```bash
rm -rf .claude/skills/fluxui-development .claude/skills/livewire-development
```

`fluxui-development` is unambiguously dead — Flux is gone, and Boost does not restore it.

**Deviation from the spec, recorded during execution.** `livewire-development` cannot be retired.
`.claude/skills/**` is generated by Laravel Boost from the installed composer packages, and
`livewire/livewire` remains a direct dependency because Filament needs it. `boost:update` in Step 3
therefore restores the skill, and would do so again on every future run. Deleting it means fighting
the generator forever, for a skill that is in any case applicable to Filament (whose components *are*
Livewire components). It stays.

Boost also **adds** two skills for the new stack in Step 3 — `inertia-react-development` and
`wayfinder-development`. Keep both; they are the replacement for `fluxui-development`.

- [ ] **Step 3: Regenerate `CLAUDE.md`**

`CLAUDE.md`'s `<laravel-boost-guidelines>` block is woven from `.ai/guidelines/**`, so it must be regenerated after Step 1:

```bash
herd php artisan boost:update
git diff --stat CLAUDE.md
```

Then inspect the diff. The only change should be the `=== .ai/architecture rules ===` section picking up your rewrite. If `boost:update` also pulls unrelated upstream guidance changes, revert those hunks (`git checkout -p CLAUDE.md`) — this ticket is not the place to absorb them.

If `boost:update` leaves `CLAUDE.md` untouched, hand-edit the `=== .ai/architecture rules ===` section of the `<laravel-boost-guidelines>` block so it matches `.ai/guidelines/architecture.blade.php` verbatim.

- [ ] **Step 4: Confirm nothing else mentions the retired stack**

```bash
grep -rn "Flux\|flux" .ai/ CLAUDE.md .claude/skills/ || echo "clean"
```
Expected: `clean`.

- [ ] **Step 5: Run the full gate one last time**

```bash
herd composer check
```
Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Rewrite UI guidelines for Inertia + React; retire Flux and Livewire skills"
```

---

## Exit criteria (verify before opening the PR)

Drive each of these in the browser at `https://sui-30.suivre.test` — tests alone do not satisfy the ticket.

- [ ] Login, registration, email verification, password reset, password confirmation, 2FA and passkeys all work exactly as before.
- [ ] All three settings pages (`profile`, `security`, `appearance`) work; the timezone select persists a change.
- [ ] `/admin` renders the Filament panel unchanged.
- [ ] `herd composer check` is green.
- [ ] A screenshot of `/settings/security` is indistinguishable from `main`'s, modulo shadcn-vs-Flux control chrome.
- [ ] `resources/views/` contains exactly one file: `app.blade.php`.
- [ ] `herd composer show livewire/livewire livewire/blaze` both resolve; `livewire/flux` does not.

## Deliberately out of scope (Ticket B — SUI-31)

- Petrol intensity ramp, motion tokens, radius, tabular figures (spec §4).
- Swapping Instrument Sans → Inter.
- `TabBar`, desktop icon rail, `AppLayout` as an Inertia persistent layout.
- `/calendar`, `/day/{date}`, `/insights` routes; repointing `fortify.home`; retiring `dashboard`.
- `DashboardTest` → `ShellTest`.
- Vitest + React Testing Library (no component with logic exists yet).
- Extracting `UpdateUserProfile` — revisit when a Filament `UserResource` creates a second call site for the *email change resets verification* rule.

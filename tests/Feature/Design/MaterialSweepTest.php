<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The material layer is only a system while every call site uses it (D28). A
 * hand-written shadow or blur radius is what turns it back into a pile of
 * one-offs, so the rule is enforced here rather than remembered.
 *
 * Scope is authored code. `components/ui` is shadcn/ui carried unmodified on
 * purpose — its `shadow-xs` is a vendored Tailwind utility, and editing it
 * would put every future `shadcn add` in conflict with the local copy.
 */
function authoredComponents(): Finder
{
    return Finder::create()
        ->files()
        ->name('*.tsx')
        ->notName('*.test.tsx')
        ->in([
            resource_path('js/pages'),
            resource_path('js/layouts'),
            resource_path('js/components/suivre'),
        ])
        ->append(
            Finder::create()
                ->files()
                ->name('*.tsx')
                ->notName('*.test.tsx')
                ->depth(0)
                ->in(resource_path('js/components')),
        );
}

it('writes no shadow of its own outside the elevation tokens', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/\b(shadow-(?!none)|drop-shadow-)/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use .elevation-raised or .elevation-floating instead of a shadow utility.');
});

it('writes no blur of its own outside the glass token', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        if (preg_match('/\b(backdrop-blur|blur-\[|blur-(sm|md|lg|xl))\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use .glass instead of a blur utility.');
});

it('keeps the raw palette out of authored code, where the design tokens belong', function (): void {
    $offenders = [];

    foreach (authoredComponents() as $file) {
        /** @var SplFileInfo $file */
        // auth-split-layout.tsx is unrouted starter-kit scaffolding — no page
        // renders it (confirmed by grep; nothing imports AuthSplitLayout). Its
        // `bg-zinc-900` paints a brand panel that wants to stay dark
        // regardless of scheme, which the current token set has no semantic
        // equivalent for. Narrowed here rather than inventing one without a
        // design decision; revisit if the layout is ever wired up or deleted.
        if ($file->getRelativePathname() === 'auth/auth-split-layout.tsx') {
            continue;
        }

        if (preg_match('/\b(bg|text|border)-(neutral|gray|slate|zinc|stone|red|green|blue)-\d{2,3}\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], 'Use a semantic token — the palette is not the design system.');
});

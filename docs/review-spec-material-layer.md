# Serving the material-layer review

## One site serves all three branches

The branches are strictly stacked — `sui-58` is an ancestor of `sui-59`, which is an
ancestor of `sui-60` — so the `sui-60` worktree contains every change in the other two.
Review against **`https://sui-60.suivre.test`** and nothing is missed.

This is why `base_url` in `review-spec-material-layer.json` is `https://sui-60.suivre.test`
and not `https://suivre.test`. `suivre.test` serves the main worktree, which is on `main`
and carries none of this work — every screen in the spec would look unchanged there.

| Branch | Site | Database |
|---|---|---|
| `sui-58-add-the-material-layer-elevation-glass-tint-gooey` | `https://sui-58.suivre.test` | `suivre_sui_58` |
| `sui-59-rebuild-the-day-as-summary-cards-that-expand-in-place` | `https://sui-59.suivre.test` | `suivre_sui_59` |
| `sui-60-sweep-the-material-layer-across-the-remaining-surfaces` | `https://sui-60.suivre.test` | `suivre_sui_60` |

Herd already serves all three, secured, on PHP 8.4. Nothing needs registering.

## Blocker: there is no account that can reach these routes

`suivre_sui_60` holds exactly one user — `test@example.com`, role **admin**.

An admin cannot see any screen in this spec. `RequireMemberAccount` (D27) keeps an admin
out of the whole user-facing app, journal *and* `/settings`; `User::canAccessPanel()` keeps
a member out of `/admin`. The two roles reach opposite halves and both directions are
enforced. Public registration is closed, so no account can be made from the browser.
`StagingSeeder` does mint a member, but it is not called by `DatabaseSeeder` and without
`STAGING_SEED_PASSWORD` set it assigns a random 40-character password.

Create the member directly, in the `sui-60` worktree:

```bash
cd ../sui-60-sweep-the-material-layer-across-the-remaining-surfaces

herd php artisan tinker --execute 'app(\App\Services\Users\Actions\CreateUserAccount::class)(
    name: "Review", email: "review@suivre.test", password: "password",
    timezone: "America/New_York", role: \App\Enums\UserRole::Member,
);'
```

The timezone is a required argument, not a default — the journal is keyed on the user's
local day (D5), and no browser is present to report one. Set it to the timezone you will
actually be reviewing in, or the day cards file against the wrong date.

Log in at `https://sui-60.suivre.test/login` with that email and password.

## Two more steps before the routes render anything

1. **Build the assets.** The committed `public/build/manifest.json` in that worktree
   predates the branch — it has no entry for `day-sections`. `app.blade.php` code-splits
   per page, so `/day` will fail or serve stale JS until you run `npm run build` in the
   worktree.

2. **Add a condition.** `RequireTrackedCondition` redirects a member with no tracked
   conditions to `/onboarding/conditions`. Until one exists, `/calendar`, `/day` and
   `/insights` all bounce there. Adding one from that screen clears the gate.

## Making the day screen worth looking at

Three of the nine screens are `/day/2026-08-20`, and the summary cards state what is on
file. On an empty day every card reads "Not recorded" / "Nothing logged" / "None", which
tests the tone question but not the layout ones — a section with no content collapses to
almost nothing when expanded.

Before reviewing, log a check-in, rate a condition, and add a meal with two or three items
for that date through the UI itself. Leave flares empty: the day opens on the first thing
not yet on file, and flares are deliberately excluded from that, so an empty flares card is
part of what is being reviewed.

## Route notes

- `/day/2026-08-20` — today. The route is `day/{date}`; any ISO date works.
- `/calendar` — the month segment is optional and defaults to the current month.
- `/login` — log out first. Put this screen last; it ends the session.
- The bottom tab bar is the mobile navigation, so the 430×932 viewport is required for the
  `tab-bar-glass` and `tab-indicator-travel` screens. On a desktop width the bar is replaced
  by the sidebar rail and neither question can be answered.

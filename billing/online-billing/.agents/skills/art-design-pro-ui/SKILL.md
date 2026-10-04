---
name: art-design-pro-ui
description: Design, implement, or review any UI/UX change under apps/web while preserving the repository's Art Design Pro Vue visual system. Use for pages, layouts, cards, forms, tables, dialogs, navigation, responsive behavior, loading and empty states, or visual styling; do not use for backend-only work.
---

# Art Design Pro UI

Keep every frontend change visually native to the existing Art Design Pro application. Do not introduce a parallel design language.

Before editing UI code, read [references/project-map.md](references/project-map.md), inspect the target component and at least one comparable current surface, and check reusable components under `apps/web/src/components/core`.

## Visual contract

- Use Art Design Pro surfaces and utilities: `page-content`, `art-card*`, `text-g-*`, `bg-g-*`, `border-g-*`, `bg-active-color`, `bg-theme`, `text-theme`, and semantic colors such as `success`, `warning`, and `error`.
- Use `ArtSvgIcon` with the repository's Remix Icon vocabulary. Reuse existing core components and Element Plus controls before creating a component.
- Match the density, radii, spacing, typography, control sizing, and interaction patterns of sibling pages. Respect the configurable box mode and theme color.
- Keep light and dark themes working through project tokens. Avoid hard-coded white surfaces, fixed gray/slate palettes, arbitrary brand colors, and light-only borders.
- Avoid standalone gradient hero sections, oversized marketing cards, heavy custom shadows, raw emoji/letter icons, or a generic dashboard aesthetic unless the user explicitly requests that exception and it fits an established local pattern.
- Preserve responsive behavior at the same breakpoints and with the same compactness as the shell. Do not make a single page visually louder than navigation and adjacent workspaces.

## UX contract

- Preserve existing API, authorization, Reverb, route, and server-authoritative behavior unless the task explicitly changes it.
- Make status, primary action, next operational step, errors, loading, empty results, disabled states, and mobile behavior clear.
- Put actions near the record they affect. Reuse established deep-link and query contracts instead of inventing route parameters.
- Do not expose raw internal JSON, IDs, debug data, or backend implementation details when a user-facing label or reference can be shown.

## Workflow

1. Inspect the current page, a comparable Art Design Pro page, core components, and theme utilities.
2. Identify off-system styling before editing: hard-coded colors, duplicate cards, bespoke spacing, incompatible icons, or missing dark-mode tokens.
3. Implement the smallest cohesive change using existing components and tokens. Keep unrelated business logic untouched.
4. Format only changed frontend files with the repository Prettier command.
5. Run `pnpm.cmd exec vue-tsc --noEmit` for focused validation and `pnpm.cmd build` for a completed UI change.
6. When visual acceptance matters, inspect the authenticated rendered page at desktop and narrow widths. Report source/build verification separately from browser acceptance.

If a user provides a visual direction that conflicts with this skill, follow the user while adapting it to the Art Design Pro system as far as the request permits.

# Project UI map

Use these sources to learn the current design system before changing `apps/web`.

## Foundations

- `apps/web/src/assets/styles/core/app.scss`: `page-content`, `art-card*`, box-mode borders, shadows, and radii.
- `apps/web/src/assets/styles/core/tailwind.css`: shared utilities such as `art-card-header`, `flex-c`, `flex-cb`, and border helpers.
- `apps/web/src/components/core/`: reusable Art Design Pro cards, tables, layouts, icons, forms, banners, and feedback components.
- `apps/web/src/store/modules/realtime.ts`: shared Reverb and notification state; reuse it instead of creating parallel global state.

## Useful reference surfaces

- `apps/web/src/views/communication/chat/index.vue`: production communication workspace using `page-content`, gray/theme tokens, Element Plus, and shared realtime state.
- `apps/web/src/views/dashboard/`: native dashboard card composition and `art-card-header` examples.
- `apps/web/src/views/template/cards/index.vue`: supported Art card variants and semantic colors.
- `apps/web/src/components/core/layouts/art-notification/index.vue`: header notification behavior and compact notification presentation.

## Preferred vocabulary

- Surfaces: `page-content`, `art-card`, `art-card-sm`, `art-card-xs`, `bg-g-100`, `bg-g-200`.
- Text: `text-g-500` through `text-g-900` according to hierarchy.
- Borders: `border-g-200`, `border-g-300`, or shared border utilities.
- Selection and emphasis: `bg-active-color`, `bg-theme/10`, `text-theme`, `border-theme/20`.
- Semantic state: `text-success`, `text-warning`, `text-error` and their low-opacity backgrounds.
- Icons: global `ArtSvgIcon` with existing `ri:*` names.

Prefer these tokens over hard-coded Tailwind color families so theme color, box mode, and dark mode continue to work.

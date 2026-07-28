# Tapoda Corporate Identity and CSS System

**Status:** Target specification

**Purpose:** One maintainable system controls Tapoda Corporate Identity across public pages, applicant workflows, operations, email, and print.

`CI` in this document means Corporate Identity. Continuous Integration is the enforcement mechanism.

## 1. Decision

Create one deep Design System module (`DesignSystem`) with:

- One versioned design-token source.
- One set of Thai-first UI primitives.
- One set of recurring workflow patterns.
- One controlled page-template catalog.
- Generated adapters for web, legacy Blade compatibility, email, and print.
- Automated lint, accessibility, responsive, visual-regression, and bundle-budget gates.

Page modules cannot define their own colors, fonts, spacing scales, breakpoints, shadows, z-indexes, focus rings, status colors, dialogs, buttons, fields, or table behavior.

The system preserves Tapoda’s warm earth identity. Existing raw values are evidence, not automatically approved accessible tokens.

## 2. Current evidence

| Finding | Verified repository evidence | Consequence |
|---|---|---|
| Blade surface | 91 Blade files: 77 non-mail and 14 mail | Large migration surface |
| Page-local presentation | 231 inline `style` attributes across non-mail Blade | Style cannot be changed centrally |
| Embedded CSS | 43 `<style>` blocks across 38 non-mail Blade files | Cascade and responsive rules are page-local |
| Page-local interaction | 149 `<script>` tags and 53 non-mail files with page scripts/script pushes | UI state and validation differ by page |
| Public CSS payload | 468,928 bytes across the inspected public CSS files | Duplicate frameworks and template CSS |
| Framework collision | Public layout loads Bootstrap 5.1.3 and `style.css`, which embeds Bootstrap 4.3.1 | Load order controls behavior |
| Admin system | AdminLTE and Bootstrap 4 | Separate interaction and visual conventions |
| Message system | Message layout combines public CSS, AdminLTE, Bootstrap generations, and page scripts | Result/error pages inherit unrelated behavior |
| V2 form styling | `_action_styles.blade.php` is 762 lines; each step also carries local CSS/JS | Shared behavior remains shallow and leaky |
| Brand values | `#B88963`, `#B08F7E`, `#C6A592`, `#A88B80`, `#E9E4DE`, `#D6C7BB`, `#ECE2D9` recur | Recognizable identity has no semantic ownership |
| Contrast | Existing brown values measure about 2.28–3.15:1 on white; `#89766C` is about 4.31:1 | Current brand colors fail normal-text WCAG AA |
| Responsive model | `960px`, `1800px`, and `2980px` widths plus User-Agent branches and local media queries | Device and page determine presentation |
| Language/accessibility | Public/backend layouts declare `lang="en"`; public viewport disables zoom | Thai experience and reflow are not systematic |
| UI verification | Existing tests largely inspect strings/files; no complete visual/accessibility suite | CSS regressions are hard to detect |

Email inline CSS is excluded from the 231 non-mail inline-style count. Inline email styles require a generated email adapter until client support changes.

## 3. Module shape

`DesignSystem` is deep: a small interface provides large implementation leverage.

### Interface

Callers may use only:

- Semantic design tokens.
- Approved UI primitives.
- Approved workflow patterns.
- Approved page templates.
- Documented variants and state props.
- Generated email and print recipes.

Callers must not know:

- Raw palette values.
- Framework-specific class combinations.
- Focus implementation.
- Internal spacing/radius/shadow values.
- Breakpoint mechanics.
- Modal focus management.
- Table sticky-column mechanics.
- Email-client CSS workarounds.
- Legacy Bootstrap mapping.

### Internal implementation

- Reference and semantic token definitions.
- Tailwind theme generation.
- Primitive styling and behavior.
- Pattern composition.
- Accessibility mechanics.
- Responsive layout rules.
- Web/email/print adapter generation.
- Story fixtures and visual baselines.
- Legacy compatibility styles.

### Real seams and adapters

Four outputs justify the seam:

1. `WebReactAdapter` — Inertia/React and Tailwind CSS.
2. `LegacyBladeAdapter` — temporary compatibility for migrated Blade pages.
3. `EmailAdapter` — table-safe, inlined email output.
4. `PrintAdapter` — applicant, teacher, laundry, and report print rules.

Figma/Tokens Studio may consume the same source, but Figma is not a production writer.

### Deletion test

Deleting `DesignSystem` should redistribute tokens, interaction states, responsive rules, accessibility behavior, and visual tests across every page. That concentrated complexity proves depth.

## 4. One token source

Canonical location in the new repository:

```text
resources/design/
  tokens/
    reference.tokens.json
    semantic.tokens.json
    typography.tokens.json
    layout.tokens.json
    motion.tokens.json
  build/
    build-tokens.mjs
  generated/
    tokens.web.css
    tokens.tailwind.css
    tokens.ts
    tokens.email.php
    tokens.print.css
```

Use the Design Tokens Community Group JSON format. Style Dictionary or an equivalent deterministic local build generates every adapter.

### Token tiers

#### Reference tokens

Raw values without product meaning:

- Earth/sand/stone color ramps.
- Green/blue/amber/red state ramps.
- Font families and weights.
- Spacing scale.
- Radius scale.
- Shadow scale.
- Motion duration/easing.
- Breakpoint values.
- Z-index levels.

Only the token implementation may consume reference tokens directly.

#### Semantic tokens

Product meaning:

- `color.canvas`
- `color.surface.default`
- `color.surface.subtle`
- `color.text.primary`
- `color.text.muted`
- `color.text.inverse`
- `color.border.default`
- `color.border.strong`
- `color.action.primary`
- `color.action.primary-hover`
- `color.focus.ring`
- `color.status.info`
- `color.status.success`
- `color.status.warning`
- `color.status.danger`
- `color.status.neutral`

Page code consumes semantic tokens only.

#### Pattern tokens

Rare, approved aliases owned by a reusable pattern:

- `application.stepper.*`
- `data-grid.sticky-divider`
- `dialog.backdrop`
- `status-badge.*`
- `email.call-to-action.*`
- `print.table.*`

A page-specific token is rejected unless the visual rule appears in at least two approved patterns or expresses a lasting brand requirement.

### Candidate visual direction

These are starting values, not final brand approval:

| Semantic role | Candidate | Rule |
|---|---:|---|
| Primary text | `#2F2925` | Normal text on light surfaces |
| Muted text | `#655E58` | Must retain 4.5:1 at normal size |
| Canvas | `#F7F3EE` | Warm neutral page background |
| Surface | `#FFFFFF` | Forms, cards, dialogs |
| Border | `#D8CFC5` | Controls and dividers |
| Brand | `#765B49` | Structural emphasis |
| Brand strong | `#594235` | Text/active state |
| Primary action | `#176B49` | Main task action |
| Primary action strong | `#0F5136` | Hover/pressed |
| Link | `#285C8C` | Text links |
| Warning | `#8A5700` | Warning with icon/text |
| Danger | `#A32929` | Error/destructive |

Every combination must pass automated contrast checks. Status always includes Thai text and an icon; color is never the only signal.

## 5. CSS architecture

One ordered cascade:

```css
@layer reset, tokens, base, primitives, patterns, templates, utilities, legacy;
```

Rules:

- `reset`: approved modern reset only.
- `tokens`: generated custom properties only.
- `base`: body, headings, links, selection, focus defaults.
- `primitives`: Button, Field, Dialog, Alert, Table, and similar foundations.
- `patterns`: FormSection, FilterBar, DataGrid, StatusTimeline, and other recurring tasks.
- `templates`: PublicShell, ApplicationShell, WorkspaceShell, print, and other page-region layouts.
- `utilities`: Tailwind-generated utilities.
- `legacy`: temporary scoped compatibility overrides only.

Forbidden:

- Unscoped third-party framework CSS.
- `!important`, except a documented accessibility or legacy containment case.
- Raw hex/rgb/hsl values outside token source and test fixtures.
- Raw pixel spacing, radius, shadow, breakpoint, or z-index outside token source.
- Page `<style>` blocks.
- Non-email `style=""`.
- Page-specific copies of primitive or pattern CSS.
- User-Agent presentation branches.
- `maximum-scale=1`.
- CSS-generated data labels that replace table semantics.

### Legacy containment

During migration:

```html
<div data-ui-generation="legacy">...</div>
<div data-ui-generation="next">...</div>
```

- Bootstrap/AdminLTE can exist only below `data-ui-generation="legacy"`.
- New styles never target legacy classes.
- Legacy rules live in the last cascade layer.
- A route cannot mix legacy and next controls inside one task surface.
- The compatibility adapter is deleted after the final legacy route passes parity.

## 6. Typography and content

- Self-host `Sarabun` as the primary Thai/Latin family.
- Use system fallbacks when font loading fails.
- Body text: minimum 16px, Thai line-height about 1.6.
- Operational metadata: minimum 14px.
- Approved type steps: 14, 16, 18, 22, 28, 36.
- Avoid uppercase transformations for Thai.
- Use weight and spacing for hierarchy.
- Mark English fragments with `lang="en"`.
- All page roots use `lang="th"`.
- Email and print adapters use approved fallback stacks.

Kanit may be added as an approved display token only if the brand owner supplies a clear use case. It cannot become a second default body system.

## 7. Layout and responsive system

Layout-pressure breakpoints:

- `30rem`
- `48rem`
- `64rem`
- `80rem`

Page templates:

| Template | Intended pages | Width behavior |
|---|---|---|
| Public editorial | Guidance, qualifications, about | Readable measure; long Thai prose |
| Discovery | Course finder/detail | Fluid cards to desktop grid |
| Account | Sign-in, registration, OTP, recovery, consent | Narrow task column |
| Guided task | Initial/post-invitation application | Step shell plus focused content |
| Member task center | Next action, active applications, history, settings | Responsive task cards |
| Operations queue | Review/course/user/check-in queues | Dense grid with filters |
| Operations record | Applicant dossier/course workspace | Summary plus task panels |
| Report | Applicant/teacher/laundry | Named horizontal scroll region |
| Kiosk/workstation | Check-in | Scanner/keyboard first |
| System state | Empty/error/session/maintenance/success | Stable explanation and next action |
| Communication | Email and print | Adapter-specific constraints |

Requirements:

- One DOM and one information hierarchy across sizes.
- 320 CSS px and 200% zoom retain content and actions.
- Touch targets are at least 44×44 CSS px.
- Data grids may scroll horizontally inside a labeled region.
- Sticky columns use separate borders and pseudo-element dividers; no collapsed-border dependency.
- Sticky actions account for safe-area insets.
- Motion is 120–200ms and respects `prefers-reduced-motion`.

## 8. UI primitive catalog

Each primitive owns markup, variants, states, responsive behavior, keyboard behavior, accessible name/description, and tests.

### Foundation

- Text
- Heading
- Icon
- Divider
- Surface
- Stack
- Cluster
- Grid
- Container

### Actions

- Button: primary, secondary, quiet, destructive, link
- IconButton
- ActionLink
- BulkAction

Every action supports idle, hover, focus, pressed, disabled-with-reason, loading, success receipt, and safe retry.

### Inputs

- TextField
- PasswordField
- TextArea
- Select
- SearchableSelect
- RadioGroup
- Checkbox
- DateField
- OtpField
- FileField
- AddressFieldGroup
- RepeatableFieldGroup

Every field has visible label, optional help, required/optional state, error association, disabled/read-only distinction, and server-error rendering.

### Feedback

- FieldError
- ErrorSummary
- Alert
- Toast
- SaveStatus
- Progress
- Skeleton
- EmptyState
- ResultReceipt
- ErrorPage

### Navigation

- PublicHeader
- AccountMenu
- OperationsNavigation
- Breadcrumbs
- Tabs
- ApplicationStepper
- Pagination

### Data and overlays

- SemanticTable
- DataGrid
- FilterBar
- ColumnChooser
- StatusBadge
- DefinitionList
- StatusTimeline
- Dialog
- ConfirmationDialog
- Drawer

One dialog implementation owns focus trap, initial focus, Escape behavior, close rules, return focus, labeling, and scroll lock.

## 9. Workflow patterns

- CourseFinder
- CourseAvailability
- CourseAction
- ApplicationShell
- FormSection
- ConditionalField
- RepeatableAnswerGroup
- SaveDraftStatus
- SubmissionReview
- TaskCard
- CustomerCourseTimeline
- ApplicantDossier
- ReviewPanel
- DecisionPanel
- BulkActionBar
- CheckInSearch
- IdentityReaderStatus
- ParticipantMatch
- CheckInReceipt
- ExportJobStatus
- DocumentLink
- EmailShell

Patterns may compose primitives. Pages may compose patterns. Pages cannot fork pattern styling.

## 10. State contract

Every data or action pattern declares:

- Idle.
- Loading.
- Empty.
- Validation failure.
- Authorization denied.
- Session expired.
- Network failure.
- Server failure.
- Partial success where applicable.
- Success receipt.
- Retry/idempotency behavior.

Required HTTP state pages:

- 401/sign-in required.
- 403/forbidden.
- 404/not found.
- 419/session expired.
- 422/validation failed.
- 429/rate limited with retry time.
- 500/unavailable.
- Offline/reconnect.
- Maintenance.

No new screen may rely only on SweetAlert, transient toast, spinner overlay, color, or raw HTML message content.

## 11. Email and print adapters

### Email

- Generated token subset mapped to email-safe inline declarations.
- Shared presentation table shell, header, body, CTA, callout, checklist, footer, and plaintext URL.
- `role="presentation"` for layout tables.
- Meaningful action text, course, deadline, consequence, support route.
- Thai-first typography with safe fallbacks.
- Plaintext alternative.
- Snapshot checks across approved Gmail, Outlook, and mobile profiles.

### Print

- Generated ink, spacing, typography, table, page-break, and visibility tokens.
- No screen navigation/action styles.
- Applicant, teacher, laundry, and report-specific patterns compose the same print primitives.
- Preserve maximum-ten teacher print and approved grouping until retirement.
- Golden PDF/print comparison before cutover.

## 12. File ownership

Suggested new-repository shape:

```text
resources/
  design/
    tokens/
    generated/
  css/
    app.css
    legacy.css
    email.css
    print.css
  js/
    design-system/
      primitives/
      patterns/
      templates/
      stories/
```

Ownership:

- Brand owner approves reference palette, logo, and identity rules.
- Product design owns semantic token intent and visual acceptance.
- Design-system maintainers own primitives, patterns, adapters, and migration compatibility.
- Feature-module teams compose only approved interfaces.
- Accessibility owner approves exceptions.
- No page author changes a global token to fix one page.

## 13. Continuous Integration enforcement

Required checks:

1. Token schema and reference validation.
2. Generated-output drift check.
3. Stylelint.
4. ESLint/TypeScript checks for UI code.
5. No-hardcoded-color/spacing/z-index rule.
6. No non-email inline-style rule.
7. No page `<style>` rule.
8. No new Bootstrap/AdminLTE imports.
9. No uncontrolled primitive/pattern duplication.
10. Primitive and pattern interface tests.
11. Automated axe tests.
12. Keyboard interaction tests.
13. Visual regression at 320, 375, 768, 1024, and 1440 px.
14. 200% zoom/reflow checks for critical flows.
15. Print snapshot/golden tests.
16. Email render snapshots.
17. CSS/JS bundle budget.
18. Dead-token and unused-variant report.

Pull requests changing tokens must include:

- Before/after visual evidence.
- Contrast results.
- Affected primitive/pattern/page-template list.
- Migration note.
- Brand/product/accessibility approvals required by ownership.

## 14. Versioning

- Semantic tokens and UI interfaces use versioned releases.
- Additive variants are minor changes.
- Renamed/removed token or variant is breaking.
- A deprecation includes replacement and removal milestone.
- Generated outputs include source version and checksum.
- Page inventory records the design-system version used during migration.
- Visual baselines are tied to the same version.

## 15. Migration plan

### CI-0 — freeze

- Capture screenshots for every page family and state.
- Record current palette, typography, assets, breakpoints, dialogs, tables, and status labels.
- Approve official Thai/English name, logo, and retained brand traits.

### CI-1 — token foundation

- Create token source and generators.
- Validate candidate palette and contrast.
- Self-host approved fonts.
- Establish cascade layers and build gates.

### CI-2 — primitives and states

- Build actions, fields, feedback, navigation, tables, dialogs, and system states.
- Verify keyboard, screen reader, zoom, reduced motion, and Thai content.

### CI-3 — public/account templates

- Migrate public shell, course discovery, account, OTP, recovery, and consent.
- Remove User-Agent presentation branching.

### CI-4 — guided/member templates

- Migrate application variants and member task center.
- Replace `_action_styles.blade.php`, page CSS, and duplicated address/validation behavior.

### CI-5 — operations templates

- Migrate review, course workspace, check-in, user administration, and bulk actions.
- Preserve dense workflows without retaining AdminLTE.

### CI-6 — report/email/print

- Migrate sticky report tables using the approved grid pattern.
- Generate email and print adapters.
- Sign golden output parity.

### CI-7 — retirement

- Remove Bootstrap 5 + Bootstrap 4 collision.
- Remove AdminLTE.
- Remove page-local CSS/JS and legacy adapter after route parity.
- Enforce zero new legacy violations.

## 16. Acceptance criteria

- One token change updates every approved web, email, and print use through generated adapters.
- No production page owns raw brand values.
- No new page `<style>` block or non-email inline style exists.
- Only one web CSS system executes on a migrated route.
- Public, applicant, member, operations, email, and print surfaces use approved semantic roles.
- Every primitive and workflow pattern exposes all required states.
- All critical pages pass WCAG 2.2 AA, keyboard, Thai language, 320px, and 200% zoom checks.
- Status is never color-only.
- Visual regression covers every page template and critical state.
- Existing course, application, review, check-in, report, email, and print behavior passes parity before legacy CSS retirement.

## 17. Product decisions required

- Official logo and asset source.
- Official Thai and English organization names.
- Whether Kanit remains an approved display-only font.
- Brand palette changes allowed to achieve WCAG AA.
- Required light/dark themes; default recommendation is one approved light theme at launch.
- Email-client support matrix.
- Print/PDF browser and paper-size support matrix.
- Design-system ownership and approval roles.

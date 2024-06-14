# 1.3.6 / 2024-04-02
## Styles
- Field: Update spacing between fields from 24px to 16px (Roan Kattouw)
- [DEPRECATING CHANGE] tokens: Deprecate unused position-offset-input-radio--focus (Roan Kattouw)
- tokens: Add a new format for experimental CSS output (Eric Gardner)
- tokens: Use the built-in CSS formatter for the experimental build (Roan Kattouw)
- tokens: Rename experimental output to theme-codex-wikimedia-experimental (Roan Kattouw)
- tokens: Produce CSS vars file with overrides for dark mode (Eric Gardner)
- tokens: Add Less mixin version of dark mode build (Roan Kattouw)

## Icons
- icons: Add 'sortVertical' (Volker E.)

## Code
- Table: Make it possible to set text alignment per column (Anne Tomasevich)
- build: Fix bug in diff-release.sh caused by prepack/postpack scripts (Roan Kattouw)
- build: Make "legacy" build redundant (Eric Gardner)
- build: Update floating-ui to 1.0.6 (Roan Kattouw)
- build: Update vite to 4.5.2 (Roan Kattouw)
- build: Silence nonsensical CSS syntax errors (Roan Kattouw)

## Documentation
- demos: Remove superflous self-closing syntax (Volker E.)
- docs: Update components guidelines for Link, Button, and ButtonGroup components. (Bárbara Martínez Calvo)
- docs: Update components guidelines for Select, Combobox, TextInput, and TextArea. (Bárbara Martínez Calvo)
- docs: Remove the using.svg images from Button, ButtonGroup, and Link. (Bárbara Martínez Calvo)
- docs: Change spacing guidance on forms from 24 to 16 between fields (Derek Torsani)
- docs: Update components guidelines for Checkbox, Radio, and ToggleSwitch. (Bárbara Martínez Calvo)
- docs: Update components guidelines for SearchInput and TypeaheadSearch. (Bárbara Martínez Calvo)
- docs: Update components guidelines for Field, Label, Lookup, and ChipInput. (Bárbara Martínez Calvo)
- docs: Update components guidelines for Accordion, Card, Icon, InfoChip, and Thumbnail. (Bárbara Martínez Calvo)
- docs: Update components guidelines for ToggleButton and ToggleButtonGroup. (Bárbara Martínez Calvo)
- docs: Update components guidelines for Message and Dialog. (Bárbara Martínez Calvo)
- docs: Update components guidelines for Menu, MenuItem, Tabs, Tab, ProgressBar. (Bárbara Martínez Calvo)
- docs: Add note about limited use of SearchInput in Field (Volker E.)
- docs: Fix links to validation section in Field docs (Roan Kattouw)

# 1.3.5 / 2024-03-19
## Styles
- styles: spacing between adjacent Field components (lwatson)
- tokens: Add area background color tokens and apply (Volker E)

## Code
- build: update browserslist-config-wikimedia to latest version 0.6.1 (Volker E)
- build: Token reorganization part 2 (Eric Gardner)
- Table: Add initial, WIP component (Anne Tomasevich)
- Table: Set min-height on footer (Anne Tomasevich)
- tokens: expand the option tokens color palette (lwatson)
- tokens: Make sure tokens in the JS output are always strings (Roan Kattouw)

## Documentation
- Field: Remove unused status class from CSS-only Field (Roan Kattouw)
- Field: Remove unused cdx-field__control--has-help-text CSS class (Roan Kattouw)
- docs: Add ADR for  CSS icon system (Anne Tomasevich)
- docs: add Patchdemo instructions to the releasing doc (lwatson)
- docs: unify link name (Volker E)
- docs: Unify casing of component descriptions (Volker E)

# 1.3.4 / 2024-03-05

## Features
- Lookup: menu is closed if there are no menu items (lwatson)

## Styles
- Select: Use background rules for the CSS-only icon (Anne Tomasevich)
- Icon, Select: Use escape() for escaping colors, now that Less.php supports it (Roan Kattouw)

## Code
- build: add .npmrc to all packages (Volker E)
- deps: shrinkwrap dependencies (Eric Gardner)
- build: Remove codex-search build (Ty Hopp)
- build: use prepack/postpack to manage extra files (Eric Gardner)
- build: Re-organize Codex design tokens (Eric Gardner)
- Revert "docs: Fix mobile menu presentation" (Eric Gardner)

## Documentation
- docs: Remove internal links from "Additional Resources" (Volker E)
- docs: add guidelines for Lookup with initial suggestions (bmartinezcalvo)
- ADR 8: Color modes and token organization (Eric Gardner)
- docs: Add forms guidelines to Codex style guide (Derek Torsani)

# 1.3.3 / 2024-02-20

## Styles
- buttons: Use `inline-flex` for content (Anne Tomasevich)
- Icon, styles: Use mask-image for all CSS icons (Anne Tomasevich)
- styles: Remove uses of Less's fade() function (Anne Tomasevich)
- build, tokens: Add "experimental" tokens and stylesheets (Eric Gardner)

## Code
- Lookup: Enable an initial list of suggestions (Anne Tomasevich)
- tokens: Remove leading space in tab-list-item tokens (Roan Kattouw)
- build: Respect CODEX_DOC_ROOT when building the sandbox (Roan Kattouw)
- build: Updating netlify-cli to 15.11.0 (libraryupgrader)
- build: update 'browserslist-config-wikimedia' to latest v0.6.0 (Volker E)
- build: Unify SVGO configurations (Volker E)
- build: update 'less', 'postcss' and 'autoprefixer' to latest versions (Volker E)
- build: update 'style-dictionary' to latest 3.9.2 (Volker E)
- build: Automatically add all demos/*.html files to the sandbox build (Roan Kattouw)

## Documentation
- docs: add Components Guidelines when designing components (bmartinezcalvo)
- docs: Add ES6 vars usage ability to formats (Volker E)
- docs: Add other statuses to the Field configurable demo (Anne Tomasevich)
- docs: Make really long text in InfoChip demo longer (Bartosz Dziewoński)
- docs: Fix mobile nav bar font size (Volker E)
- docs: Add "no results" message to Lookup demo (Anne Tomasevich)
- docs: Fix mobile menu presentation (Volker E)
- docs: Fix mobile 'on this page' navigation styling (Volker E)
- docs: Add conclusion to Wikipedia Apps guidelines (Volker E)
- docs: Style tables in the demo pane (Anne Tomasevich)
- docs: Document the CSS-only InfoChip (C. Scott Ananian)

# 1.3.2 / 2024-02-06

## Styles
- Accordion: Update focus styles and docs image alt text (Eric Gardner)
- TextInput, Select: reduce base height (lwatson)
- styles, Field: Fix and simplify spacing of help text (Anne Tomasevich)

## Code
- build, tokens: Add a custom "camelCaseNegative" transformer (Eric Gardner)
- build: Reorganize custom style-dictionary methods (Eric Gardner)
- build: Make diff-css.sh show uncommitted changes (Roan Kattouw)
- build: Update rtlcss and postcss-rtlcss (Roan Kattouw)
- build: Upgrade Stylelint dependencies and 'postcss-html' to latest (Volker E)

## Documentation
- docs: Align h2 anchor links with heading text (Anne Tomasevich)
- docs: Add interaction guidelines in style guide (Derek Torsani)
- docs: Rename to Design System Team (singular) (Volker E)
- docs: Style Accordion content image (Anne Tomasevich)

# 1.3.1 / 2024-01-24

## Code
- build: Point "main" to the CJS file rather than the ESM file (Roan Kattouw)

# 1.3.0 / 2024-01-23

## Note
As of this release, all the Codex packages have been migrated over to
ESM (and have "type: module" set in their respective package.json files).

## Features
- Accordion: Use <details> element for markup (Eric Gardner)
- Accordion: Enable and demonstrate CSS-only usage (Eric Gardner)
- Accordion: Remove click handler, work around test brokenness (Roan Kattouw)
- Field: Enable use of warning and success messages (Anne Tomasevich)
- TextArea: Add CSS-only version (lwatson)
- TextArea: CSS-only version follow-up (lwatson)

## Styles
- TypeaheadSearch, styles: Increase specificity of menu CSS overrides (Eric Gardner)
- styles, Card: Fix CSS-only Card with icon (lwatson)
- styles, token: Add new border color token for "media" borders (lwatson)
- styles: Apply border-color-muted in Codex (lwatson)
- styles: Fix boldening of Labels (Volker E)

## Code
- build, tokens: Export design tokens as ES6 variables (Anne Tomasevich)
- build: Migrate @wikimedia/codex package to ESM (Eric Gardner)
- build: Migrate codex-design-tokens package to ESM (Ty Hopp)
- build: Migrate codex-icons package to ESM (Eric Gardner)
- build: Pin dependencies to exact versions (Volker E)
- build: Update 'vite' to latest 4.n version (Volker E)
- build: Update .browerslistrc reference to upstream (James D. Forrester)

## Docs
- docs: Fix code color regression and contrast issue (Volker E)
- docs: Remove VRT from releasing docs (Anne Tomasevich)
- docs: fix typo and expand abbreviations in Radio & Voice and tone (Volker E)
- docs: improve progress bar elevated asset (Volker E)
- docs: update URL to token typography in Codex (lwatson)

# 1.2.1 / 2024-01-09

## Styles
- styles: Increase CSS specificity of subcomponents (lwatson)

## Code
- build: bump expected node from 16 to 18 everywhere (Volker E)
- build: Update 'svgo' to v3.0.5 (Volker E)
- deps: Update vitepress and vue-docgen-cli (Eric Gardner)
- build: Update custom SVGO plugin to conform to public API (Eric Gardner)
- build: Remove `<!-- comments -->` from build output (Roan Kattouw)
- build: Update 'svgo' to latest v3.2.0 (Volker E)
- build: Update 'browserslist-config-wikimedia' to latest (Volker E)
- tokens, build: Remove deprecated WikimediaUI Base aliases and its build (Volker E)

## Documentation
- docs: Redirect from old "adding icons" URL to the new one (Roan Kattouw)
- docs: Add v-model to TextInput demos (lwatson)
- docs, Field: Flatten fieldset examples and cross-link them (Roan Kattouw)
- docs: Standardize InfoChip demo page (Anne Tomasevich)
- docs: Isolate styles in the demo pane (Anne Tomasevich)
- docs: Improve the "adding new icons" docs (Anne Tomasevich)
- docs: Move the "adding new icons" page (Anne Tomasevich)

# 1.2.0 / 2023-12-19

## Features
- docs: Add illustration guidelines (Derek Torsani)

## Styles
- styles: Replace falsely applied `font-size-base` with `font-size-medium` (Volker E)

## Code
- build: Migrate codex-docs package to ESM (Eric Gardner)
- build: Upgrade expected node from 16 to 18 (James D. Forrester)
- build: Make modular build entries explicit (Ty Hopp)
- build: Make icons explicit runtime dep of codex package (Ty Hopp)
- build: Automatically update codex-icons dependency for new releases (Roan Kattouw)
- build: Rerun npm install after switching branches in diff-css.sh (Roan Kattouw)
- nvmrc: Update to Node 18 now we've moved CI (James D. Forrester)

## Documentation
- docs: Adjust sidebar outline marker size (Ty Hopp)
- docs: Fix minor issues in 4 component images (Volker E)
- docs: Use Less variable in order to have rules applied (Volker E)

# 1.1.1 / 2023-12-06

## Code
- build: Don't remove manifest-rtl.json etc from the output (Roan Kattouw)
- build: Pin style-dictionary to 3.8.0 (Eric Gardner)

# 1.1.0 / 2023-12-05

## Features
- Select: Prevent Space key from scrolling the page (lwatson)
- useFloatingMenu: Add clipping and flipping (Roan Kattouw)
- Menu: Improve scrolling behavior to play nice with useFloatingMenu (Roan Kattouw)

## Styles
- TextInput: Move border-radius from `<input>` to root element (Roan Kattouw)
- Menu: Update border-radius when Menu flips (Roan Kattouw)
- Menu: Fix footer item being too wide (Roan Kattouw)
- useFloatingMenu: Also apply padding to flip() (Roan Kattouw)

## Icons
- icons: Add 'appearance' icon (Derek Torsani)

## Code
- build: Deduplicate build emissions (Ty Hopp)
- build: update 'svgo' & 'svglint' dependencies (Volker E)
- build: Update eslint-config-wikimedia 0.25.1 -> 0.26.0 (Roan Kattouw)
- deps: Update Vue to 3.3.9 and lock key package versions (Eric Gardner)

## Documentation
- docs: Add Rules component for dos and dont's (Anne Tomasevich)
- docs: Add Codex CSS import methods to usage page (Ty Hopp)
- docs: Clarify required packages to install (Ty Hopp)
- docs: Update readme files to reference usage docs (Ty Hopp)
- docs: Add content guidelines (Anne Tomasevich)
- docs: Update build products for icons package (Roan Kattouw)
- Menu, docs: Use useFloatingMenu in all Menu demos (Roan Kattouw)
- demo: Add menu with footer to the DialogDemo (Anne Tomasevich)
- docs: Misc fixes for content guidelines (Derek Torsani)
- docs: Update AUTHORS.txt (Anne Tomasevich)

# 1.0.1 / 2023-11-07

## Features
- MenuItem: Ensure proper hyphenation and wrapping of long words (Anne Tomasevich)

## Styles
- Tabs: Update margin of frameless/quiet tabs (lwatson)
- tokens: Add explanatory comment for `font-size-base` (Volker E)
- tokens: fix self-references in deprecated `box-shadow-` aliases (Volker E)

## Icons
- icons: optimize 'qrCode' and 'userRights' code (Volker E)

## Code
- build: Update browserslist database to latest (Volker E)
- build: Prepare codex-icons for Jest migration (Roan Kattouw)
- build: Update TypeScript, eslint, jest, etc (Roan Kattouw)
- build, tokens: add custom file header representing “Codex Design Tokens” (Volker E)

## Documentation
- docs: Hide the social link flyout menu (Anne Tomasevich)
- docs: Improve tokens table name cell layout on mobile (Anne Tomasevich)
- docs: Add ID to each token's name cell (Anne Tomasevich)
- docs, styles: Replace fallback sans with Codex font stack choice (Volker E)
- docs: Optimize formerly oversized and binary containing SVG images (Volker E)
- docs: Add CSS grid layout to Codex (lwatson)
- docs: Move Apps guidelines section from DSG to Codex (lwatson)
- docs: Optimize Apps section's PNGs (Volker E)
- docs: improve readability of Wikimedia Apps guidelines (Volker E)
- docs: Reduce size of 'text-area-types-*.svg' (Volker E)
- docs: Update contributing page Phabricator links (Ty Hopp)
- docs: Reference to “Apps” as “Wikipedia Apps” (Volker E)

# 1.0.0 / 2023-10-24

## Code
- build: Don't minify the ESM library build (Roan Kattouw)

## Documentation
- docs: Fix broken Dialog demo (lwatson)
- docs: Remove actions from Field type (Volker E)
- docs: Use CSS multi-column layout to display interaction states (lwatson)
- docs: Export useFloatingMenu composable (lwatson)
- docs: Document Codex Composables (lwatson)
- docs: Update AUTHORS.txt for 1.0 release (Volker E)

# 1.0.0-rc.1 / 2023-10-12

## Code
- build: Update 'svglint' and 'glob' packages (Volker E)
- build: Make svglint check for correct XML declaration (Volker E)
- build: Add SVGO & svglint to 'codex-docs' (Volker E)

## Documentation
- docs: Remove "Usage" heading from component pages (Anne Tomasevich)
- docs: Move Vue-specific component documentation (Anne Tomasevich)
- docs: Move Vue-specific component documentation, part II (Volker E)
- docs: Move Vue-specific component documentation, part III (Volker E)
- docs: Move Vue-specific component documentation, part IV (lwatson)
- docs: Rename "states" to "interaction states" in docs (Volker E)
- docs: Expand abbreviations in ADR 07 (Volker E)
- docs: Add 'Fira Code' monospace font to typography guide (Volker E)
- docs: Add existing component guidelines image assets (Volker E)
- docs: Add new component guidelines image assets (Volker E)
- docs: Add background shade to asset images (Volker E)
- docs: Don't use SFC comments in component documentation pages (Eric Gardner)
- docs: Move existing guidelines of Combobox, TextInput, Message, and Dialog (lwatson)
- docs: Create design guidelines for ToggleButton, ToggleButtonGroup, and TypeaheadSearch (lwatson)
- docs: Create design guidelines for Tab, Tabs, TextArea and Thumbnail (Volker E)
- docs: Create design guidelines for Label, Lookup and Menu (Volker E)
- docs: Create design guidelines for MenuItem, ProgressBar and SearchInput (Volker E)
- docs: Move existing guidelines of Checkbox, Radio, Select, and ToggleSwitch (lwatson)
- docs: Create design guidelines for Accordion, Card and ChipInput (Volker E)
- docs: Move existing guidelines of Button, ButtonGroup and Link (Volker E)
- docs: Fix minor layout issues and missing links (Volker E)
- docs: Create design guidelines for Field, Icon and InfoChip (Volker E)
- docs: Update Menu and Lookup component assets (Volker E)
- docs: Fix component guideline images by removing backgrounds and borders (Volker E)
- docs: Follow-up fixes including more links, consistent style (Volker E)
- docs: Asset images hot fixes for component guidelines (Volker E)

# 0.20.0 / 2023-09-26

## Features
- Combobox, Lookup, Select: Use FloatingUI (Anne Tomasevich)

## Styles
- Add cdx-no-invert class to thumbnails (Ed Sanders)
- Checkbox: Add error-active state (lwatson)
- TextArea: Update CSS display property (lwatson)
- icons: Remove unnecessary `standalone` SVG attribute (Volker E)

## Code
- More consistently use async/await with nextTick() (Roan Kattouw)
- ChipInput: Add input text as chip when focus leaves component, not input (Roan Kattouw)
- ToggleSwitch, docs: Remove leftover classes from label content (Volker E)
- build: Update versions of packages (Roan Kattouw)
- build: Update 'stylelint-config-wikimedia' and 'stylelint' plugins (Volker E)
- build: Update 'eslint-config-wikimedia' and remove 'eslint' (Volker E)
- build: Disable security/detect-non-literal-fs-filename (Eric Gardner)
- build: Prefer CJS build to UMD for most usage (Eric Gardner)
- build: Generate Codex bundles for every component (Eric Gardner)
- build: Configure eslint to lint .mjs files properly (Roan Kattouw)
- build: Remove codex-search workspace (lwatson)
- build: Make the build config the primary tsconfig.json file (Roan Kattouw)

## Documentation
- docs: Style tweaks to character counter example for Field component (Eric Gardner)
- docs: Update token table style and add caption (Volker E)
- docs: Fix designing icons Phabricator template link (Volker E)
- docs: Limit headings in page nav to level 2 and 3 in components (Volker E)
- docs: Add ADR 07: Floating UI (Eric Gardner)

# 0.19.0 / 2023-09-12

## Features
- FilterChip, FilterChipInput: Rename to ChipInput and InputChip (Anne Tomasevich)
- ChipInput: Enable in Field and add form field demo (lwatson)
- ChipInput: Make inputChips prop required (Roan Kattouw)
- ChipInput: Add ARIA attributes (Roan Kattouw)
- ChipInput: Add keyboard navigation (Roan Kattouw)
- ChipInput: Release MVP component (Anne Tomasevich)
- InputChip: Remove now-unused generated ID on the remove button (Roan Kattouw)
- Checkbox: Implement the error state (lwatson)
- Dialog: Put all on-open code together, and run it on mount if needed (Roan Kattouw)
- Dialog: Set aria-hidden and inert on the rest of the page when open (Roan Kattouw)

## Styles
- Field: Add CSS-only version (Anne Tomasevich)
- Label: Add CSS-only version (Anne Tomasevich)
- TextInput, Combobox: Set consistent min-width regardless of icons (Roan Kattouw)
- TextInput: Fix padding when both clear button and end icon appear (Roan Kattouw)
- styles, ChipInput: Increase gap between chips (Anne Tomasevich)
- styles, Field: Unset margin on the root element (Anne Tomasevich)
- tokens: Rename error hover tokens to stay consistent (Volker E)

## Code
- sandbox: Add ChipInput (Roan Kattouw)
- build: Make mw-examples:clean clean up better (Roan Kattouw)

## Documentation
- docs, Checkbox, Radio: Use CSS-only Field and Label (Anne Tomasevich)
- docs: Add a Field demo showing a custom character counter (Eric Gardner)
- docs: Fix styles for custom Dialog example (Roan Kattouw)
- docs: Improve accessibility purpose statements (Volker E)
- docs: Move Wrapper teleport target to work around VitePress bug (Roan Kattouw)
- docs: Use deep outline on component pages (Anne Tomasevich)
- docs: add `sandbox:` prefix to contributing code commits docs (Volker E)

# 0.18.0 / 2023-08-29

## Breaking changes
- [BREAKING CHANGE] Radio: Made the name prop of the Radio component required (didier-ds)
    - Previously, the CdxRadio component could be used without passing in the
      "name" prop. This prop is now required, and a warning will be emitted
      if it is not passed in.
- [Breaking] Dialog: incorporate Vue's <teleport> feature (Eric Gardner)
    - Previously, dialogs were rendered in-place, and centered on the page
      using absolute positioning. Now, all dialogs are teleported to the
      bottom of the <body> by default. This may cause styling differences,
      because the styles that would apply to a dialog rendered in place may
      not apply to a teleported dialog. To address styling issues, applications
      may have to change CSS selectors that target dialog contents, and/or
      provide() a teleport target container for dialogs to be teleported into.

## Features
- ToggleButton: Add icon-only detection (Roan Kattouw)
- ProgressBar: Add `aria-label` to component and demos (Volker E)
- Menu: Add ARIA live region when menu items are pending (Volker E)

## Styles
- Radio: Add focus styles for unchecked radios (Anne Tomasevich)
- FilterChip: Add interactive styles (Anne Tomasevich)

## Code
- Checkbox: Remove enter keypress handler to trigger click event (Volker E)
- codex, utils: Fix typo in stringTypeValidator (Volker E)
- Button, Tabs: Factor out common slot content analysis code (Roan Kattouw)
- Button: Move icon-only check into its own composable (Roan Kattouw)
- useIconOnlyButton: Factor out warn-once logic into new useWarnOnce composable (Roan Kattouw)
- useLabelChecker: Reimplement using useWarnOnce and useSlotContents (Roan Kattouw)
- Combobox: Fix typo in screen reader comment (Volker E)
- FilterChip: Add click-chip event and change keyboard behavior (Anne Tomasevich)
- FilterChipInput: Make chips editable; add chip on blur (Anne Tomasevich)
- FilterChipInput: Ensure error state is unset (Anne Tomasevich)
- Field: Improve reactivity for input ID and description ID (Roan Kattouw)

## Documentation
- Dialog: Fix target types and clarify teleport documentation (Roan Kattouw)
- docs: Use consistent casing for "design tokens" (Anne Tomasevich)
- docs: Update and improve php commands in RELEASING (Anne Tomasevich)
- docs: Unify on “TypeScript” (Volker E)
- docs: Update broken links (Martin Urbanec)
- docs: Fix "Copy code" button, make it work with code groups (Roan Kattouw)
- docs: Fix reference to origin/master in Codex release docs (Catrope)

# 0.17.0 / 2023-08-16

## Features
- Menu: Don't set aria-activedescendant when the menu is closed (Roan Kattouw)
- Menu: Always clear the highlighted state when the menu closes (Roan Kattouw)
- Menu, Select: Allow keyboard navigation through typing (Roan Kattouw)
- Dialog: Add `aria-modal="true"` to the dialog element (Volker E)

## Styles
- tokens: Remove min-size-base, make it a deprecated alias (Roan Kattouw)

## Code
- Menu: Simplify logic for highlighting selected item (Roan Kattouw)

## Documentation
- docs, Field: Fix Label usage in demo (Anne Tomasevich)
- Menu, docs: Document ARIA attributes and add them to all examples (Roan Kattouw)
- docs, ToggleButton: Remove dynamic label examples (Anne Tomasevich)
- docs, Menu, MenuItem: Use proper HTML structure for demos (Anne Tomasevich)
- docs, Link: Improve demo text (Anne Tomasevich)
- docs, Button: Add docs on role="button" for fake buttons (Anne Tomasevich)
- demo: Add an exhaustive table of all icons (Roan Kattouw)
- demo: Move grid of all buttons to a separate page (Roan Kattouw)
- docs: Fix alphabetical order of sidebar items (Anne Tomasevich)
- docs: Clarify use of role="button" and remove from label demos (Anne Tomasevich)
- docs: Brand the main site as "beta" (Anne Tomasevich)
- docs: Change background color of beta tag (Anne Tomasevich)
- Checkbox, Radio, ToggleSwitch: Improve group docs and demo pages (Anne Tomasevich)
- docs, Checkbox: Remove CSS-only indeterminate checkbox (Anne Tomasevich)
- docs: Add MediaWiki versions of inlined Less examples (Roan Kattouw)
- docs: Add more descriptive `aria-label` to GitHub link (Volker E)
- docs: Improve beta tag design (Anne Tomasevich)
- docs: Standardize design tokens and components overview format (Anne Tomasevich)
- docs: Improve CSS-only button and checkbox docs (Anne Tomasevich)
- docs: Standardize capitalization of "design tokens" (Anne Tomasevich)
- docs: Shorten link text on design tokens overview page (Anne Tomasevich)
- docs: Improve release process docs for visual regression tests (Roan Kattouw)

# 0.16.1 / 2023-08-01
## Styles
- Tabs: Override browser default styles for <button> (Roan Kattouw)

# 0.16.0 / 2023-08-01

## Breaking
- Tabs: Align markup closer to APG example (Eric Gardner)
  - This changes the HTML markup for the CSS-only version of the Tabs component uses.
    Users of the CSS-only Tabs component must update the HTML they output to the
    new version to continue to get the correct styling.

## Features
- ToggleSwitch: Fix label hover behavior (Anne Tomasevich)
- Label, Field: Do not render description unless it exists (Anne Tomasevich)
- Checkbox, Radio, ToggleSwitch: Warn when input is not labelled (Anne Tomasevich)
- Message: Don't allow error messages to be auto-dismissed (Roan Kattouw)

## Styles
- tokens: Slightly darken color-red600 to improve contrast (Roan Kattouw)
- tokens: Use the color-link-red tokens for red links (Roan Kattouw)
- tokens: Consistently use -error tokens for error state (Roan Kattouw)

## Code
- build: Also check .mjs files with TypeScript (Roan Kattouw)
- Checkbox, Radio, ToggleSwitch: Use Label internally (Anne Tomasevich)
- Label: Make <legend> the root element (Anne Tomasevich)
- ToggleSwitch: Use role="switch" (Anne Tomasevich)
- Icon: Remove click handler (Anne Tomasevich)
- Combobox, Lookup, Select, TypeaheadSearch: Clean up ARIA attributes (Roan Kattouw)
- code: Add component name to warnings (Anne Tomasevich)

## Documentation
- docs: Limit code examples to plain JS (no TypeScript) and ES6 (Roan Kattouw)
- docs: Remove margin and border-radius above code groups (Anne Tomasevich)
- docs, build: Generate MediaWiki-targeted versions of every code example (Roan Kattouw)
- docs, Radio: Don't use the same name attribute for multiple demos (Roan Kattouw)

# 0.15.0 / 2023-07-18

## Features
- Accordion: Remove "disabled" property for now (Eric Gardner)
- Accordion: Move from WIP components to public components (Roan Kattouw)
- FilterChip, FilterChipInput: Add WIP components to Codex (Julia Kieserman)
- FilterChipInput: Add variant with separate input (Anne Tomasevich)

## Styles
- Message: Remove top and bottom margins from first/last content children (Eric Gardner)
- Add horizontal centering to fake button styles (bwang)

## Code
- Accordion: Improve tests (Roan Kattouw)
- Accordion: Make tests not throw warning about missing icon label (Roan Kattouw)

## Documentation
- docs: Add demo table for icon-only link buttons with small icons (Anne Tomasevich)

# 0.14.0 / 2023-07-05

## Features
- ToggleSwitch: Fix layout of CSS-only version (Anne Tomasevich)
- TextInput: Add `readonly` styling and config demo (Volker E)
- Icon: Make <use> work in CSS-only icons (Roan Kattouw)

## Styles
- styles, Button, Message: Remove some `:not()` selectors (Anne Tomasevich)
- Button, styles: Remove :not() selectors for button size (Roan Kattouw)
- Accordion: Simplify styles and add comments (Roan Kattouw)

## Icons
- Icons: add QR code icon (MusikAnimal)
- Icons: Add user rights icon to Codex (LWatson)

## Code
- build: Don't make stylelint require tokens where they don't exist (Roan Kattouw)
- demo: Refactor ButtonDemo and improve coverage (Roan Kattouw)
- Accordion: changes after design review (szymonswiergosz)
- Accordion: ARIA fixes and improvements (Eric Gardner)

## Documentation
- TextArea: Add resize browser support warning to Codex docs (LWatson)
- docs, ToggleSwitch: Use unique IDs in CSS-only ToggleSwitch examples (Roan Kattouw)
- Icon, docs: Use "code" instead of "tag" in CSS-only icon demo (Roan Kattouw)
- docs: Remove unused/unnecessary styles in demos (Roan Kattouw)
- lib: Export IconSize type (Roan Kattouw)
- docs: Rewrite useModelWrapper docs (Roan Kattouw)

# 0.13.0 / 2023-06-20

## Features
- Label: Add Label component (Anne Tomasevich)
- Field: Add Field component and enable use with input components (Anne Tomasevich)
- Field: Set help text line height and improve demos (Anne Tomasevich)
- Label: Change line height and add a rich text demo (Anne Tomasevich)
- TextArea: Change icon in demo (LWatson)
- Field, Label: Set smaller line height (Anne Tomasevich)
- TextArea: Move from WIP components to public components (LWatson)
- Field et al: Add demos for supported components (Anne Tomasevich)
- TextArea: Enable use with Field component (Anne Tomasevich)

## Styles
- Button: Organize and correct padding styles (Anne Tomasevich)
- Dialog: Simplify padding styles (Roan Kattouw)
- TextArea: Remove error styles from readonly (LWatson)
- TextArea: Move CSS overrides to mixin (LWatson)
- Button, styles: Fix bare :not() selector (Roan Kattouw)

## Code
- build: Update doc comment in codex-docs postcss config (Roan Kattouw)
- build: Make svglint rules stricter (Roan Kattouw)

## Documentation
- docs: Update release instructions for foreign-resources version field (Roan Kattouw)
- Tabs: Don't set role="tab" on <li>s in CSS-only markup (Roan Kattouw)
- Tabs, docs: Don't show Vue code in the CSS-only HTML example (Roan Kattouw)
- Tabs: Fix CSS-only example for disabled tabs (Roan Kattouw)
- docs: Show a more realistic link button example (Anne Tomasevich)
- TextArea: Demonstrate more examples on the Codex docs (LWatson)
- docs: Add section about linting to the "Contributing code" docs (Roan Kattouw)
- docs: Remove label button example (Anne Tomasevich)
- docs: Apply rtlcss ([dir] selectors) to component demo examples (Roan Kattouw)
- Fix tokens package name (Lucas Werkmeister)

# 0.12.0 / 2023-06-06
## Features
- Accordion: initial implementation of accordion component (szymonswiergosz)
- Accordion: Add ARIA-labels for icon-only buttons (Eric Gardner)
- Accordion: Remove unused CSS rule (Roan Kattouw)
- Button: Add classes to support CSS-only non-button buttons (bwang)
- Combobox, Lookup, SearchInput: emit input events (Anne Tomasevich)
- Dialog: automatically display dividers when content is scrollable (Eric Gardner)
- Select: Add status prop (Anne Tomasevich)
- Tab: Don't output `aria-hidden` attribute  with "false" values (Eric Gardner)
- Tabs, styles: Deduplicate tab styles (Roan Kattouw)
- TextArea: Add config demo to docs page (LWatson)
- TextArea: Add startIcon and endIcon props (LWatson)
- TextArea: Refactor autosize (LWatson)
- TextInput: expose a public blur() method (Eric Gardner)
- ToggleSwitch: Enable and demonstrate switch groups (Anne Tomasevich)

## Styles
- tokens: Move `min-size-interactive*` and deprecate `min-size-base` token (Volker E)

## Icons
- icons: Update 'userTemporary' to latest design (Volker E)

## Code
- Remove unnecessary uses of toRefs() and toRef() (Roan Kattouw)
- build, styles: Add `grid` properties to 'properties-order' (Volker E)
- build, styles: Enable `*width*` and `*height*` declaration strict value (Volker E)
- build: Add `gap` to 'declaration-strict-value' (Volker E)
- build: Add `justify-items` and `justify-self` to 'properties-order' (Volker E)
- build: Enable `box-sizing` declaration strict value (Volker E)
- build: Update netlify-cli from 10.10.2 to 15.1.1 (Roan Kattouw)
- build: Use correct name for `isolation` property (Volker E)

## Documentation
- demo: Hide empty table headers from AT (Volker E)
- docs: Add ADR for Field component implementation (Anne Tomasevich)
- docs: Add coded typography example (Anne Tomasevich)
- docs: Fix typo in “VitePress” (Volker E)
- docs: Fix typos in 'CHANGELOG.md' and 'RELEASING.md' (Volker E)
- docs: Make CSS Tabs demo require scroll (Anne Tomasevich)
- docs: Make box-shadow-color demo more consistent with box-shadow demo (Roan Kattouw)
- docs: Replace deprecated `line-height-x-small` token (Volker E)
- docs: Replace protocol-relative URLs with 'https://' only (Volker E)

# 0.11.0 / 2023-05-23
## Features
- Button: Add `size` prop (Anne Tomasevich)
- TextArea: Add fixedHeight and autosize props (LWatson)

## Styles
- Button: Update flush mixin to handle button sizes (Anne Tomasevich)
- Combobox, styles: Apply combined minimum width to Combobox (Volker E.)
- Icon: Fix flush mixin and CSS-only icon button mask styles (Anne Tomasevich)
- link, styles: Replace SFC variables with tokens (Volker E.)
- Select: Set opacity of 1 for disabled CSS select (Anne Tomasevich)
- Tabs, styles: Use appropriate box-shadow color tokens (Volker E.)
- tokens: Add `outline-color-progressive--focus` token (Volker E.)
- tokens, binary-input: Replace SFC vars with tokens and add min size (Volker E.)
- Minimize and fix Wikisource logo (thiemowmde)
- icons: Manually optimize MediaWiki/Wikinews/Wiktionary logos (thiemowmde)
- icons: Add 'userTemporary' (Volker E.)

## Code
- CSS components: Implement design and docs improvements (Anne Tomasevich)
- TextInput: Emit a "clear" event when clear button is clicked (Eric Gardner)
- TypeaheadSearch: Use menu item classes for the search footer (bwang)
- build: Update 'style-dictionary' to latest version (Volker E.)
- build, styles: Enable `z-index` declaration strict value (Volker E.)
- build, styles: Add `transition-property` to declaration strict value (Volker E.)

## Documentation
- docs: Amend `box-shadow` color demo (Volker E.)
- docs: Add `size` prop to Button configurable demo (Anne Tomasevich)
- docs: Fix CSS tabs demo (Anne Tomasevich)
- docs: Explain which Message features aren't supported in CSS version (Anne Tomasevich)
- docs, Button: Don't set `@click` on Icons (Roan Kattouw)
- docs: Add ADR for CSS Components (Anne Tomasevich)
- docs: Make font size relative (Volker E.)
- docs: Add `aria-label` to CSS-only icon only Button demo (Volker E.)

# 0.10.0 / 2023-05-10

## Features
- Icon: Support icons in CSS-only buttons in Chrome (Anne Tomasevich)
- Tabs: Add CSS-only version (Anne Tomasevich)

## Styles
- Button, tokens: Correct button padding values and add tokens (Anne Tomasevich)
- styles: Add mixins for flush layouts (Anne Tomasevich)
- tokens: Remove trailing zero (Volker E)
- TextArea: Add error state styles (LWatson)
- styles, tokens: Apply correct border color tokens (Volker E)
- ToggleSwitch, styles: Use slower transition for background color change (Eric Gardner)
- TextInput: Fix error status styles for a disabled TextInput (LWatson)
- styles, mixins: Name icon fallback mixin more appropriately (Volker E)
- InfoChip, styles: Use design specification border color (Volker E)
- Tabs, styles: Replace SFC vars with tokens (Volker E)
- tokens, styles: Use min-size appropriately in Select and TextArea (Volker E)

## Code
- Simplify Codex build process via a JS build script (Eric Gardner)
- Tabs: Remove getLabelClasses, use aria-* for styling (Eric Gardner)

## Documentation
- docs: Add "Toolkit" nav item and nested "Composables" item (Anne Tomasevich)
- docs: Style main nav dropdown menu with Codex tokens (Anne Tomasevich)
- docs: Add style guide overview and update language around Codex (Anne Tomasevich)
- docs: Add click handlers to all Button examples (Roan Kattouw)
- docs: Style dropdown nav to look like Codex Menu (Anne Tomasevich)
- docs: Add link to Phab task template to style guide overview (Anne Tomasevich)
- docs: Update component docs to include CSS-only components (Anne Tomasevich)
- docs: Add Visual Styles section of the Style Guide (Anne Tomasevich)
- docs: Add Design Principles section of the style guide (Anne Tomasevich)
- docs: Use relative sizes for th/td and fix token demo presentation (Volker E)

# 0.9.1 / 2023-04-25
## Styles
- binary inputs: Move `border-color` to enabled and transitions to mixin (Volker E)
- TextArea: Add base and state styles (LWatson)
- tokens: Add 'Green 500', shuffle value of 'Green 600' and new 'Green 700' (Volker E)
- tokens, Message, InfoChip: Use darker border colors (Volker E)
- icons: Fix 'function', 'functionArgument', 'instance' and 'literal' fill (Volker E)
- icons: Remove unnecessary code from .svg icon files (thiemowmde)

## Code
- tests: Add test asserting that all icon .svg files are used (Roan Kattouw)
- tests: Import component files directly, not from lib.ts (Roan Kattouw)
- build: Update Vite, VitePress, and related libraries (Eric Gardner)
- build: Updating node version equal to CI's (Volker E)
- build: Add svglint for icon files (Roan Kattouw)
- build: Remove dependency on @rollup/pluginutils (Roan Kattouw)
- build: Add documentation comments to build-related files in codex-docs (Roan Kattouw)
- build, docs: Fix references to .es.js files that now have .mjs names (Roan Kattouw)

## Documentation
- docs: Add missing mixin import to CSS-only TextInput examples (Roan Kattouw)
- docs: Update RELEASING.md (Eric Gardner)
- docs: Unbreak RTL demos broken by VitePress upgrade (Roan Kattouw)
- Lookup: Add docs for CSS-only support (Anne Tomasevich)
- docs: Remove outline on home page (Anne Tomasevich)
- docs: Update tokens docs and remove warning about Codex status (Anne Tomasevich)
- docs: document useModelWrapper composable (Sergio Gimeno)

# 0.9.0 / 2023-04-11

## Breaking
- Button: Remove `type` prop and all workarounds (Anne Tomasevich)
  - Previously the Button component could accept a `type` prop with
    the following values: `normal`, `primary`, and `quiet`. The name
    of this prop has been changed to `weight` to avoid conflicts with
    the native `type` attribute on HTML `<button>` elements. Any
    existing uses of `<cdx-button>` with the old `type` prop values
    should be updated; this code will continue to function but buttons
    will render in the default style until the prop name is updated to
    `weight`.

## Features
- Dialog: Ensure correct line-height is used in subtitle, footer (Eric Gardner)
- Card, Thumbnail: Add CSS-only versions (Anne Tomasevich)
- ProgressBar: Add CSS-only version (Anne Tomasevich)
- ToggleSwitch: Add CSS-only version (Anne Tomasevich)
- TextArea: Set up WIP component (LWatson)
- TextArea: Set up modelValue prop via v-model (LWatson)
- TextArea: pass most attributes down to the `<textarea>` element (LWatson)

## Styles
- tokens: Add common `border` shorthand tokens (Volker E)

## Code
- build: Don't delete built icon paths file in postpublish (Roan Kattouw)
- build: Update 'browserslist-db' to latest (Volker E)

## Documentation
- docs: Add a wrapped Dialog example (Eric Gardner)
- docs: Remove unneeded class from CSS icon-only button demo (Anne Tomasevich)
- docs: Remove warning about CSS-only components (Anne Tomasevich)

# 0.8.0 / 2023-03-28

## Features
- TextInput: Add additional input types (LWatson)
- Re-introduce Dialog header and footer customization (Eric Gardner)

## Styles
- tokens, styles: Add and apply `z-index` category (Volker E)
- tokens, styles: Expand `z-index` token category by `z-index-stacking-0` (Volker E)
- tokens: Move `line-height-heading` to deprecated aliases (Volker E)
- tokens: Remove `padding-vertical-menu` deprecated alias token (Volker E)
- tokens: Move `*search-figure` to base tokens (Volker E)

## Code
- mixins: Add a file that imports all mixins (Roan Kattouw)
- Icon: Remove unnecessary variable interpolation (Anne Tomasevich)
- build: Remove 'dist/' from import path for codex-icon-paths.less (Roan Kattouw)

## Documentation
- docs: Slightly amend deprecated token headline (Volker E)
- Buttons, docs: use `weight` prop and set appropriate `type` (Anne Tomasevich)

# 0.7.0 / 2023-03-14

## Breaking
- mixins: Remove tokens import (Roan Kattouw)

## Deprecating
- Button: Change `type` prop and add `weight` prop (Anne Tomasevich)

## Features
- MenuItem: Change highlight behavior (Anne Tomasevich)

## Styles
- Button, styles: Use design-first background color tokens for active (Volker E)
- Card, styles: Use correct token on supporting text (Volker E)
- Dialog, styles: Update title font-size (Volker E)
- styles: Replace deprecated tokens with their non-deprecated equivalents (Roan Kattouw)
- tokens, TextInput: Add `opacity-icon-placeholder` and apply to TextInput (Volker E)
- tokens, styles: Move further SFC tokens to components (Volker E)
- tokens: Add `text-overflow` tokens (Volker E)
- tokens: Clean up deprecation messages (Roan Kattouw)
- tokens: Move most deprecated tokens to separate aliases file (Roan Kattouw)
- tokens: Replace `opacity-icon-accessory` with `opacity-icon-subtle` (Volker E)
- tokens: Use `50%` for `border-radius-circle` value (Volker E)

## Code
- Thumbnail, styles: Remove obsolete SFC token (Volker E)
- build: Update Style Dictionary to latest (Volker E)
- build: Upgrade VitePress to 1.0.0-alpha.48 (Eric Gardner)
- css-icon: Make Less code compatible with MediaWiki's older Less compiler (Roan Kattouw)
- tokens: Move Style Dictionary transforms and formats into config file (Roan Kattouw)

## Documentation
- docs: Add instructions for configuring VS Code (Anne Tomasevich)
- docs: Display auto-generated token deprecation messages on docs site (Roan Kattouw)
- docs: Exclude VitePress cache from linting (Eric Gardner)
- docs: Improve VS Code setup docs (Anne Tomasevich)
- docs: Unify on 'Less' term (Volker E)


# 0.6.2 / 2023-02-28
## Styles
- icons: Add 'function', 'functionArgument', 'instance' and 'literal' (Volker E)

## Code
- code: Clean up TextInput files (Anne Tomasevich)
- code: Deduplicate TextInput native event tests (Roan Kattouw)
- build: Upgrade VitePress from 1.0.0.alpha.29 to 1.0.0.alpha.47 (Roan Kattouw)
- build: Update 'stylelint' dependencies (Volker E)
- Revert "build: Upgrade VitePress from 1.0.0.alpha.29 to 1.0.0.alpha.47" (Catrope)
- build: Remove `double` colon override (Volker E)
- build: Add `margin` group to strict value declaration rule (Volker E)
- build: Add `padding` group to strict value declaration rule (Volker E)
- build: Add Stylelint strict value rule for top, left, bottom, right (Volker E)

## Documentation
- docs: Add link to planned components page (Anne Tomasevich)
- docs: Add pre-release coordination instructions to RELEASING.md (Roan Kattouw)
- docs: Update component descriptions (Anne Tomasevich)

# 0.6.1 / 2023-02-21

## Features
- TextInput, SearchInput: Add CSS-only versions (Anne Tomasevich)
- TypeaheadSearch: Add CSS-only version (Anne Tomasevich)
- build: Expose ES module build correctly, rename to .mjs (Sergio Gimeno)

## Styles
- Message, styles: Fix padding on user-dismissable (Volker E)

## Code
- build: Enable `declaration-strict-value` on `background-position` (Volker E)
- tokens: Undeprecate legacy opacity tokens and introduce 1 token (Volker E)

## Documentation
- docs: Amend Sandbox styles (Volker E)
- docs: Provide `aria-label` to all Lookup demos (Volker E)
- docs: Provide `aria-label` to all SearchInput demos (Volker E)
- docs: Provide `aria-label` to all TextInput demos (Volker E)
- docs: Provide `aria-label` to ToggleSwitch demo (Volker E)
- docs: Refine visual hierarchy and use Codex tokens (continued) (Volker E)
- docs: Change CSS-only icon demo to remove size/color combo (Anne Tomasevich)
- docs: Update new component task template links (Anne Tomasevich)
- docs: Update for codex.es.js -> codex.mjs rename (Roan Kattouw)

# 0.6.0 / 2023-02-14

## Features
- Icon: support pre-defined Icon sizes (Eric Gardner)
- Icon: refactor CSS icon mixins and introduce sizes (Anne Tomasevich)
- Button: Add CSS-only version (Anne Tomasevich)
- Menu, TypeaheadSearch: Don't select item highlighted via mouse (Anne Tomasevich)

## Styles
- Tabs, styles: Replace SFC vars with Codex tokens (Volker E)
- TypeaheadSearch, styles: Prevent footer icon container from shrinking (Volker E)
- tokens: Add `background-position` token (Volker E)
- ToggleSwitch, tokens: Amend size and replace SFC vars with tokens (Volker E)
- tokens: Introduce new component tokens for search figure (Volker E)

## Icons
- icons: Minimize MediaWiki logo (Volker E)
- icons: Amend 'Wikinews' and 'Wiktionary' logo (Volker E)

## Code
- build: Updating @sideway/formula to 3.0.1 (libraryupgrader)

## Documentation
- demos: Use a more explicit label for the "Toggle" section in the sandbox (Roan Kattouw)
- docs: Refine visual hierarchy and use Codex tokens (Volker E)
- docs: Add Pixel testing to the releasing docs (Anne Tomasevich)
- docs: Refine visual hierarchy and use design-first heading styles (Volker E)

# 0.5.0 / 2023-01-31

## Features
- InfoChip, Message: Introduce InfoChip component and StatusType constant (Julia Kieserman)
- Icon: Add Less mixin for CSS-only icons (Anne Tomasevich)
- Message: Add CSS-only version (Anne Tomasevich)
- Checkbox, Radio: Add CSS-only versions (Anne Tomasevich)
- Select: Add CSS-only version (Anne Tomasevich)
- Message: Replace 'check' with 'success' icon on success type messages (Volker E)
- build: Build legacy versions of the Codex styles (Roan Kattouw)

## Styles
- build, tokens, styles: Introduce simple stylesheet unit transform (Roan Kattouw)
- tokens, styles: Introduce design-first `font-size` tokens (Volker E)
- tokens: Make `position-offset` token relative & replace offset SFC vars (Volker E)
- tokens: Rename icon token sizes keys according to design (Volker E)
- docs, binary inputs: Improve docs and use a token for label padding (Anne Tomasevich)
- binary input, styles: Use spacing token for padding (Volker E)
- binary input: Remove obsolete `size-icon-small` token (Volker E)
- styles, pending state: Replace relative font size SFCs with token (Volker E)

## Icons
- icons: Add 'success' (Volker E)
- icons: Add Wikimedia logos (Volker E)

## Code
- Combobox, Lookup: Add aria-controls attribute (Lucas Werkmeister)
- Tab: Expose disabled state with `aria-disabled` (Volker E)
- Icon: Don't add aria-hidden=false (Kosta Harlan)
- InfoChip: Follow-up fixes (Eric Gardner)
- InfoChip: Re-name component to InfoChip (Eric Gardner)
- build: Updating eslint to 8.31.0 (libraryupgrader)
- Add .idea (JetBrains IDEs) to .gitignore (Kosta Harlan)
- build: Use shared Vite config for the demos build (Roan Kattouw)
- build: Update 'eslint' dependency and its dependencies (Volker E)

## Documentation
- docs: Move SVGO preset JSON file to the public/ directory (Roan Kattouw)
- README: Add note about running unit tests for a workspace (Kosta Harlan)
- docs: Remove oversized relative size token demos from table (Volker E)
- docs: Improve documentation of CSS-only components (Anne Tomasevich)
- docs: Align Menu demos with keyboard navigation standards (Anne Tomasevich)
- docs: Update types and constants docs to reflect new StatusType (Anne Tomasevich)
- docs: Unify token headings (Volker E)
- docs: Add warning about CSS-only components (Anne Tomasevich)

# 0.4.3 / 2023-01-10

## Styles
- styles, Tabs: Increase specificity of list item margin rule (Anne Tomasevich)
- styles, Dialog: Set heading padding to 0 (Anne Tomasevich)
- ToggleSwitch, styles: Replace SFC absolute positioning var with token (Volker E)
- Card, styles: Remove wrongly applied SFC variable (Volker E)
- styles: Unify to `.cdx-mixin-` as used elsewhere (Volker E)
- tokens: Group absolute dimension and size tokens (Volker E)
- tokens: Add `accent-color` (Volker E)
- tokens: Rename drop shadow token to `box-shadow-drop-xx-large` (Volker E)
- tokens, styles: Add `min-width-medium` to TextInputs and Select (Volker E)
- tokens: Introduce small icon and min size component tokens (Volker E)
- tokens: Use calculated value instead of `calc()` (Volker E)

## Code
- build: Update typescript-eslint (Roan Kattouw)
- build: Add diff-release.sh for previewing release diffs (Roan Kattouw)
- build: Updating json5 to 2.2.2 (libraryupgrader)

## Documentation
- docs: Add mw.org update and thanking contributors to RELEASING.md (Anne Tomasevich)
- docs: Reorder “Designing…” navigation items (Volker E)
- docs: Fix slot binding docs (Anne Tomasevich)
- docs, tokens: Apply `line-height` tokens everywhere (Volker E)

# 0.4.2 / 2022-12-13

## Code
- build: Fix ID prefixing in icons (Roan Kattouw)

# 0.4.1 / 2022-12-13

## Features
- Lookup: Prevent the spacebar from opening the lookup dropdown menu, but only ever having the default behavior of adding a space character. (ddw)
- Combobox, TypeaheadSearch: Always allow default behavior of space key for menu components with text inputs. (ddw)

## Code
- build: Update SVGO to v3.0.2 (Volker E)
- build: Enable `transition-duration` token only linting (Volker E)
- build: Enable checkJs in the icons package (Roan Kattouw)
- build: Update TypeScript and vue-tsc (Roan Kattouw)

## Documentation
- docs: Add tokens package to releasing and Codex documentation (Volker E)
- docs: Update intro card icons (Volker E)
- docs: Add “Designing new components” documentation (Volker E)
- docs: Amend “Designing Icons” page with latest designer feedback (Volker E)
- docs: Add “Redesigning existing components” documentation (Volker E)
- docs: Add “Designing tokens” documentation (Volker E)
- docs: Expand Design System Design Tokens overview (Volker E)
- docs: Update design contribution docs for consistency (Anne Tomasevich)
- docs: Amend design tokens overview with latest comments (Volker E)
- docs: Put “Reset demo” button at bottom (Volker E)

# 0.4.0 / 2022-12-06

## Features
- MenuItem: Add supportingText prop (Anne Tomasevich)

## Styles
- Dialog: Prevent skins from breaking header styles (Eric Gardner)
- Menu: Remove -1px margin-top from menu (Anne Tomasevich)
- MenuItem, docs: Fix CSS workaround for Menu absolute positioning (Roan Kattouw)
- styles: Make mixin imports consistent (Roan Kattouw)
- tokens, styles: Add `animation-iteration-count*` tokens and apply (Volker E)
- tokens: Add `tab-size` token (Volker E)
- tokens: Add tests for getTokenType (Roan Kattouw)
- tokens: Amend Yellow 600 color option token and add Yellow 500 (Volker E)

## Icons
- icons: Optimize 'palette' icon's file size (Volker E)

## Code
- build, tokens: Rename index.json to theme-wikimedia-ui.json (Roan Kattouw)
- build: Add the design-tokens package to the RELEASING.md docs (Roan Kattouw)
- build: Correct file extension in tokens README (Anne Tomasevich)
- build: Correctly align "engine" requirements with Wikimedia CI (Roan Kattouw)
- build: Ensure build-demos script resolves token paths correctly (Eric Gardner)
- build: Remove 'dist/' from import paths for mixin and tokens files (Roan Kattouw)
- build: Updating decode-uri-component to 0.2.2 (libraryupgrader)

## Documentation
- docs, Dialog: Use normal configurable setup for Dialog demo (Anne Tomasevich)
- docs: Add banners for main branch and deployment previews (Anne Tomasevich)
- docs: Amending “Designing icons” (Volker E)
- docs: Demonstrate good TypeScript practices in LookupWithFetch example (Roan Kattouw)
- docs: Document the output of the tokens package, and add a README (Roan Kattouw)
- docs: Link to the latest docs site instead of the main branch one (Roan Kattouw)
- docs: Move SVGO preset JSON file out of assets/ directory (Roan Kattouw)
- docs: Prevent VitePress from treating the link to latest as internal (Roan Kattouw)
- docs: Spell out directions and improve radio controls (Anne Tomasevich)
- docs: Update VitePress to 1.0.0-alpha.29 (Roan Kattouw)
- docs: Update one more link to /main to /latest (Anne Tomasevich)

# 0.3.0 / 2022-11-22

## Features
- Menu, TypeaheadSearch: Dedicated sticky footer in the Menu component (Michael Große)
- ProgressBar: Add disabled state, refine styles and demos (Volker E)
- Treat submits with selected search result like clicks (Lucas Werkmeister)
- TextInput: Add error state to TextInput component (Julia Kieserman)
- Button: Unify active state behavior (Anne Tomasevich)
- ToggleButton: Unify active state behavior (Anne Tomasevich)
- Dialog: prepare basic Dialog for release (Eric Gardner)

## Styles
- Remove property with deprecated value `break-word` (Volker E)
- Tabs, styles: Make Tab list styles more specific (Volker E)
- Tabs, styles: Don't let external list item `margin`s bleed in (Volker E)
- Message, styles: Replace SFC variable with design token (Volker E)
- Link, docs: Apply design fixes (Volker E)
- styles: Replace SFC variables with design-first size tokens (Volker E)
- styles: Replace SFC variables with design-first spacing tokens (Volker E)
- Apply `line-height` design-first tokens (Volker E)
- MenuItem: Reset line-height to @line-height-x-small (Anne Tomasevich)
- Message: Replace SFC var and align content vertically (Volker E)
- CdxTextInput: error status uses progressive palette for focus (Julia Kieserman)
- styles, docs: Standardize Less mixin parameters naming (Volker E)
- styles: Replace and remove obsolete SFC variables (Volker E)
- styles, Menu: Move footer border-top to the Menu component (Anne Tomasevich)
- styles: Use consistently `list-style` shorthand (Volker E)
- styles: Remove `z-index` option token usage (Volker E)
- tokens, styles: Add `size` and `spacing` design-first tokens (Volker E)
- tokens: Amend design-first `line-height` tokens to latest (Volker E)
- tokens: Put deprecated tokens at the bottom of CSS/Less/Sass output (Roan Kattouw)
- tokens: Replace `max-width.breakpoint` token refs with `size.absolute-1` (Volker E)
- tokens, Button: Rename to `background-color-button-quiet*` tokens (Volker E)
- tokens: Add `spacing-400` token (Volker E)
- tokens: Add `spacing-30` and `spacing-35` decision tokens and apply (Volker E)
- tokens: Introduce `font-family` (legacy) tokens (Volker E)
- tokens: Don't output theme tokens in CSS/Less/Sass output (Roan Kattouw)

## Code
- Dialog: Defer access to window.innerHeight until mount time (Roan Kattouw)
- Dialog: Ensure border-box is used for layout (Eric Gardner)
- Icon, docs: Move `fill: currentColor` to CSS (Volker E)
- build: Support HMR for public mixins in the docs site (Roan Kattouw)
- build: Set Style Dictionary `basePxFontSize` to 16px for all web outputs (Volker E)
- Build: Bundle component demos using Vite library mode (Eric Gardner)
- build: Expand Stylelint 'properties-order' property list (Volker E)
- build: Update root '.editorconfig' and remove single package one (Volker E)
- build: Change Stylelint configuration file format to JS (Volker E)
- build, styles: Introduce 'stylelint-declaration-strict-value' plugin (Volker E)
- build: Updating stylelint to 14.14.0 (libraryupgrader)
- build: Actually eslint files in hidden directories (Roan Kattouw)
- build: Update codex package to TypeScript 4.8.2 (Roan Kattouw)
- build: Enable TypeScript in the tokens package (Roan Kattouw)
- build: Update 'stylelint-*' packages and 'postcss-html' (Volker E)
- build: Update 'node' and 'npm' versions in 'engine' (Volker E)
- Upgrade vue-tsc to a more recent version (Eric Gardner)

## Documentation
- docs, Link: Add page for the Link mixin (Anne Tomasevich)
- docs, Link: Add wrapper class to code sample (Anne Tomasevich)
- docs: Fix typo and expand abbreviations (Volker E)
- docs: Reset VitePress' opinionated anchor styles (Volker E)
- docs: Use <style lang="less">, not <style>, for Less blocks (Roan Kattouw)
- docs: Deduplicate results from Wikidata in demos (Michael Große)
- docs, Menu: Add more docs on the new footer prop (Anne Tomasevich)
- docs: Remove obsolete token comment in Thumbnail (Volker E)
- docs: Update opacity token demo (Volker E)
- docs: Expand explanation on component tokens (Volker E)
- docs: Replace calculation with spacing token (Volker E)
- docs: Add dir to controls, and apply to every component page (Anne Tomasevich)
- docs: Fix direction control for configurable Dialog demo (Anne Tomasevich)
- docs: Add extra padding to the bottom of the demo pane (Anne Tomasevich)
- docs, styles: Replace non-existent token for nav spacing (Anne Tomasevich)

# 0.2.2 / 2022-10-25
## Features
- Link: implement Codex link styles as Less mixins (Eric Gardner)
- Link: Make selectors flexible (Roan Kattouw)

## Styles
- Combobox, styles: Fix expand button icon size (Volker E.)
- Dialog, styles: Use design spec `transition` tokens (Volker E.)
- Tabs, styles: Remove obsolete `@text-decoration-none` SFC var (Volker E.)
- ToggleSwitch, styles: Fix disabled label color (Volker E.)
- ToggleButtonGroup, styles: Combine focus shadow and white border shadow (Roan Kattouw)
- styles, components: Replace falsely applied or deprecated tokens (Volker E.)
- styles: Use `outline` override on all of `:focus` (Volker E.)
- styles, docs: Apply `code` styling (Volker E.)
- styles, docs: Replace static `text-decoration` values with Codex tokens (Volker E.)
- tokens: Add comments to legacy opacity icon tokens on color equivalents (Volker E.)
- tokens: Amend `background-color-framed--active` (Volker E.)
- tokens: Add design-first `line-height` tokens (Volker E.)
- tokens: Deprecate component `background-color-primary*` tokens (Volker E.)
- tokens: Add design-first `text-decoration` tokens (Volker E.)
- tokens, docs: Replace deprecated background-color-primary token (Volker E.)
- tokens, docs: Replace deprecated design tokens (Volker E.)
- tokens: Use proper theme reference to `text-decoration` values & demo (Volker E.)
- tokens: Add design-first Dialog backdrop background color tokens (Volker E.)

## Code
- Combobox: bubble up the `load-more` event (Noa wmde)
- Dialog: Introduce Dialog component, useResizeObserver (Eric Gardner)
- Dialog, docs: Clarify Safari hack documentation (Volker E.)
- Dialog: MVP Design Fixes (Eric Gardner)
- LookupFetchDemo: refactor to be more extensible (Michael Große)
- Menu: Add configurable scroll behavior (Michael Große)
- Menu: Emit 'load-more' event when close to the end (Michael Große)
- Select: bubble up the `load-more` event (Noa wmde)
- TextInput: Suppress keydown events for Home/End (Roan Kattouw)
- TypeaheadSearch: adjust to support configurable scroll & load more (Noa wmde)
- TypeaheadSearch: Allow customization of autocapitalize attribute (Jon Robson)
- TypeaheadSearchWikidataDemo: refactor to make fetch more reusable (Michael Große)
- TypeaheadSearchWikidataDemo: add infinite scroll behavior (Michael Große)
- build: Allow async/await in TypeScript and Vue code (Roan Kattouw)
- build: Point docs tsconfig to lib-wip.ts, not the .d.ts file (Roan Kattouw)
- build: Disable Cypress tests for now (Roan Kattouw)
- build: Exclude WIP code from test coverage threshold (Roan Kattouw)
- build: Enable stylelint for Markdown files (Volker E.)
- build: Bump .nvmrc to node v16.16.0 (Volker E.)
- build: Move Vite plugin for copying mixins into its own file (Roan Kattouw)
- build: Restructure shared Vite config between codex and codex-search (Roan Kattouw)
- build: Convert Vite config files back to TypeScript (Roan Kattouw)
- tests: Add cypress browser test for Menu component scroll functionality (Michael Große)

## Documentation
- docs: Make top section headings consistent (Anne Tomasevich)
- docs: Add link to code review process (Anne Tomasevich)
- docs: Link to Codex docs on mediawiki.org (Anne Tomasevich)
- docs: Call out icons package in README (Anne Tomasevich)
- docs: Move generated components demos and add an overview page (Anne Tomasevich)
- docs: Fix WIP component detection in sidebar (Roan Kattouw)
- docs: Add links to “Maintainers” section (Volker E.)
- docs: Update WIP components docs (Roan Kattouw)
- docs: Add contact section to “About” (Volker E.)
- docs: Rename navigation label to “Architecture Decisions” (Volker E.)
- docs: Add note on what a 'breaking change' means (Volker E.)
- docs: Restructure Codex docs introduction page 'index.md' (Volker E.)
- docs: Use note for invisible label (Volker E.)
- docs: Unset link styles (Anne Tomasevich)
- docs, Menu: Separate menu scroll into its own demo (Anne Tomasevich)
- docs: Clean up Menu example files (Anne Tomasevich)
- docs, Menu: Improve display of number input (Anne Tomasevich)
- docs: Document types and constants (Anne Tomasevich)
- docs: Add “Designing Icons” to “Contributing” section (Volker E.)
- docs: Reposition deprecation “tag” of design tokens (Volker E.)
- docs, Dialog: Add Dialog types to docs page (Anne Tomasevich)
- docs: Add visibleItemLimit to MenuConfig docs (Anne Tomasevich)
- docs: Add more scrolling demos and load-more docs (Anne Tomasevich)
- docs: Add a link to usage bidirectionality support to all icons list (Volker E.)
- docs: Use Card styles (and its design tokens) on footer pager cards (Volker E.)

# 0.2.1 / 2022-09-13

## Styles
- tokens: Fix `background-color-quiet` and also deprecate (Volker E)

# 0.2.0 / 2022-09-13
## Features
- TypeaheadSearch: Expand input on menu open (Anne Tomasevich)
- TypeaheadSearch: Remove active class (Anne Tomasevich)

## Styles
- styles, docs: Use and document solely `--has-` and `--is-` prefixes (Volker E)
- tokens: Add 'maroon' color option token and Red Link component tokens (Volker E)

# Icons
- icons: Add 'palette' to collection (Volker E)
- icons: Minimize search icon (Thiemo Kreuz)

## Code
- build: Fix bug list steps to actually work (Roan Kattouw)
- build: Update TypeScript to 4.8 (Roan Kattouw)
- build: Update VitePress from 1.0.0-alpha.10 to 1.0.0-alpha.13 (Roan Kattouw)

## Documentation
- docs: ADR 04 - Visual styles as Less mixins (Eric Gardner)
- docs: Add `alt` attribute to docs logo (Volker E)
- docs: Add announcement to releasing docs (Anne Tomasevich)
- docs: Document WIP components and contribution pathways (Anne Tomasevich)
- docs: Fix landing page links (Anne Tomasevich)
- docs: Hide direction switcher (Anne Tomasevich)
- docs: Refactor site architecture (Anne Tomasevich)
- docs: Split contributing code docs into multiple pages (Anne Tomasevich)

# 0.1.1 / 2022-08-31

## Code
- build: Don't build .d.ts files for demos and WIP components (Roan Kattouw)
- build: Add bug list and LibraryUpgrader steps to RELEASING.md (Roan Kattouw)
- build: Skip diff-css.sh when not running in CI (Roan Kattouw)
- build: Upgrade Vite to v3.0.9 (Roan Kattouw)

# 0.1.0 / 2022-08-30

## Features
- Lookup: When input is empty, clear pending state, and don't reopen menu (Roan Kattouw)
- ButtonGroup: Use box-shadow instead of border between disabled buttons (Roan Kattouw)

## Styles
- ButtonGroup: Increase z-indexes to avoid using z-index: -1; (Roan Kattouw)
- styles, Tabs: Don't emphasise being clickable on already selected Tab (Volker E)
- styles, Card: Unset text-decoration on focus (Anne Tomasevich)
- styles, docs: Rename and clarify icon-wrapper-padding mixin (Volker E)
- styles, docs: Expand on pending-state mixin usage and replace vars (Volker E)
- styles, demo: Use Codex breakpoint token (Volker E)
- styles, docs: Improve more styles after the VitePress update (Anne Tomasevich)
- styles: Unify on `cdx-mixin-` Less mixin prefix (Volker E)
- tokens: Add small top and start box-shadow decision tokens (Volker E)
- tokens: Add design-first breakpoints tokens (Volker E)

## Code
- types: Export MenuState type, reorder types in lib.ts (Roan Kattouw)
- build: Add separate entry point for components in development (Roan Kattouw)
- tests: Reorganize Checkbox tests per new standards (Anne Tomasevich)
- tests: reorganize Lookup tests per new standards (Anne Tomasevich)

## Documentation
- docs, Thumbnail: Update "placeholder" language (Anne Tomasevich)
- docs: Don't error when a component-demos .md file doesn't exist (Roan Kattouw)
- docs: Use TypeScript for VitePress config and theme files (Roan Kattouw)
- docs: Use better TypeScript types for vue-docgen templates (Roan Kattouw)
- docs: Use IconLookup component for Select's defaultIcon prop (Anne Tomasevich)
- docs: Flag development components, hide them in release docs (Roan Kattouw)
- docs: Standardize JEST unit test names and structure (Simone This Dot)
- docs, tokens: Show deprecated tag even if there is no token demo (Roan Kattouw)
- docs, tokens: Exclude breakpoint tokens from the size token docs (Roan Kattouw)
- docs: Reword alpha warning (Roan Kattouw)
- docs: Update VitePress (Anne Tomasevich)
- docs: Remove VitePress list style in the demo pane (Anne Tomasevich)

# 0.1.0-alpha.10 / 2022-08-16

## Features
- TypeaheadSearch: Open menu on new results, even if empty (Roan Kattouw)
- ButtonGroup: Initial implementation (Roan Kattouw)
- ToggleButtonGroup: Initial implementation (Roan Kattouw)
- DirectionSwitcher: Use ToggleButtonGroup now that it exists (Roan Kattouw)
- ButtonGroup: Add overflowing demo, fix styling (Roan Kattouw)
- ToggleButtonGroup: Add maximum example, icon-only example (Roan Kattouw)
- ButtonGroup, ToggleButtonGroup: Straighten white lines between buttons (Roan Kattouw)
- ButtonGroup: Apply rounded corners to groups, not buttons (Roan Kattouw)
- icons: Update icons to the latest optimizations (Volker E)
- CopyTextButton: Use Clipboard API when available to copy code (Abijeet Patro)
- icons: Update 'info' icon to newest design (Volker E)

## Styles
- styles, tokens: Replace SFC `border-color` tokens (Volker E)
- styles, tokens: Introduce `border-color-subtle` and replace SFC token (Volker E)
- styles: Remove SVG title from background image (Volker E)
- styles, Card: Add background color (Anne Tomasevich)
- tokens, styles: Add further cursor tokens on theme option and base level (Volker E)
- tokens, demos: Mark deprecated tokens loud and clear (Volker E)
- tokens: As demo features “Deprecated” prefix now, don't repeat yourself (Volker E)
- tokens, demos: Put deprecated tokens always at bottom (Volker E)
- tokens: Use design-first Color decision tokens (Volker E)
- tokens: Use design-first Border Color decision tokens (Volker E)
- tokens: Amend `color-notice` value (Volker E)
- tokens: Amend `modifier-gray200-translucent` value (Volker E)
- tokens: Use design-first Background Color decision tokens (Volker E)
- tokens, styles, ToggleSwitch: Cleanup tokens and styles applied (Volker E)

## Code
- Tabs: Improve tests (Roan Kattouw)
- Re-organize and improve component sandbox page (Eric Gardner)
- build: Update Vue from 3.2.33 to 3.2.37 (Roan Kattouw)
- build: Upgrade eslint to 0.23.0 and make pass (Roan Kattouw)
- build: Run build-all-if-missing in "npm coverage" (Roan Kattouw)
- build: Publish the sandbox alongside Netlify deployment previews (Roan Kattouw)
- build: Add script to generate a CSS diff for a change (Roan Kattouw)
- build: Run diff-css.sh in npm test (Roan Kattouw)
- build: Add "style" field to package.json (Roan Kattouw)
- build: Make Vite port configurable, listen on all IPs (Roan Kattouw)

## Documentation
- docs: Add links to task templates and explain component scoping process (Anne Tomasevich)
- docs, utils: factor out getIconByName() utility (DannyS712)
- docs: Clarify how search query highlighting works (Anne Tomasevich)
- docs, component demos: add getEventLogger() utility (DannyS712)
- docs, Controls: simplify splitting of props and slot controls (DannyS712)
- docs, tests: add relevant types, nonexistent keys Thumbnail objects (DannyS712)

# 0.1.0-alpha.9 / 2022-07-28

## Features
- Button: Add full support for icon-only buttons (Simone This Dot)
- Thumbnail: Add Thumbnail component (Anne Tomasevich)
- MenuItem: Use the Thumbnail component (Anne Tomasevich)
- icons: Add 'copy'/'cut'/'paste' (Volker E)
- TypeaheadSearch: Remove space when no-results slot is empty (Steven Sun)
- Card: Add initial Card component (Anne Tomasevich)
- Menu: Add Home/End keyboard button support (Simone This Dot)
- TypeaheadSearch: Remove border-top on footer when it's the only menu item (Steven Sun)
- Tabs: Make icon-only scroll buttons `aria-hidden` (Anne Tomasevich)

## Styles
- styles, ProgressBar: Fix border radius overflow in Safari (Anne Tomasevich)
- styles, Checkbox, ToggleSwitch: Simplify state styles hierarchy (Volker E)
- styles, TypeaheadSearch: Correct padding of footer's icon (Simone Cuomo)
- tokens: Introduce `box-shadow-color` decision tokens (Volker E)
- tokens: Replace legacy `@box-shadow` tokens with new combination tokens (Volker E)
- tokens: Add new typographic system fonts to stack (Volker E)

## Code
- MenuItem: Remove unused tokens (Anne Tomasevich)
- Thumbnail: clean up testThumbnail in tests (DannyS712)
- Button: Improve icon-only button detection, and add tests (Roan Kattouw)
- Button: Ignore whitespace-only text nodes when detecting icon-only-ness (Roan Kattouw)
- Button: Unify on “icon-only” label for that type (Volker E)
- build: Update 'style-dictionary' to latest v3.7.1 (Volker E)
- build: Update package-lock.json for style-dictionary upgrade (Roan Kattouw)
- build: Update 'less', 'postcss*' and 'autoprefixer' dependencies (Volker E)
- Build: Update netlify-cli, update minimist vulnerability (Eric Gardner)
- build: gitignore .vscode directory for settings files (Anne Tomasevich)
- build: Update netlify-cli to v10.10.2 (Roan Kattouw)

## Documentation
- docs: Clarify commit message category order (Volker E)
- docs: Expand on marking deprecating and breaking changes in commit msg (Volker E)
- docs, Controls: reduce duplication using `<component>` and `:is` (DannyS712)
- docs: Correct language about deprecating/breaking change prefix (Roan Kattouw)
- docs: Update CSS class names on tokens demos (Volker E)
- docs: Update border-radius-pill demo (Volker E)
- docs, composables: factor out useCurrentComponentName() composable (DannyS712)
- docs: Guide users to the repo (Kosta Harlan)
- docs: Separate search-no-results-text in TypeaheadSearch demo (Steven Sun)
- docs: Add step to releasing docs to document breaking changes (Anne Tomasevich)

# 0.1.0-alpha.8 / 2022-06-23

## Breaking changes
- refactor: Fix inconsistencies across components with menu items (Simone This Dot)

## Features
- Menu: Highlight the selected item when menu is opened (Anne Tomasevich)
- Menu, Typeahead: Apply MenuItem selected styles to Menu footer (Simone This Dot)
- TextInput: Remove focus tracking state, replace with --has-value class (Roan Kattouw)
- MenuItem: Remove highlighted and active styles on mouseleave (Anne Tomasevich)
- ToggleSwitch: Component is squashed when it has a long default label (Simone This Dot)
- Combobox: Apply `aria-hidden` on button (Volker E)
- MenuItem: Display placeholder thumbnails before images are loaded (Simone This Dot)
- MenuItem: Align icon and thumbnail to top with description (Anne Tomasevich)
- MenuItem: Reduce transition duration of thumbnail (Anne Tomasevich)
- Menu: Refine `active` binding of default slot (Anne Tomasevich)

## Styles
- styles, buttons: Unify whitespace and property order (Volker E)
- Select, TextInput, styles: Unify `outline` values (Volker E)
- styles, TextInput: Add border width to icon position offset values (Anne Tomasevich)
- Button, styles: Reorder and cleanup focus styles (Volker E)
- Button, styles: Removing default button `:active` selector (Volker E)
- styles, ToggleSwitch: Unify applying pointer `cursor` (Volker E)
- styles: Apply design-first `box-shadow` tokens (Volker E)
- styles, TextInput: Use `min-height-base` instead of `height-base` (Volker E)
- styles, TypeaheadSearch: Reduce footer font size (Anne Tomasevich)
- tokens: Fix quiet active background-color value (Volker E)
- tokens, styles: Use `transition-property-base` for ToggleSwitch focus (Volker E)
- tokens, styles: Replace `animation` property values with tokens (Volker E)
- tokens: Use design-first `box-shadow` tokens (Volker E)
- tokens: Add `transition-property-toggle-switch-grip` token and apply (Volker E)

## Code
- tests: Fix Tabs down arrow test by using `attachTo: 'body'` (Roan Kattouw)
- build: Lower target browsers to include Edge 18 (Volker E)
- build: Update Vue to 3.2.33 (Anne Tomasevich)

## Documentation
- docs: Tidy up CHANGELOG a bit (DannyS712)
- docs: Expand on using Vite (Volker E)
- docs: Add 'AUTHORS.txt' (Volker E)
- docs: Add `aria-label` to slot and prop `input`s (Volker E)
- docs: Update intro and contributing guidelines (Anne Tomasevich)
- docs, IconLookup: Add `aria-label` to icon lookup props and slots (Anne Tomasevich)

# 0.1.0-alpha.7 / 2022-06-09

## Features
- Button, ToggleButton: Text overflow from button is larger than max width (Simone This Dot)
- Combobox: Remove useless tabindex="0" on the input (Roan Kattouw)
- Lookup: Update menu items when item is selected (Simone This Dot)
- Select: Remove arrow indicator direction change when menu is expanded (Volker E)
- Tabs: Add scroll buttons (Roan Kattouw)
- TextInput: Allow clear and end icons to coexist (Anne Tomasevich)
- TypeaheadSearch: Remove hover effect from button (Roan Kattouw)

## Styles
- Style: Refactor icon positioning in TypeaheadSearch using mixin (Simone This Dot)
- styles, docs: Enforce specific CSS properties over shorthand (Volker E)
- Tabs, styles: Consistently apply margins to `<a>` elements (Roan Kattouw)
- styles: Use consistent border-bottom on item with dropdown menus (Simone This Dot)
- Select, styles: Introduce `.cdx-select--enabled` class and align states (Volker E)
- TypeaheadSearch, styles: Fix auto-expand distance (Anne Tomasevich)
- MenuItem, TypeaheadSearch, styles: Fix link style overrides (Anne Tomasevich)
- MenuItem - Fix style for Menu with custom menu item (Simone This Dot)
- SearchResultTitle, styles: remove properties for consistency with MenuItem label (Anne Tomasevich)
- Tabs, tokens, styles: Use `rgba()` over transparent for background color (Volker E)
- tokens: Follow new color palette naming scheme for design-first tokens (Volker E)
- tokens: Use design-first `border` tokens (Volker E)

## Code
- useGeneratedId: no need to return a reference (DannyS712)
- useStringHelpers: export helpers directly instead of in a function (DannyS712)
- codex, utils: create directory and rename utils.ts and useStringHelpers.ts (DannyS712)
- utils, tests: add tests for stringTypeValidator.ts (DannyS712)
- build: Add codex-search package to prepare-release.sh (Roan Kattouw)
- build: Add a new script to simplify the creation of snapshot (Simone This Dot)

## Documentation
- docs: Clarify that Vue needs to be installed to use Codex (Roan Kattouw)
- docs, Wrapper: simplify highlighting of generated code (DannyS712)
- docs, Tabs: Update Tabs demos (Anne Tomasevich)
- docs: add generic configurable component for using v-model (DannyS712)
- docs, ConfigurableGeneric: `<component>` can be self closing (DannyS712)
- docs, Wrapper: minor simplification and cleanup (DannyS712)
- docs, SlotIcon: avoid `as` for typescript type of iconsByName (DannyS712)
- docs, Wrapper: include v-model in generated code sample (DannyS712)
- docs: Restructure tokens overview documentation and inter-link (Volker E)
- docs, sidebar: fix design tokens order (DannyS712)
- docs, SizeDemo: document css property values, simplify (DannyS712)
- docs, codegen: default slot never requires `<template>` wrapper (DannyS712)
- docs, Button: remove a number of selection demo variations (DannyS712)
- docs, CdxDocsFontDemo: remove unneeded less import (DannyS712)
- docs, RELEASING: update explanation of creating tag patch (DannyS712)
- docs, tests: add tests for ConfigurableGeneric (DannyS712)
- docs: Remove link override styles for demos (Anne Tomasevich)
- docs: separate 'default' property values from initial values to use (DannyS712)
- docs: Expand “ADRs” menu label slightly (Volker E)
- docs, tokens: add generic CdxDocsTokenDemo for demonstrations (DannyS712)
- docs, tokens: deduplicate styles in CdxDocsTokenDemo (DannyS712)
- docs, MenuItem: add configurable menu item demo (DannyS712)
- docs: Add instructions for updating MediaWiki to RELEASING.md (Roan Kattouw)
- docs: Clarify browser support (Volker E)
- Combobox, docs: Add disabled demo (Roan Kattouw)

# 0.1.0-alpha.6 / 2022-05-12

## Features
- Lookup: Use pending and focus states to decide whether to open the menu (Roan Kattouw)
- Menu, TypeaheadSearch: Add inline progress bar (Anne Tomasevich)
- Menu, TypeaheadSearch: Remove selectHighlighted prop (Eric Gardner)
- Menu: Change footer slot to no-results (Anne Tomasevich)
- Menu: Fix keyboard navigation after expanding menu by click (Steven Sun)
- MenuItem: Reorganize and improve color styles (Anne Tomasevich)
- MenuItem: Support language attributes (Anne Tomasevich)
- Message: Update component to meet design spec (Anne Tomasevich)
- Message: Add auto-dismiss functionality and improve demos (Anne Tomasevich)
- ProgressBar: add progress bar component with indeterminate state (DannyS712)
- ProgressBar: add inline variant (Anne Tomasevich)
- Tabs: Introduce Tab and Tabs components, useIntersectionObserver (Eric Gardner)
- ToggleButton: add quiet type (DannyS712)

## Styles
- binary inputs, styles: Fix hover cursor behavior (Volker E)
- tokens, Button: Fix applied quiet progressive border token (Volker E)
- Checkbox: Don't apply checked styles to indeterminate inputs (Anne Tomasevich)
- Checkbox: Vertically center indeterminate icon (line) (Volker E)
- MenuItem: Update thumbnail styles (Anne Tomasevich)
- Message: Use opacity-transparent token now that it exists (Roan Kattouw)
- Message: Fix mobile padding and transition styles (Anne Tomasevich)
- ProgressBar: Update indeterminate animation (Anne Tomasevich)
- SearchInput: Fix border radius and button border behavior (Anne Tomasevich)
- Tabs: Fix broken header hover styles in Chrome (Eric Gardner)
- Tabs: Adjust styles to follow design and simplify selector logic (Volker E)
- Tabs: Apply correct borders on hover/active (Roan Kattouw)
- Tabs, styles: Add `frameless` variant CSS class (Volker E)
- Tabs, styles: Rename 'frameless' to 'quiet' (Volker E)
- TextInput: Update TextInput styles to match design spec (Anne Tomasevich)
- ToggleButton: Update quiet styles and make focusable (Roan Kattouw)
- TypeaheadSearch, style: Remove border-top for no-results text (Steven Sun)
- styles: Introduce `screen-reader-text()` mixin (Volker E)
- styles: Add `.text-overflow()` mixin and use in MenuItem (Volker E)
- styles: Add `hyphens` mixin and apply (Volker E)
- styles: Use CSS 3 notation for pseudo-elements (Volker E)
- styles: Don't use transition-duration: @transition-base (Roan Kattouw)
- styles: Use comment style consistently (Volker E)
- styles: Centralize "start icon padding" style logic (Simone This Dot)
- styles: Replace obsolete notation of keyframes (Volker E)
- tokens, ToggleSwitch: Remove `box-shadow-input-binary` (Volker E)
- tokens: Add token type to JSON attributes (Roan Kattouw)
- tokens: Don't refer to theme tokens in deprecation comments (Roan Kattouw)
- tokens: Use correct color in 'modifier-base80-translucent' (Volker E)
- tokens: Add legacy `opacity` tokens (Volker E)
- tokens: Add `0.30` valued opacity token and update naming (Volker E)
- tokens: Use 'user' as name for human initiated timing function token (Volker E)
- tokens: Update `border-radius` design-first tokens (Volker E)
- tokens: Add `@position-offset-border-width-base` (Anne Tomasevich)
- tokens: Remove conflicting token comment (Anne Tomasevich)
- tokens, styles: Add design-first transition tokens (Volker E)
- tokens: Add design-first animation tokens (Volker E)

## Code
- Button: simplify rootClasses definition (DannyS712)
- Combobox: simplify onInputBlur() logic (DannyS712)
- Combobox: don't retrieve the entire context for setup() (DannyS712)
- Checkbox, Radio: remove unneeded !! for boolean props (DannyS712)
- Menu: Add global event listener for mouseup to clear active (Anne Tomasevich)
- Menu: simplify handleKeyNavigation() cases (DannyS712)
- Menu: simplify highlightPrev() with early return (DannyS712)
- MenuItem: Only set active state on main mouse button mousedown (Anne Tomasevich)
- ProgressBar: Set height on root element (Anne Tomasevich)
- Select: Apply design review feedback (Simone This Dot)
- Select: document why `return undefined` is needed in computing start icon (DannyS712)
- TypeaheadSearch: Add snapshot for "no results" message (Anne Tomasevich)
- TypeaheadSearch: Don't use refs for timeout handles (Roan Kattouw)
- Components: don't retrieve the entire context for setup() (DannyS712)
- useIntersectionObserver: Make reactive to templateRef changing (Roan Kattouw)
- useIntersectionObserver: Don't observe elements before they're mounted (Roan Kattouw)
- useModelWrapper: Support typed event parameters (Roan Kattouw)
- build: Add shell script for preparing a release (Roan Kattouw)
- build: Add "npm run coverage" command (Roan Kattouw)
- build: Enable type checking rules for typescript-eslint (Roan Kattouw)
- build: Upgrade TypeScript 4.4.3 -> 4.6.2 (Roan Kattouw)
- build: Upgrade vue-tsc 0.28.3 -> 0.33.6 (Roan Kattouw)
- build: Disable "restrict-template-expressions" linting rule in tests (Eric Gardner)
- build: Upgrade postcss-rtlcss 3.5.1 -> 3.5.4 (Roan Kattouw)
- build: Check .js files with TypeScript in the Codex package (Roan Kattouw)
- build: Check .js with TypeScript in the codex-docs package (Roan Kattouw)
- build: Use rtlcss to generate codex.style-rtl.css, by running Vite twice (Roan Kattouw)
- build: Upgrade eslint and its plugins (Roan Kattouw)
- build: Upgrade @vue/test-utils and use VueWrapper correctly (Roan Kattouw)
- build: Type check the VitePress config (Roan Kattouw)
- build: Increase stylelint max-nesting-depth to 3 (Anne Tomasevich)
- build: Put icon type definitions in dist/types (Roan Kattouw)
- build: Use vue-tsc to generate type definitions (Roan Kattouw)
- build: Add the @wikimedia/codex-search package (Roan Kattouw)
- build: Update 'browserslist-config-wikimedia' to v0.4.0 (Volker E)
- build: Export all composables (Catrope)
- build: Enable stylelint in hidden directories (Roan Kattouw)
- build: Actually make stylelint work in the .vitepress/ directory (Roan Kattouw)
- build: Update 'stylelint' and 'stylelint-config-wikimedia' to latest (Volker E)
- build: Remove useless eslint-disable (Roan Kattouw)
- lib: Don't export the TabData type (Roan Kattouw)
- docs, tests: add dedicated tests for CopyTextButton (DannyS712)
- tests: add tests for flattenDesignTokensTree() method (DannyS712)

## Documentation
- docs, Wrapper: minor cleanup and organization (DannyS712)
- docs, CopyTextButton: improvements to success logic (DannyS712)
- docs: Add example usage of useComputedDir() (Roan Kattouw)
- docs: Add button examples with icons (Roan Kattouw)
- docs, TokensTable: import missing CdxDocsCursorDemo component (DannyS712)
- docs, Wrapper: Add dynamic sample code generation with controls (DannyS712)
- docs: Fix typo on `processKeyFrames` in postcss.config.js (Roan Kattouw)
- docs: Unbreak navigating away from component pages with generated code (Roan Kattouw)
- docs: Simplify breakpoint documenting sentences. (Volker E)
- docs, component.js: avoid unneeded template string interpolation (DannyS712)
- docs: avoid empty "Values" column for properties when unused (DannyS712)
- docs: Work around VitePress click handling behavior (Roan Kattouw)
- docs: Ensure generated code samples can handle self-closing tags (Anne Tomasevich)
- docs, Controls: don't show "slots" heading if there aren't any (Anne Tomasevich)
- TextInput: Add configurable demo (Anne Tomasevich)
- Wrapper: Revert changes to Wrapper styles (Eric Gardner)
- docs: Manually set link styles for Message demos (Anne Tomasevich)
- docs: Update CSS conventions (Anne Tomasevich)
- demo: Add ToggleButton, ToggleSwitch and Message to sandbox demo (Roan Kattouw)
- docs: Hide theme tokens in the tokens documentation (Roan Kattouw)
- docs: Use design tokens within codex-docs custom theme (Anne Tomasevich)
- docs, Wrapper: use ToggleButton for show/hide code (DannyS712)
- docs, changelog: Organize 'CHANGELOG.md' release notes (DannyS712)
- DirectionSwitcher: use ToggleButton for direction options (DannyS712)
- docs, codex: Remove 'wikimedia-ui-base' from codex package as well (Volker E)
- styles, docs: Use `lang="less" attribute for style block everywhere (Volker E)
- docs: Allow configuring placeholder text for TextInput demo (DannyS712)
- docs: Use v-bind for boolean `forceReset` prop (Anne Tomasevich)
- docs, Menu: Remove outdated slot (Anne Tomasevich)
- docs: Set VitePress text color to `color-base` (Anne Tomasevich)
- docs: Allow configuring icon properties and generate correct code (DannyS712)
- ToggleButton: Add icon-only demo (Roan Kattouw)
- docs: Allow configuring icons used as slot contents (DannyS712)
- docs: Add paragraph about dealing/organizing 'CHANGELOG.md' (Volker E)
- docs: move more code generation logic from Wrapper.vue to codegen.ts (DannyS712)
- docs, styles: Improve interaction of code button borders (Anne Tomasevich)


# 0.1.0-alpha.5 / 2022-03-15
## Features
- Replace useMenu composable with Menu component (Roan Kattouw)
- MenuItem: Change Option to MenuItem (Anne Tomasevich)
- Menu, MenuItem: Add menuConfig, enable boldLabel & hideDescriptionOverflow (Anne Tomasevich)
- MenuItem: Merge in ListTile and reflect updated designs (Anne Tomasevich)
- ToggleButton: add ToggleButton component (DannyS712)
- SearchInput: Add the SearchInput component (Anne Tomasevich)
- build, tokens: Add deprecation functionality to tokens (Volker E.)

## Styles
- Button, styles: Replace attribute with `:enabled`/`:disabled` pseudo classes (Volker E.)
- Combobox, styles: Replace menu styles with `options-menu` mixin (Volker E.)
- Checkbox, Radio, styles: Unify enabled and disabled CSS logic and fix `:active` (Volker E.)
- Button, styles: Remove Button `:focus` outline reset (Volker E.)
- TextInput, styles: Replace attribute with `:enabled`/`:disabled` pseudo classes (Volker E.)
- ToggleSwitch, styles: Unify disabled and enabled CSS logic (Volker E.)
- ToggleSwitch, styles: Remove unused `margin-left` transition (Roan Kattouw)
- styles: Fix `transform` value on center aligned menu item (Volker E.)
- styles: Add button styles mixin to avoid style duplication (DannyS712)
- styles: Remove element selectors (Volker E.)
- Lookup, tokens: Make Lookup component use Codex tokens (Volker E.)
- Message, tokens: Make Message component use Codex tokens (Volker E.)
- Select, tokens: Make Select component use Codex tokens (Volker E.)
- Combobox, tokens: Make Combobox component use Codex tokens (Volker E.)
- Button, tokens: Make Button component use Codex tokens (Volker E.)
- TextInput, tokens: Use `transition-property-base` (Volker E.)
- ListTile, ListTileLabel, tokens: Make ListTile components use Codex tokens (Volker E.)
- Checkbox, Radio, tokens: Make binary input components use Codex tokens (Volker E.)
- ToggleSwitch, tokens: Make toggle switch component use Codex tokens (Volker E.)
- TypeaheadSearch, tokens: Make typeahead search component use Codex tokens (Volker E.)
- styles: Use common file for non-component specific mixins (Volker E.)
- styles: Fix fixed transform on Combobox use of 'menu-icon.less' (Volker E.)
- tokens: Add `transition-property.base` and `.icon` (Volker E.)
- tokens: Explain usage of `position.offset` tokens (Volker E.)
- tokens: Add `color` and `border-color` for message components & validation (Volker E.)
- tokens: Add `margin-top.options-menu` for Options menu (Volker E.)
- tokens: Add binary components specific tokens (Volker E.)
- tokens: Remove `border-radius-rounder` (Volker E.)
- tokens: Add `border-binary-input` shorthand (Volker E.)
- tokens: Add `cursor` property tokens (Volker E.)
- styles, tokens: Replace SFC `cursor` tokens with Codex design tokens (Volker E.)
- tokens: Convert remaining deprecated tokens to new style (Roan Kattouw)
- tokens: Move `color-primary` from base to components (Volker E.)
- tokens: Add `margin-offset-border-width-base` and remove menu component token (Volker E.)
- icons: Skew 'italic-arab-keheh-jeem' and bolden 'bold-arab-dad' icons (Volker E.)

## Code
- Combobox: Remove superfluos `aria-disabled` attribute (Volker E.)
- Select: Set `aria-multiselectable="false"` (Roan Kattouw)
- Lookup: Simplify code (Roan Kattouw)
- useMenu: Remove inputValue feature, replace with updateSelectionOnHighlight (Roan Kattouw)
- useMenu: Remove footerCallback feature (Roan Kattouw)
- TypeaheadSearch: Simplify input change handling (Anne Tomasevich)
- Menu: Fix selectedValue documentation rendering (Roan Kattouw)
- binary inputs: Remove `aria-disabled` overtaken by input's `disabled` (Volker E.)
- binary-input: Remove use of `[ class$='...' ]` selector (Roan Kattouw)
- build: Removing remaining references to 'WikimediaUI Base' and uninstall (Volker E.)
- build: Add "npm run build-all" command, clean up other commands (Roan Kattouw)
- build: Explicitly set stylelint to modern support (Volker E.)
- build: Require all CSS classes to start with `cdx-` (Roan Kattouw)
- build: Update Stylelint packages to latest (Volker E.)
- build: Update 'style-dictionary' to latest (Volker E.)
- build: Enable eslint in hidden directories (Roan Kattouw)
- build, tokens: Make style-dictionary config.js config-only (Roan Kattouw)

## Documentation
- docs: Make tokens table copy button quiet again (Anne Tomasevich)
- demo: Use ToggleSwitch for boolean props in controls (Anne Tomasevich)
- docs: Restructure and provide more details on SVG optimization (Volker E.)
- docs: Standardize on cdx-docs prefix (Anne Tomasevich)
- docs: Normalize component demo formatting and language (Anne Tomasevich)
- docs: Use kebab-case for component names in *.md files (Roan Kattouw)
- docs: Add import statement to imported code snippet example (Roan Kattouw)
- docs: Rename `<Wrapper>` to `<cdx-demo-wrapper>` (Roan Kattouw)
- docs: Replace WikimediaUI Base with Codex design tokens reference (Volker E.)
- docs: Overwrite VitePress theme default html, body font size to `initial` (Volker E.)
- docs: Improve generated events and methods docs (Anne Tomasevich)
- docs, Controls.vue: remove unneeded uses of `<template>` wrappers (DannyS712)
- docs: Use Special:MyLanguage for Code of Conduct link (DannyS712)
- docs: Change "a Code of Conduct" to "the Code of Conduct" (Roan Kattouw)
- docs: Improve demos of components that use menus (Anne Tomasevich)
- docs: Set dir="ltr" on all non-component docs pages (Roan Kattouw)
- docs, ToggleButton: remove unneeded `ref` import from markdown page (DannyS712)
- docs: Normalize to writing “Less” (Volker E.)
- docs, Wrapper: add a "reset" button (DannyS712)
- docs, Wrapper: add a "copy" button for code samples (DannyS712)


# v0.1.0-alpha.4 / 2022-02-18
## Styles
- tokens: Fix `background-color-framed--hover` to set to `#fff` (Volker E.)
- tokens: Update input padding token to match WMUI value (Anne Tomasevich)

## Code
- build: Add 'branch-deploy' npm script, for WMF CI to call (Roan Kattouw)
- build: Bump .nvmrc to 16.9.1 (Roan Kattouw)
- build, icons: Rename LICENSE-MIT to LICENSE (Roan Kattouw)

## Documentation
- docs: Set CODEX_DOC_ROOT default to '/' not '' (James D. Forrester)
- docs: Explain that icons are monochrome, add SVG conventions (Roan Kattouw)
- docs: Make CODEX_DOC_ROOT default to / instead of /codex/main (Roan Kattouw)
- docs: Make VitePress base URL configurable as an environment variable (Roan Kattouw)
- docs: Explicitly set dir="ltr" on direction switcher (Roan Kattouw)

# v0.1.0-alpha.3 / 2022-02-17
## Features
- ToggleSwitch: Add ToggleSwitch component (Anne Tomasevich)
- TypeaheadSearch: Add `auto-expand-width` prop (Nicholas Ray)
- TypeaheadSearch: Add initial iteration of TypeaheadSearch (Anne Tomasevich)

## Styles
- TextInput, tokens: Make TextInput component use Codex tokens (Volker E.)
- tokens: Add 'input' and 'input-binary' component 'border-color' tokens (Volker E.)
- tokens: Fix `background-color-base--disabled` value (Volker E.)
- tokens: Add 'size-indicator' (Volker E.)
- icons, license: Set to MIT license (Volker E.)

## Code
- build: Change icons CJS build to UMD (Roan Kattouw)
- build, styles: Add further properties to 'stylelint-order' & align code (Volker E.)
- build: Update package-lock.json (Roan Kattouw)
- build: Enable safeBothPrefix for postcss-rtlcss (Roan Kattouw)
- build: Change browserslistrc to `modern-es6-only` (Lucas Werkmeister)
- build: Turn on 'lint:eslint' for JSON configuration files (Volker E.)
- build: Remove trailing whitespace from Codex's README.md (Roan Kattouw)
- build: Update 'package-lock.json' (Lucas Werkmeister)


# v0.1.0-alpha.2 / 2022-02-14
## Code
- build: Un-pin postcss, update to 8.4.6 (Roan Kattouw)
- build: Add LICENSE files to each package (Roan Kattouw)
- build: Copy SVGs to dist/images at the right time (Roan Kattouw)

## Documentation
- docs: Add a README.md file for the Codex package (Roan Kattouw)

# v0.1.0-alpha.1 / 2022-02-14
- Initial release

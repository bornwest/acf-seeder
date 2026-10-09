# ACF Seeder

Seeds posts, pages, ACF options pages and their field values (including images and files) from JSON files that live in the **active theme**, via a **Seed Content** page in the WordPress admin sidebar. Requires Advanced Custom Fields Pro.

Seeding is idempotent: posts are matched by post type + slug and updated in place, images are matched by file name and uploaded once. Existing posts with the same slug are **overwritten**.

## Seed files (in the theme)

```
<theme>/seeds/
  data/
    <plural-name>/<slug>.json   one post per file, e.g. faqs/, team-members/
    pages/<slug>.json           one page per file
    options/<slug>.json         one ACF options page per file
  files/                        non-image files (PDFs etc.) referenced from the JSON
  images/                       images referenced from the JSON
```

The folder name maps to a post type by matching the post type name or its plural label (`team-members` → the "Team Members" post type, `team-member`). Post types must be registered (ACF JSON post types are fine). Post types are seeded first so pages can reference them, then pages, then options. `pages` and `options` are reserved folder names.

Override the location with the `acf_seeder_dir` filter (absolute path, no trailing slash).

### File format

```json
{
  "slug": "jane-doe",
  "title": "Jane Doe",
  "template": "page-templates/homepage.php",
  "group": "Template: Homepage",
  "fields": { }
}
```

- `template` — pages only; sets the page template.
- Options pages use the same format with no `template`; `group` is required (the options page's field group title). Values are written to ACF's shared `options` store, so read them with `get_field('name', 'option')`.
- `group` — optional; the title of the ACF field group to fill, or a list of titles to fill several groups. Without it, groups are found from the post type / page template location rules.
- `fields` — optional (omit for a blank post or page); values mirroring the *logical* ACF structure. Clone prefixes are resolved automatically, so a cloned `header` group is written as `"header": { "title": "…" }`. Repeaters are arrays of rows.

| Field type   | Value                                                        |
|--------------|--------------------------------------------------------------|
| image / file | `"file.jpg"` or `{ "file": "file.jpg", "alt": "…" }` (relative to `seeds/images`; the extension may be omitted, e.g. `"capability/fixed-income"`) |
| file         | `"doc.pdf"` (relative to `seeds/files`; extension may be omitted) |
| post_object  | post slug (or ID)                                            |
| link         | `{ "title": "…", "url": "…", "target": "" }`                 |
| true_false   | `true` / `false`                                             |

SVG images are accepted: SVG uploads are allowed only while the seeder imports a file (they are not enabled site-wide), so only seed SVGs you trust.

Warnings (unmatched field names, missing images or referenced posts) are shown on the Seed Content page after a run.

## Filters

- `acf_seeder_dir` — absolute path of the seeds directory.

## Structure

```
acf-seeder.php          plugin header, constants, bootstrap (admin only)
includes/
  class-seeder.php        picks what to seed, creates/updates posts
  class-source.php        reads the seed files
  class-field-mapper.php  seed JSON → ACF values
  class-asset-importer.php seeds/images and seeds/files → media library
  class-log.php           collects run messages
  class-admin-page.php    menu, form handler
views/admin-page.php
assets/admin-page.{css,js}
```

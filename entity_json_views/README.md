# Entity JSON Views

Exports Drupal Views (REST export displays with `data_field` row plugin) as
static JSON files.

## Requirements

- Drupal 9, 10, or 11
- Modules: `views`, `rest`, `serialization`

## Workflow

1. Create a View with a **REST export** display and configure the fields
   you want in the output using the `data_field` row plugin.
2. Go to `/admin/config/entity-json-views` and select which displays to export.
3. Run `drush hev-export` to generate the JSON files.

## Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/json/views/{view_id}/{display_id}` | Serves the exported JSON file |

Cache: `public, max-age=3600` with ETag and Last-Modified headers.

## Permissions

| Permission | Description |
|------------|-------------|
| `access entity json views` | Access View JSON routes |

## Drush

```bash
# Export all configured views
drush hev-export
```

## Storage

JSON files are stored at `public://views/{view_id}/{display_id}.json`.

The JSON output is exactly what the View's `data_field` row plugin produces:
a clean array of objects with only the fields you selected in the View UI,
using the aliases you configured.

## Creating a View for export

1. Go to `/admin/structure/views/add`
2. Choose your entity type and base fields
3. Add a **REST export** display
4. Configure the fields you want to include
5. Set the row plugin to `data_field` (not `data_entity`)
6. Configure field aliases and formats as desired
7. Save the View
8. Go to `/admin/config/entity-json-views` and enable the display
9. Run `drush hev-export`

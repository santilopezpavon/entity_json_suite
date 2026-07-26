# Headless Entity Serializer

Drupal module that serializes content entities into static JSON files on disk, for consumption by headless applications or Static Site Generators (SSG).

## Features

- **JSON Serialization**: Converts Drupal content entities (Nodes, Users, Taxonomy Terms, etc.) into JSON files.
- **Structured Storage**: Saves files to a configurable directory with a bucket-based structure organized by entity type, ID, and language code.
- **Full Regeneration**: Deletes all previously generated files and rebuilds them from scratch.
- **Incremental Update**: Processes only entities created, updated, or deleted since the last run.
- **Multilingual Support**: Generates separate JSON files for each translation of an entity.
- **Path Aliases**: Automatically exports path aliases into a bucket-based directory structure.

## Installation

```bash
ddev exec composer require drupal/headless_entity_serializer
ddev exec drush en headless_entity_serializer
```

## Configuration

Navigate to **Admin > Configuration > Entity Serialization Settings** (`/admin/config/headless-entity-serializer`):

1. **Destination Directory**: Path where JSON files will be saved (e.g., `public://exported_entities`). Drupal must have write permissions to this directory.
2. **Entity Types to Serialize**: Entity types to serialize as top-level JSON files.
3. **Entity Types to Serialize Inline**: Entity types to serialize as part of their referencing entity.

### Entity Types vs Entity Types Inline

Both options generate independent JSON files for each entity. The difference is **how the entity is discovered** for export:

| | Entity Types to Serialize | Entity Types to Serialize Inline |
|---|---|---|
| **JSON file** | `entity_type/id/langcode.json` | `entity_type/id/langcode.json` (same) |
| **Discovery** | Directly iterated by the generator | Discovered through entity reference fields of the parent |
| **Incremental update** | Processed independently | Only processed if the parent entity is also re-exported |

**Example:** A `paragraph` configured as *inline* gets its own JSON file (`paragraph/1.json`), but it is only exported when a parent `node` that references it is processed. If the paragraph changes and the parent does not, the paragraph's file will **not** be updated during an incremental run.

**Typical usage:** Use *Entity Types to Serialize* for main content types consumed directly by the frontend (`node`, `taxonomy_term`, custom ECK types). Use *Entity Types to Serialize Inline* for dependent entities that always live inside another entity (`paragraph`, `block_content`).

## Drush Commands

All commands are run from the project root inside the ddev container.

### Full Regeneration

Deletes all existing JSON files and regenerates them from scratch.

```bash
# All configured entity types
ddev exec drush hes-full

# A specific entity type
ddev exec drush hes-full node
```

Use when: first setup, after major content structure changes, or when you need a clean state.

### Incremental Update

Processes only entities modified since the last run. Recommended for production environments.

```bash
ddev exec drush hes-incremental
```

Use when: regular synchronization, e.g., as a cron job.

### Reset State

Forces the next incremental run to process all entities.

```bash
ddev exec drush hes-reset-state
```

## Generated File Structure

Files are saved under the configured destination directory:

```
[destination_directory]/
├── [entity_type]/
│   ├── [bucket]/               # bucket = floor(entity_id / 1000)
│   │   └── [entity_id]/
│   │       ├── [langcode].json
│   │       └── ...
│   └── ...
└── alias-buckets/
    └── [langcode]/
        └── [bucket]/
            └── [alias_path]/
                ├── data.json
                └── [entity_type]-[entity_id].json
```

### Entity JSON Example

```json
{
  "id": "1",
  "type": "node",
  "bundle": "article",
  "langcode": "en",
  "title": "Hello World",
  "created": "2025-01-15T10:30:00+00:00",
  "changed": "2025-06-20T14:00:00+00:00"
}
```

### Alias JSON Example

```json
{
  "entityType": "node",
  "entityId": "1"
}
```

## Permissions

| Permission | Description |
|------------|-------------|
| Administer headless entity serializer | Access the configuration form and manage serialization settings. |

## Development

See [../AGENTS.md](../AGENTS.md) for code quality tools and architecture details.

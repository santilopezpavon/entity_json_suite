# Entity JSON Suite

A collection of Drupal modules for generating static JSON files from content entities. Designed for headless architectures and Static Site Generators (SSG).

## Modules

| Module | Description |
|--------|-------------|
| [headless_entity_serializer](headless_entity_serializer/) | Serializes Drupal entities (nodes, users, taxonomy terms, etc.) into structured JSON files on disk. Supports full regeneration and incremental updates. |
| [entity_json_serving](entity_json_serving/) | HTTP serving module: serves serialized JSON files via GET endpoints with ETag, language negotiation, and path alias resolution. |
| [entity_json_views](entity_json_views/) | Exports Drupal Views (REST export displays with `data_field` row plugin) as static JSON files. Drush command `hev-export` and serving route `/json/views/{view_id}/{display_id}`. |

## Requirements

- Drupal 9, 10, or 11
- PHP 8.1+

## Development

See [AGENTS.md](AGENTS.md) for development guidelines, code quality tools, and module architecture.

# Entity JSON Suite

A collection of Drupal modules and a Node.js server for generating and serving static JSON files from content entities. Designed for headless architectures and Static Site Generators (SSG).

## Components

| Component | Type | Description |
|-----------|------|-------------|
| [headless_entity_serializer](headless_entity_serializer/) | Drupal module | Serializes Drupal entities (nodes, users, taxonomy terms, etc.) into structured JSON files on disk. Supports full regeneration and incremental updates. |
| [entity_json_serving](entity_json_serving/) | Drupal module | HTTP serving module: serves serialized JSON files via GET endpoints with ETag, language negotiation, and path alias resolution. |
| [entity_json_views](entity_json_views/) | Drupal module | Exports Drupal Views (REST export displays with `data_field` row plugin) as static JSON files. Drush command `hev-export` and serving route `/json/views/{view_id}/{display_id}`. |
| [node-server](node-server/) | Node.js package | High-performance static file server. Reads the same files Drupal writes, resolves aliases by file lookup (no Drupal bootstrap). 10-50x faster than the Drupal serving module. |

## Requirements

- Drupal 9, 10, or 11
- PHP 8.1+
- Node.js 20+ (only for node-server)

## Development

See [AGENTS.md](AGENTS.md) for development guidelines, code quality tools, and module architecture.


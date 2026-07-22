# Entity JSON Suite

A collection of Drupal modules for generating static JSON files from content entities. Designed for headless architectures and Static Site Generators (SSG).

## Modules

| Module | Description |
|--------|-------------|
| [headless_entity_serializer](headless_entity_serializer/) | Serializes Drupal entities (nodes, users, taxonomy terms, etc.) into structured JSON files on disk. Supports full regeneration and incremental updates. |
| [entity_json_serving](entity_json_serving/) | HTTP serving module: serves serialized JSON files via GET endpoints with ETag, language negotiation, and path alias resolution. |

## Requirements

- Drupal 9, 10, or 11
- PHP 8.1+

## Development

See [AGENTS.md](AGENTS.md) for development guidelines, code quality tools, and module architecture.

# Headless Entity Serializer Module

This Drupal module is designed to automatically serialize content entities into static JSON files on your server's file system. This facilitates their consumption by a headless application or a Static Site Generator (SSG). It offers functionalities for both full regeneration and incremental updates of these files.

## Key Features

* JSON Serialization: Converts Drupal content entities (such as Nodes, Users, Taxonomy Terms, etc.) into JSON files.
* Disk Storage: Saves the JSON files to a configurable directory on your file system, following a logical structure by entity type, ID, and language code.
* Full Regeneration: Allows you to delete all previously generated JSON files for selected entity types and regenerate them from scratch. Ideal for initial deployment or a complete resynchronization.
* Incremental Update: Processes only entities that have been created, updated, or deleted since the last run, ensuring efficient and fast synchronization.
* Multilingual Support: Generates separate JSON files for each translation of an entity, if the site has languages configured.

## Configuration

* Configure the destination directory: Navigate to /admin/config/headless-entity-serializer in your Drupal site.
* Define the Destination Directory where the JSON files will be saved (e.g., public://exported_entities). Ensure Drupal has write permissions to this directory.
* Select the Entity Types you wish to serialize.

## Usage

Once configured, you can use the Drush commands to manage the generation of your JSON files.

### Full Regeneration
This command deletes all existing JSON files for the configured entity types and regenerates them completely.

```bash
drush hes-full
```
When to use it:

* The first time you set up the module.
* After major structural changes to your content or configuration that affect most entities.
* When you need to ensure all files are in a clean, freshly updated state.

### Incremental Update
This command only processes entities that have been modified, created, or deleted since the last incremental run. It is the recommended way to keep your files updated in a production environment.

```bash
drush hes-incremental
```
When to use it:

* Regularly, for example, configured as a cron job.
* For continuous synchronization of your content.

## Generated File Structure

JSON files will be saved in the configured destination directory, following a structure like this:


[destination_directory]/
├── [entity_type_id]/
│   └── [entity_id]/
│       └── [langcode].json
├── alias/alias/
│   └── [langcode].json  (e.g., alias/en.json, alias/es.json)

The content of alias/alias/[langcode].json would be an associative array similar to:
```javascript
{
  "/about/us": {
    "source": "/node/1",
    "langcode": "en",
    "status": 1,
    "type": "entity",
    "bundle": "page"
  },
  "/contact": {
    "source": "/node/2",
    "langcode": "en",
    "status": 1,
    "type": "entity",
    "bundle": "page"
  }
}
```




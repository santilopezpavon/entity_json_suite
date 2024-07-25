This is the core module for Drupal’s static files.

It provides a form config at the path /admin/config/entity-json-settings/settings. In this form, you can select the entities to sync, and it also shows the recommended entities to sync.

The entities will be automatically synchronized.


This module has two commands:


1. Generate all JSON files entities.
```bash
drush entity-json-speed:generate
```
2. Delete all JSON files entitites.
```bash
drush entity-json-speed:delete
```

Next Steps:

1. Create a NodeJS server for serve the information of entities.
2. Create new Drupal Module for server the information from JSON entities.
3. Do compatible with private folders and give the posibility of select the folder to save the json files.
# Entity JSON Suite — Guía de desarrollo

## Herramientas de calidad

Todas las herramientas se ejecutan desde la raíz del proyecto (`/api`) dentro del contenedor ddev.

### PHPStan (análisis estático, nivel 5)

```bash
ddev exec vendor/bin/phpstan analyse
```

Configuración: `phpstan.neon`

### PHP_CodeSniffer (coding standards Drupal)

```bash
# Verificar errores
ddev exec vendor/bin/phpcs --standard=.phpcs.xml

# Auto-corregir errores fixables
ddev exec vendor/bin/phpcbf --standard=.phpcs.xml
```

Configuración: `.phpcs.xml`

### Rector (refactoring automático)

```bash
# Dry-run (solo muestra cambios sin aplicar)
ddev exec vendor/bin/rector process --dry-run

# Aplicar cambios
ddev exec vendor/bin/rector process
```

Configuración: `rector.php`

### Scripts Composer (atajos)

```bash
ddev exec composer phpstan
ddev exec composer phpcs
ddev exec composer phpcbf
ddev exec composer rector
ddev exec composer rector:fix
ddev exec composer code-quality   # Ejecuta las tres herramientas
```

## Drush commands del módulo

```bash
# Generación completa (todos los tipos de entidad configurados)
ddev exec drush hes-full

# Generación completa de un tipo específico
ddev exec drush hes-full node

# Actualización incremental (solo entidades modificadas desde el último run)
ddev exec drush hes-incremental

# Resetear estado del incremental (fuerza regenerar todo en el próximo incremental)
ddev exec drush hes-reset-state

# Exportar menús como JSON estático
ddev exec drush hem-export

# Exportar vistas como JSON estático
ddev exec drush hev-export
```

## Tests

```bash
# Unit tests (rápidos, sin base de datos)
ddev exec composer test
ddev exec composer test:unit

# Kernel tests (requieren base de datos + drupal/core-dev)
ddev exec composer test:kernel
```

## Módulos de la suite

```
entity_json_suite/
├── headless_entity_serializer/          # Módulo base: generación JSON
│   └── src/Services/Storage/FileStorageManager.php  # + getEntityFilePath()
│
├── entity_json_menu/                  # Módulo exportación de menús
│   ├── src/
│   │   ├── Controller/MenuExportController.php    # GET /json/menus/{menu_id}
│   │   ├── Form/MenuExportSettingsForm.php        # Admin config
│   │   ├── Services/MenuExporter.php              # Export service
│   │   └── Commands/MenuExportCommands.php        # drush hem-export
│   ├── config/schema/entity_json_menu.schema.yml
│   ├── entity_json_menu.info.yml
│   ├── entity_json_menu.permissions.yml
│   ├── entity_json_menu.routing.yml
│   ├── entity_json_menu.services.yml
│   └── drush.services.yml
│
├── entity_json_serving/               # Módulo serving HTTP
│   ├── src/
│   │   ├── Controller/EntityJsonController.php   # 2 endpoints
│   │   ├── Resolver/AliasResolver.php            # Resolución de alias vía path_alias
│   │   └── PathProcessor/AliasPathProcessor.php  # Multi-segment alias support
│   ├── config/schema/entity_json_serving.schema.yml
│   ├── entity_json_serving.info.yml
│   ├── entity_json_serving.permissions.yml
│   ├── entity_json_serving.routing.yml
│   └── entity_json_serving.services.yml
│
└── entity_json_views/                 # Módulo exportación de vistas
    ├── src/
    │   ├── Controller/ViewsExportController.php    # GET /json/views/{view_id}/{display_id}
    │   ├── Form/ViewsExportSettingsForm.php        # Admin config
    │   ├── Services/ViewsExporter.php              # Export service
    │   └── Commands/ViewsExportCommands.php        # drush hev-export
    ├── config/schema/entity_json_views.schema.yml
    ├── entity_json_views.info.yml
    ├── entity_json_views.permissions.yml
    ├── entity_json_views.routing.yml
    └── entity_json_views.services.yml
```

## entity_json_serving — Rutas HTTP

| Ruta | Método | Descripción |
|------|--------|-------------|
| `GET /json/{entity_type}/{entity_id}/{langcode}` | `serveEntity()` | BinaryFileResponse (streaming, ETag, 304) |
| `GET /json/path/{alias}` | `serveByAlias()` | Alias → entity JSON |

- Permiso requerido: `access entity json`
- Cache-Control: 3600s entity
- 304 Not Modified vía `isNotModified()` cuando el cliente envía ETag/If-Modified-Since

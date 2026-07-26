<?php

declare(strict_types=1);

namespace Drupal\entity_json_menu\Commands;

use Drupal\entity_json_menu\Services\MenuExporter;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for exporting Menus as static JSON.
 */
class MenuExportCommands extends DrushCommands {

  /**
   * The menu exporter service.
   */
  protected MenuExporter $exporter;

  /**
   * Constructs a new MenuExportCommands.
   */
  public function __construct(MenuExporter $exporter) {
    parent::__construct();
    $this->exporter = $exporter;
  }

  /**
   * Export all configured Menus as static JSON files.
   *
   * @command entity-json-menu:export
   * @aliases hem-export
   */
  public function export(): void {
    $results = $this->exporter->exportAll();

    if ($results === []) {
      $this->logger()->notice('No menus configured for export.');
      $this->logger()->notice('Go to /admin/config/entity-json-menu to configure which menus to export.');
      return;
    }

    $success = 0;
    $fail = 0;

    foreach ($results as $result) {
      if ($result['success']) {
        $this->logger()->success('Exported {menu}', [
          'menu' => $result['menu_id'],
        ]);
        $success++;
      }
      else {
        $this->logger()->error('Failed {menu}: {error}', [
          'menu' => $result['menu_id'],
          'error' => $result['error'],
        ]);
        $fail++;
      }
    }

    $this->logger()->notice('Done. {success} exported, {fail} failed.', [
      'success' => $success,
      'fail' => $fail,
    ]);
  }

}

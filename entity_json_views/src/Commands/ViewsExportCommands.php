<?php

declare(strict_types=1);

namespace Drupal\entity_json_views\Commands;

use Drupal\entity_json_views\Services\ViewsExporter;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for exporting Views as static JSON.
 */
class ViewsExportCommands extends DrushCommands {

  /**
   * The views exporter service.
   */
  protected ViewsExporter $exporter;

  /**
   * Constructs a new ViewsExportCommands.
   */
  public function __construct(ViewsExporter $exporter) {
    parent::__construct();
    $this->exporter = $exporter;
  }

  /**
   * Export all configured Views as static JSON files.
   *
   * @command entity-json-views:export
   * @aliases hev-export
   */
  public function export(): void {
    $results = $this->exporter->exportAll();

    if ($results === []) {
      $this->logger()->notice('No views configured for export.');
      $this->logger()->notice('Go to /admin/config/entity-json-views to configure which views to export.');
      return;
    }

    $success = 0;
    $fail = 0;

    foreach ($results as $result) {
      if ($result['success']) {
        $this->logger()->success('Exported {view}:{display}', [
          'view' => $result['view_id'],
          'display' => $result['display_id'],
        ]);
        $success++;
      }
      else {
        $this->logger()->error('Failed {view}:{display}: {error}', [
          'view' => $result['view_id'],
          'display' => $result['display_id'],
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

<?php

namespace NES\drupal;

class NesDrupalOracle
{

  protected $mapping;
  protected $reports = [];
  public array $modules;

  public function __construct($insightReports)
  {
    $this->mapping = $this->mapReports($insightReports);
    $this->modules = array_keys($this->mapping);
  }

  public function getReport(string $moduleId)
  {
    if (!isset($this->reports[$moduleId])) {
      $this->reports[$moduleId] =
        in_array($moduleId, $this->modules)
        ? $this->simplifyReport($moduleId, $this->mapping[$moduleId])
        : null;
    }
    return $this->reports[$moduleId];
  }

  public function simplifyReport($moduleId, array $reports)
  {
    $coverage = null;
    $entries = [];
    foreach ($reports as $report) {
      foreach ($report->entries as $entry) {
        // handle coverage (if it hasn't been handled)
        if ($entry->key == 'nes_coverage') {
          if ($coverage === null) {
            $coverage = $entry;
          }
          continue;
        }
        // otherwise just keep the entry?
        $entries[] = $entry;

      }
    }


    $support = null;
    $supportMeta = $coverage->metadata->nes->supported;
    if ($supportMeta->core) {
      $support = 'Core';
    } else if ($supportMeta->essentials) {
      $support = 'Essentials';
    }

    return (object) array(
      'id' => $moduleId,
      'support' => $support,
      'entries' => $entries,
    );
  }

  /**
   * Reduces package reports by package name. 
   * Note that a report can address multiple packages.
   */
  private function mapReports($reports)
  {
    $mapping = [];
    foreach ($reports as $report) {
      // note: future iterations should filter reports without 
      // an entry of key "nes_coverage"

      foreach ($report['affectedPackages'] as $pkg) {
        $fqns = $pkg['fqns'];
        if (!array_key_exists($fqns, $mapping)) {
          $mapping[$fqns] = [];
        }

        $pkgReports = $mapping[$fqns];
        if (!in_array($report, $pkgReports)) {
          // $mapping[$fqns][] = $this->objectify($report);
          $mapping[$fqns][] = $this->objectify($report);
        }
      }
    }
    return $mapping;
  }


  function objectify($array)
  {
    // Check if it's an associative array
    if (is_array($array) && array_keys($array) !== range(0, count($array) - 1)) {
      $obj = new \stdClass();
      foreach ($array as $key => $value) {
        $obj->$key = $this->objectify($value);
      }
      return $obj;
    } elseif (is_array($array)) {
      // If it's an indexed array, recurse its elements
      foreach ($array as $key => $value) {
        $array[$key] = $this->objectify($value);
      }
    }
    return $array;
  }
}

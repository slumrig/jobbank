<?php

namespace Drupal\postings\Plugin\migrate\process;

use DOMXPath;
use Drupal\Component\Utility\Html;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\postings\Plugin\migrate\process\XPathQueryTrait;

/**
 * @MigrateProcessPlugin(
 *   id = "get_lmia_status"
 * )
 *
 * @code
 * field_lmia_status:
 *  plugin: get_lmia_status
 *  source: lmia_status
 * @endcode
 */
class GetLMIAStatus extends ProcessPluginBase {

  use XPathQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $xpath = new DOMXPath(Html::load($value));
    $status = $this->getFieldsBypath($xpath, "//span[@class='tfw-icon lmia-icon-pending']");

    if ($status->length === 0) {
      $status = $this->getFieldsBypath($xpath, "//span[@class='tfw-icon lmia-icon-approved']");
      if ($status->length === 0) {
        return 'None';
      }
      return 'Approved';
    }
    return 'Applied';
  }
}

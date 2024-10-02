<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\Component\Utility\Html;
use Drupal\migrate\Annotation\MigrateProcessPlugin;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use DOMXPath;

/**
 * @MigrateProcessPlugin(
 *   id = "get_employment_type"
 * )
 *
 * @code
 * field_employment_type:
 *  plugin: get_employment_type
 *  source: employment_type
 * @endcode
 */
class GetEmploymentType extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $xpath = new DOMXPath(Html::load($value));
    $node = $xpath->query("//span[@property='employmentType']");
    if (!empty($node[0])) {
      return str_replace('employment', 'employment / ', trim($node[0]->nodeValue));
    }
    return '';
  }
}

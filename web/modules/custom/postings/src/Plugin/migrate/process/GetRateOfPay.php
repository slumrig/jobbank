<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "get_rate_of_pay"
 * )
 *
 * @code
 * field_rate_of_pay:
 *  plugin: get_rate_of_pay
 *  source: text
 * @endcode
 */
class GetRateOfPay extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return trim(str_replace(['YEAR', 'HOUR', 'MONTH', 'WEEK'], '', explode('/', strip_tags($value))[0]));
  }
}

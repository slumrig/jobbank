<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "get_posting_url"
 * )
 *
 * @code
 * field_posting_url:
 *  plugin: get_posting_url
 *  source: job_id
 * @endcode
 */
class GetPostingURL extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    return 'https://www.jobbank.gc.ca/jobsearch/jobposting/' . $value;
  }
}

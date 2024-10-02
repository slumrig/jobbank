<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "get_email"
 * )
 *
 * @code
 * field_employer_email:
 *  plugin: get_email
 *  source: job_id
 * @endcode
 */
class GetEmail extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $params = [
      'form_params' => [
        'seekeractivity:jobid' => $value,
        'seekeractivity_SUBMIT' => 1,
        'jakarta.faces.ViewState' => 'stateless',
        'jakarta.faces.behavior.event' => 'action',
        'action' => 'applynowbutton',
        'jakarta.faces.partial.event' => 'click',
        'jakarta.faces.source' => 'seekeractivity',
        'jakarta.faces.partial.ajax' => 'true',
        'jakarta.faces.partial.execute' => 'jobid',
        'jakarta.faces.partial.render' => 'applynow markappliedgroup',
        'seekeractivity' => 'seekeractivity',
      ],
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
      ]
    ];
    $client = \Drupal::httpClient();
    $response = $client->post(
      'https://www.jobbank.gc.ca/jobsearch/jobposting/' . $value, $params);
    $results = [];
    preg_match('/mailto:(.*)"/', $response->getBody(), $results);
    return count($results) > 1 ? $results[1] : '';
  }
}

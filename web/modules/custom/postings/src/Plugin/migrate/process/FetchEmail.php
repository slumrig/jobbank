<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @MigrateProcessPlugin(
 *    id = "get_email"
 *  )
 */
class FetchEmail extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    $parts = explode('/', $value['uri']);
    $job_id = end($parts);
    $params = [
      'form_params' => [
        'seekeractivity:jobid' => $job_id,
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
    $client = new Client();
    try {
      $response = $client->post($value['uri'], $params);
      $results = [];
      preg_match('/mailto:(.*)"/', $response->getBody(), $results);
      return count($results) > 1 ? $results[1] : '';
    } catch (GuzzleException $e) {
      return '';
    }
  }
}

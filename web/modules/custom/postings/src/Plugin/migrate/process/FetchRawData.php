<?php

namespace Drupal\postings\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @MigrateProcessPlugin(
 *    id = "get_raw_html"
 *  )
 */
class FetchRawData extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): string {
    $client = new Client();
    try {
      $response = $client->get($value['uri']);
      if ($response->getStatusCode() === 200) {
        return (string) $response->getBody();
      }
    } catch (GuzzleException $e) {
      return '';
    }
    return '';
  }
}

<?php

namespace Drupal\postings\Plugin\migrate\source;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @MigrateSource(
 *   id = "posting_email_plugin"
 * )
 */
class GetPostingEmails extends SourcePluginBase {
  private const PAGES = 25;

  private array $emails = [];

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'email' => 'The employer email if exists',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'https://www.jobbank.gc.ca/jobsearch/jobposting';
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return [
      'url' => [
        'type' => 'string'
      ]
    ];
  }

  protected function initializeIterator() {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $node_storage->getQuery()
      ->condition('type', 'job_posting')
      ->range(0, self::PAGES)
      ->accessCheck()
      ->execute();
    $postings = $node_storage->loadMultiple($ids);
    $client = \Drupal::httpClient();
    foreach ($postings as $posting) {
      try {
        $uri = $posting->get('field_posting_url')->getValue()[0]['uri'];
        $parts = explode('/', $uri);
        $job_id = end($parts);
        $this->emails[] = [
          'email' => $this->getEmail($client, $job_id),
          'nid' => $posting->id(),
          'url' => $uri,
        ];
      }
      catch (GuzzleException $e) {
        continue;
      }
    }

    return new \ArrayIterator($this->emails);
  }

  /**
   * @param string $job_id
   *
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function getEmail($client, string $job_id): string {
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
    $response = $client->post(
      'https://www.jobbank.gc.ca/jobsearch/jobposting/' . $job_id, $params);
    $results = [];
    preg_match('/mailto:(.*)"/', $response->getBody(), $results);
    return count($results) > 1 ? $results[1] : '';
  }
}

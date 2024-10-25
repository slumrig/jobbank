<?php

namespace Drupal\postings\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @MigrateSource(
 *   id = "posting_source_plugin"
 * )
 */
class GetPostings extends SourcePluginBase {

  private const PAGES = 25;

  private array $postings = [];

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [
      'raw' => 'Page content unparsed',
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
        $promise = $client->getAsync($uri);
        $response = $promise->wait();
        $this->postings[] = [
          'raw' => $response->getBody()->getContents(),
          'url' => $uri,
          'nid' => $posting->id()
        ];
      }
      catch (GuzzleException $e) {
        continue;
      }
    }

    return new \ArrayIterator($this->postings);
  }

}

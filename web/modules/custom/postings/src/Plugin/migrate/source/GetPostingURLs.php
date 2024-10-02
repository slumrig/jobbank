<?php

namespace Drupal\postings\Plugin\migrate\source;

use DOMXPath;
use Drupal\Component\Utility\Html;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @MigrateSource(
 *   id = "url_source_plugin"
 * )
 */
class GetPostingURLs extends SourcePluginBase {

  private const PAGES = 4044;

  private array $urls = [];

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return ['url' => 'A job posting url.'];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return ['url' => [
        'type' => 'string'
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Iterator|\ArrayIterator {
    $client = \Drupal::httpClient();
    for ($i = 1; $i < self::PAGES; $i++) {
      $url = 'https://www.jobbank.gc.ca/jobsearch/jobsearch?page=' . $i . '&sort=D&fsrc=16';
      try {
        $promise = $client->getAsync($url);
        $response = $promise->wait();
      }
      catch (GuzzleException $e) {
        continue;
      }
      $document = Html::load($response->getBody());
      $dom = new DOMXPath($document);
      $node_list = $dom->query("//a[@class='resultJobItem']/@href");
      for ($j = 0; $j < $node_list->length; $j++) {
        $parts = explode(';', $node_list[$j]->value);
        $matches = [];
        preg_match('#(\d+)$#', $parts[0], $matches);
        $this->urls[] = [
          'id' => $matches[0],
          'url' => 'https://www.jobbank.gc.ca' . $parts[0]
        ];
      }
    }
    return (new \ArrayObject($this->urls))->getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'https://www.jobbank.gc.ca/jobsearch/jobsearch';
  }
}

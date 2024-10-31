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

  private const PAGES = 4000;

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
    $urls = [];
    for ($i = 0; $i < self::PAGES; $i++) {
      $page = ($i < 1) ? '?' : '?page=' . $i . '&';
      $url = 'https://www.jobbank.gc.ca/jobsearch/jobsearch' . $page . 'sort=D&fsrc=16';
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
      if (count($node_list) < 1) { // no results mean its reached a page with no postings
        echo $i;
        break;
      }
      for ($j = 0; $j < $node_list->length; $j++) {
        $parts = explode(';', $node_list[$j]->value);
        $matches = [];
        preg_match('#(\d+)$#', $parts[0], $matches);
        $urls[] = [
          'id' => $matches[0],
          'url' => 'https://www.jobbank.gc.ca' . $parts[0]
        ];
      }
    }
    return new \ArrayIterator($urls);
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'https://www.jobbank.gc.ca/jobsearch/jobsearch';
  }
}

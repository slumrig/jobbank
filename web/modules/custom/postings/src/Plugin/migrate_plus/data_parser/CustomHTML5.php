<?php

namespace Drupal\postings\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Annotation\DataParser;
use Drupal\migrate_source_html\Plugin\migrate_plus\data_parser\Html5;

/**
 * Obtain HTML document for migration.
 *
 * @DataParser(
 *   id = "custom_html5"
 * )
 */
class CustomHTML5 extends HTML5 {

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow(): void {
    parent::fetchNextRow();
    if ($this->valid()) {
      $url = $this->currentUrl();
      $this->currentItem['current_url'] = $url;
      $matches = [];
      preg_match('#(\d+)$#', $url, $matches);
      $this->currentItem['posting_id'] = $matches[0];
    }
  }

  protected function openSourceUrl(string $url): bool {
    try {
      return parent::openSourceUrl($url);
    } catch (\Exception $e) {
      return TRUE;
    }
  }
}

<?php

namespace Drupal\postings\Plugin\migrate\process;

use DOMXPath;

trait XPathQueryTrait {

  /**
   * @param \DOMXpath $dom_path
   * @param string $x_path
   *
   * @return iterable
   */
  private function getFieldsBypath(DOMXpath $dom_path, string $x_path): iterable {
    return $dom_path->query($x_path);
  }
}

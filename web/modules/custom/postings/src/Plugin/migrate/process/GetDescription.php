<?php

namespace Drupal\postings\Plugin\migrate\process;

use DOMXPath;
use Drupal\Component\Utility\Html;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\postings\Plugin\migrate\process\XPathQueryTrait;

/**
 * @MigrateProcessPlugin(
 *   id = "get_description"
 * )
 *
 * @code
 * field_job_description:
 *  plugin: get_description
 *  source: job_description
 * @endcode
 */
class GetDescription extends ProcessPluginBase {

  use XPathQueryTrait;

  private const LANGUAGE = "//p[@property='qualification']";
  private const EDUCATION = "//ul[@property='educationRequirements qualification']/li";
  private const EXPERIENCE = "//p[@property='experienceRequirements qualification']/span/following-sibling::span";
  private const TASKS = "//div[@property='responsibilities']/ul/li";
  private const REQUIREMENTS = "//div[@property='experienceRequirements']/h4";
  private const SKILLS = "//div[@property='skills']/h4";
  private const BENEFITS = "//div[@property='jobBenefits']/h4";

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $xpath = new DOMXPath(Html::load($value));
    $values = [];
    $values['language'] = $this->getValueByPath($xpath, self::LANGUAGE);
    $education = $this->getFieldsBypath($xpath, self::EDUCATION);
    $education_info = [];
    foreach ($education as $li) {
      $education_info[] = trim($li->nodeValue);
    }
    $values['education'] = implode(' ', $education_info);
    $values['experience'] = $this->getValueByPath($xpath,self::EXPERIENCE);
    $tasks = $this->getFieldsBypath($xpath, self::TASKS);
    foreach ($tasks as $task) {
      $values['tasks'][] = trim($task->nodeValue);
    }
    $this->parseRequirementSection($xpath, $values,self::REQUIREMENTS);
    $this->parseRequirementSection($xpath, $values,self::SKILLS);
    $this->parseRequirementSection($xpath, $values,self::BENEFITS);

    try {
      return json_encode($values, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      return '';
    }
  }

  /**
   * @param \DOMXpath $dom_path
   * @param string $x_path
   *
   * @return string
   */
  private function getValueByPath(DOMXpath $dom_path, string $x_path): string {
    $node = $dom_path->query($x_path);
    if (!empty($node[0])) {
      return trim($node[0]->nodeValue);
    }
    return '';
  }

  /**
   * @param \DOMXpath $dom_path
   * @param array $values
   * @param string $selector
   *
   * @return void
   */
  private function parseRequirementSection(DOMXpath $dom_path, array &$values, string $selector): void {
    $h4s = $this->getFieldsBypath($dom_path, $selector);
    foreach ($h4s as $h4) {
      $key = trim($h4->nodeValue);
      $values[$key] = [];
      $ul = $h4->nextSibling->nextSibling;
      if ($ul->hasChildNodes()) {
        $children = $ul->childNodes;
        for ($i = 0; $i < $children->length; $i++) {
          $child = $children->item($i);
          $value = trim(str_replace(['\t', '\n'], '', $child->nodeValue));
          if ($value !== '') {
            $values[$key][] = $value;
          }
        }
      }
    }
  }
}

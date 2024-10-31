<?php

namespace Drupal\postings\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use DOMXPath;

/**
 * A Drush commandfile.
 */
final class PostingsCommands extends DrushCommands {

  private const PAGES = 25;
  private const TITLE_PATH = "//span[@property='title']";
  private const EMPLOYMENT_TYPE = "//span[@property='employmentType']";
  private const EMPLOYER_NAME = "//span[@property='name']/strong";
  private const EMPLOYER_URL= "//span[@property='name']/a";
  private const POSTING_DATE = "//span[@property='datePosted']";
  private const HOURS_PER_UNIT = "//span[@property='workHours']";
  private const BASE_SALARY = "//span[@property='baseSalary']";
  private const VACANCIES = "//span[@class='fa fa-user']/following-sibling::span/following-sibling::span";
  private const STREET = "//span[@property='streetAddress']";
  private const LOCALITY = "//span[@property='addressLocality']";
  private const REGION = "//span[@property='addressRegion']";
  private const POSTAL_CODE = "//span[@property='postalCode']";
  private const JOB_BANK_NO = "//span[@class='source-image']/following-sibling::span/following-sibling::span";
  private const NOC = "//span[@class='noc-no']";
  private const LANGUAGE = "//p[@property='qualification']";
  private const EDUCATION = "//ul[@property='educationRequirements qualification']/li";
  private const EXPERIENCE = "//p[@property='experienceRequirements qualification']/span/following-sibling::span";
  private const TASKS = "//div[@property='responsibilities']/ul/li";
  private const REQUIREMENTS = "//div[@property='experienceRequirements']/h4";
  private const SKILLS = "//div[@property='skills']/h4";
  private const BENEFITS = "//div[@property='jobBenefits']/h4";
  private const PENDING = "//span[@class='tfw-icon lmia-icon-pending']";
  private const APPROVED = "//span[@class='tfw-icon lmia-icon-approved']";
  private const LOCATION = "(//span[contains(@class, 'fa-icon-desc')])[1]/following-sibling::span/following-sibling::span";

  /**
   * Constructs a PostingsCommands object.
   */
  public function __construct(
    private readonly Token $token,
    private readonly EntityTypeManagerInterface $entity_type_manager,
    private readonly LoggerChannelFactoryInterface $logger_factory,
    private readonly MigrationPluginManager $migration_plugin_manager
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('plugin.manager.migration'),
    );
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'postings:command-name', aliases: ['foo'])]
  #[CLI\Argument(name: 'arg1', description: 'Argument description.')]
  #[CLI\Option(name: 'option-name', description: 'Option description')]
  #[CLI\Usage(name: 'postings:command-name foo', description: 'Usage description')]
  public function commandName($arg1, $options = ['option-name' => 'default']) {
    $this->logger()->success(dt('Achievement unlocked.'));
  }

  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'postings:token', aliases: ['token'])]
  #[CLI\FieldLabels(labels: [
    'group' => 'Group',
    'token' => 'Token',
    'name' => 'Name'
  ])]
  #[CLI\DefaultTableFields(fields: ['group', 'token', 'name'])]
  #[CLI\FilterDefaultField(field: 'name')]
  public function token($options = ['format' => 'table']): RowsOfFields {
    $all = $this->token->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

  /**
   * Run the migration programmatically and hopefully make it work in batches.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'postings:migrate', aliases: ['pstm'])]
  #[CLI\Usage(name: 'postings:migrate', description: 'Migrate job posting node to custom table.')]
  public function runMigration() {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $ids = $node_storage->getQuery()
      ->condition('type', 'job_posting')
      ->condition('status', '0')
      ->condition('field_raw', NULL, 'IS NOT NULL')
      ->accessCheck()
      ->execute();
    foreach(array_chunk($ids, 50) as $ids) {
      foreach($node_storage->loadMultiple($ids) as $posting) {
        $body = $posting->get('field_raw')->getValue()[0]['value'];
        $document = Html::load($body);
        $dom_path = new DOMXpath($document);
        $posting->set('title', $this->getValueByPath($dom_path, self::TITLE_PATH));
        $posting->set('field_employer_address', $this->getAddress($dom_path));
        [$employer_name, $employer_url] = $this->getEmployer($dom_path);
        $posting->set('field_employer_name', $employer_name);
        $posting->set('field_employer_url', $employer_url);
        $posting->set('field_employment_type', $this->getEmploymentType($dom_path));
        $posting->set('field_job_bank_number', $this->getJobBankNumber($dom_path));
        $posting->set('field_job_description', $this->getJobDescription($dom_path));
        $posting->set('field_lmia_status', $this->getLMIAStatus($dom_path));
        $posting->set('field_location', $this->getValueByPath($dom_path, self::LOCATION));
        $posting->set('field_noc', $this->getNoc($dom_path));
        $posting->set('field_posting_date', $this->getPostedDate($dom_path));
        $posting->set('field_rate_of_hours', $this->getValueByPath($dom_path, self::HOURS_PER_UNIT));
        $posting->set('field_rate_of_pay', $this->getRate($dom_path));
        $posting->set('field_vacancies', $this->getValueByPath($dom_path, self::VACANCIES));
        $posting->setPublished(TRUE);
        try {
          $posting->save();
          echo '.';
        }
        catch (\Exception $e) {
          $this->logger()?->error($e->getMessage());
          echo '*';
        }
      }
      echo PHP_EOL;
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
   *
   * @return string
   */
  private function getAddress(DOMXpath $dom_path): string {
    $address = [];
    $street = $this->getValueByPath($dom_path, self::STREET);
    if (!empty($street)) {
      $address[] = $street;
    }
    $locality = $this->getValueByPath($dom_path, self::LOCALITY);
    if (!empty($locality)) {
      $address[] = $locality;
    }
    $region = $this->getValueByPath($dom_path, self::REGION);
    if (!empty($region)) {
      $address[] = $region;
    }
    $postal_code = $this->getValueByPath($dom_path, self::POSTAL_CODE);
    if (!empty($postal_code)) {
      $address[] = $postal_code;
    }
    return implode(', ', $address);
  }

/**
* @param \DOMXpath $dom_path
*
* @return array
*/
  private function getEmployer(DOMXpath $dom_path): array {
    $name = $this->getValueByPath($dom_path, self::EMPLOYER_NAME);
    $url = '';
    if (empty($name)) {
      $node = $this->getFieldsByPath($dom_path, self::EMPLOYER_URL)[0];
      $name = trim($node->nodeValue);
      $url = $node->getAttribute('href');
    }
    return [$name, $url];
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getEmploymentType(DOMXpath $dom_path): string {
    $type = $this->getValueByPath($dom_path, self::EMPLOYMENT_TYPE);
    return str_replace('employment', 'employment / ', trim($type));
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return int
   */
  private function getJobBankNumber(DOMXpath $dom_path): int {
    return (int) str_replace('#', '',
      $this->getValueByPath($dom_path, self::JOB_BANK_NO));
  }

  /**
   * @param \DOMXPath $dom_path
   *
   * @return string
   */
  private function getJobDescription(DOMXpath $dom_path): string {
    $values = [];
    $values['language'] = $this->getValueByPath($dom_path, self::LANGUAGE);
    $education = $this->getFieldsBypath($dom_path, self::EDUCATION);
    $education_info = [];
    foreach ($education as $li) {
      $education_info[] = trim($li->nodeValue);
    }
    $values['education'] = implode(' ', $education_info);
    $values['experience'] = $this->getValueByPath($dom_path,self::EXPERIENCE);
    $tasks = $this->getFieldsBypath($dom_path, self::TASKS);
    foreach ($tasks as $task) {
      $values['tasks'][] = trim($task->nodeValue);
    }
    $this->parseRequirementSection($dom_path, $values,self::REQUIREMENTS);
    $this->parseRequirementSection($dom_path, $values,self::SKILLS);
    $this->parseRequirementSection($dom_path, $values,self::BENEFITS);

    try {
      return json_encode($values, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      return '';
    }
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

  /**
   * @param \DOMXpath $dom_path
   * @param string $x_path
   *
   * @return iterable
   */
  private function getFieldsBypath(DOMXpath $dom_path, string $x_path): iterable {
    return $dom_path->query($x_path);
  }

  private function getLMIAStatus(DOMXpath $dom_path): string {
    $status = $this->getFieldsBypath($dom_path,self::PENDING);

    if ($status->length === 0) {
      $status = $this->getFieldsBypath($dom_path,self::APPROVED);
      if ($status->length === 0) {
        return 'None';
      }
      return 'Approved';
    }
    return 'Applied';
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getNoc(DOMXpath $dom_path): string {
    return str_replace('NOC ', '',
      $this->getValueByPath($dom_path, self::NOC));
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getPostedDate(DOMXpath $dom_path): string {
    $date_text = $this->getValueByPath($dom_path, self::POSTING_DATE);
    return trim(str_replace(['Posted on', '\t', '\n'], '', $date_text));
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getRate(DOMXpath $dom_path): string {
    $rate_text = $this->getValueByPath($dom_path, self::BASE_SALARY);
    return trim(str_replace(['YEAR', 'HOUR', 'MONTH', 'WEEK'], '', explode('/', $rate_text)[0]));
  }
}

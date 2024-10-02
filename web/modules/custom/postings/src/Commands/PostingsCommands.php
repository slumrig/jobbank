<?php

namespace Drupal\postings\Drush\Commands;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use DOMXpath;

/**
 * A drush command that makes requests and imports job postings.
 */
final class PostingsCommands extends DrushCommands {

  private const CJB_POSTING_URL = 'https://www.jobbank.gc.ca/jobsearch/jobposting/';
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
  private const WORK_SITE_INFO = "//span[@class='fa-icon-desc fa-icon fas fa-building']/following-sibling::span/following-sibling::span";
  private const LANGUAGE = "//p[@property='qualification']";
  private const EDUCATION = "//ul[@property='educationRequirements qualification']/li";
  private const EXPERIENCE = "//p[@property='experienceRequirements qualification']/span/following-sibling::span";
  private const TASKS = "//div[@property='responsibilities']/ul/li";
  private const REQUIREMENTS = "//div[@property='experienceRequirements']/h4";
  private const SKILLS = "//div[@property='skills']/h4";
  private const BENEFITS = "//div[@property='jobBenefits']/h4";
  private const PENDING = "//span[@class='tfw-icon lmia-icon-pending']";
  private const APPROVED = "//span[@class='tfw-icon lmia-icon-approved']";

  /**
   * Constructs a PostingsCommands object.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
  ) {
    parent::__construct();
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\postings\Drush\Commands\PostingsCommands
   */
  public static function create(ContainerInterface $container): PostingsCommands {
    return new PostingsCommands(
      $container->get('http_client'),
    );
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
   * @param string $x_path
   *
   * @return iterable
   */
  private function getFieldsBypath(DOMXpath $dom_path, string $x_path): iterable {
    return $dom_path->query($x_path);
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getEmploymentType(DOMXpath $dom_path): string {
    $type = $this->getValueByPath($dom_path, self::EMPLOYMENT_TYPE);
    return str_replace('employment', 'employment / ', $type);
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
   * @return int
   */
  private function getVacancies(DOMXpath $dom_path): int {
    return (int) str_replace(' vacancies', '',
      $this->getValueByPath($dom_path, self::VACANCIES));
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return int
   */
  private function getJobBankNo(DOMXpath $dom_path): int {
    return (int) str_replace('#', '',
      $this->getValueByPath($dom_path, self::JOB_BANK_NO));
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
   *
   * @return string
   */
  private function getJobDescription(DOMXpath $dom_path): string {
    $values = [];
    $values['language'] = $this->getValueByPath($dom_path, self::LANGUAGE);
    $education = $this->getFieldsBypath($dom_path, self::EDUCATION);
    foreach ($education as $li) {
      $values['education'] = trim($li->nodeValue);
    }
    $values['experience'] = $this->getValueByPath($dom_path,self::EXPERIENCE);

    $tasks = $this->getFieldsBypath($dom_path, self::TASKS);
    foreach ($tasks as $task) {
      $values['tasks'][] = trim($task->nodeValue);
    }
    $this->parseRequirementSection($dom_path, $values,self::REQUIREMENTS);
    $this->parseRequirementSection($dom_path, $values,self::SKILLS);
    $this->parseRequirementSection($dom_path, $values,self::BENEFITS);

    return json_encode($values);
  }

  /**
   * @param \DOMXpath $dom_path
   *
   * @return string
   */
  private function getLMIAStatus(DOMXpath $dom_path): string {
    $status = $this->getFieldsBypath($dom_path, self::PENDING);
    if ($status->length === 0) {
      $status = $this->getFieldsBypath($dom_path, self::APPROVED);
      if ($status->length === 0) {
        return 'None';
      }
      return 'Approved';
    }
    return 'Applied';
  }

  /**
   * @param int $posting_id
   *
   * @return \DOMXpath
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function postingRequest(int $posting_id): DOMXpath {
    $response = $this->httpClient->request('GET',
      self::CJB_POSTING_URL . $posting_id);
    $document = Html::load($response->getBody());
    return new DOMXPath($document);
  }

  /**
   * @param int $posting_id
   *
   * @return string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function getEmail(int $posting_id): string {
    $params = [
      'form_params' => [
        'seekeractivity:jobid' => $posting_id,
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

    $response = $this->httpClient->post(
      self::CJB_POSTING_URL . $posting_id, $params);
    $results = [];
    preg_match('/mailto:(.*)"/', $response->getBody(), $results);
    return count($results) > 1 ? $results[1] : '';
  }

  /**
   * @param array $posting
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function savePosting(array $posting): void {
    $node = Node::create([
      'type' => 'job_posting',
      'title' => $posting['title'],
      'field_employer_address' => $posting['address'],
      'field_employer_email' => $posting['email'],
      'field_employer_name' => $posting['employer_name'],
      'field_employer_url' => $posting['employer_url'],
      'field_employer_type' => $posting['type'],
      'field_job_description' => $posting['description'],
      'field_job_bank_number' => $posting['job_bank_no'],
      'field_lmia_status' => $posting['status'],
      'field_noc' => $posting['noc'],
      'field_posting_date' => $posting['posting_date'],
      'field_rate_of_hours' => $posting['hours'],
      'field_rate_of_pay' => $posting['rate'],
      'field_vacancies' => $posting['vacancies'],
      'field_posting_url' => $posting['url'],
    ]);

    $node->save();
  }

  /**
   * Make a job posting http request and parse job posting data from the response.
   */
  #[CLI\Command(name: 'postings:retrieve', aliases: ['pr'])]
  #[CLI\Usage(name: 'postings:retrieve', description: 'Request and retrieve a job posting.')]
  public function commandName(): void {
    try {
      $posting_id = 41725766;
      $dom_path = $this->postingRequest($posting_id);
      [$employee_name, $employee_url] = $this->getEmployer($dom_path);
      $posting = [
        'title' => $this->getValueByPath($dom_path, self::TITLE_PATH),
        'email' => $this->getEmail($posting_id),
        'type' => $this->getEmploymentType($dom_path),
        'employer_name' => $employee_name,
        'employer_url' => $employee_url,
        'posting_date' => $this->getPostedDate($dom_path),
        'hours' => $this->getValueByPath($dom_path, self::HOURS_PER_UNIT),
        'rate' => $this->getRate($dom_path),
        'site_info' => $this->getValueByPath($dom_path, self::WORK_SITE_INFO),
        'vacancies' => $this->getVacancies($dom_path),
        'address' => $this->getAddress($dom_path),
        'job_bank_no' => $this->getJobBankNo($dom_path),
        'noc' => $this->getNoc($dom_path),
        'description' => $this->getJobDescription($dom_path),
        'status' => $this->getLMIAStatus($dom_path),
        'url' => self::CJB_POSTING_URL . $posting_id,
      ];
      $this->savePosting($posting);
    } catch (GuzzleException $e) {
      $this->logger()->error($e->getMessage());
    }
    catch (EntityStorageException $e) {
      $this->logger()->error($e->getMessage());
    }
  }
}

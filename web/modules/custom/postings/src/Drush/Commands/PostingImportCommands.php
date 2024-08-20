<?php

namespace Drupal\postings\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;
use http\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
class PostingImportCommands extends DrushCommands {

  /**
   * Constructs a PostingImportCommands object.
   */
  public function __construct(

  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(

    );
  }

  /**
   * @command posting_import:import
   * @aliases pi
   *
   * @return void
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function importPostings() {
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
      $query->condition('type', 'job_posting')
        ->accessCheck(FALSE);
      $posting_ids = $query->execute();
      foreach ($posting_ids as $nid) {
        $node = Node::load($nid);
        print_r([
          'job_id' => $node->field_job_id->value,
          'job_title' => $node->field_job_title->value,
          'job_employment_type' => $node->field_employment_type->value,
          'email' => $node->field_email->value,
          'employer_name' => $node->field_employer_name->value,
          'employer_url' => $node->field_employer_url->value,
          'posting_data' => $node->field_posting_date->value,
          'unit_of_payment' => $node->field_unit_of_payment->value,
          'vacancies' => $node->field_vacancies->value,
          'address' => $node->field_address->value,
          'job_bank_number' => $node->field_job_bank_number->value,
          'noc' => $node->field_noc->value,
          'lmia_status' => $node->field_lmia_status->value
        ]);
      }
  }

  /**
   * An example of the table output format.
   *
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   *
   * @field-labels
   *   group: Group
   *   token: Token
   *   name: Name
   * @default-fields group,token,name
   *
   * @command posting_import:token
   * @aliases token
   *
   * @filter-default-field name
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function token($options = ['format' => 'table']) {
    $all = \Drupal::token()->getInfo();
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
}

<?php

namespace Drupal\postings\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile.
 */
final class PostingsCommands extends DrushCommands {

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
   * Migrate nodes to custom table.
   */
//  #[CLI\Command(name: 'postings:migrate', aliases: ['pst'])]
//  #[CLI\Usage(name: 'postings:migrate', description: 'Migrate job posting node to custom table.')]
//  public function createPostingsUrlRows() {
//    $node_storage = $this->entity_type_manager->getStorage('node');
//    $nids = \Drupal::entityQuery('node')
//      ->condition('type', 'job_posting_url')
//      ->accessCheck()
//      ->execute();
//    $posting_urls = $node_storage->loadMultiple($nids);
//    $db = \Drupal::database();
//    $i = 0;
//    foreach ($posting_urls as $posting_url) {
//      if ($i % 1000 === 0) {
//        $this->logger()->notice('Created ' . $i . ' rows in the custom_postings_url table.');
//      }
//      $url = $posting_url->get('field_url')->get(0)->getUrl()->getUri();
//      $db->insert('posting_urls')
//        ->fields(['url' => $url])
//        ->execute();
//      $i++;
//    }
//
//    $this->logger()->success(
//      $i . ': ' . dt('Created rows in the custom_postings_url table.')
//    );
//  }

  /**
   * Run the migration programmatically and hopefully make it work in batches.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  #[CLI\Command(name: 'postings:migrate', aliases: ['pst'])]
  #[CLI\Usage(name: 'postings:migrate', description: 'Migrate job posting node to custom table.')]
  public function runMigration() {
    $migration = $this->migration_plugin_manager->createInstance('posting_migration');
    $executable = new MigrateExecutable($migration);
    $executable->import();
  }
}

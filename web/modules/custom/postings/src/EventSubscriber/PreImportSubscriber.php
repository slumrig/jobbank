<?php

namespace Drupal\postings\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PreImportSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      'migrate.pre_import' => 'preImport',
    ];
  }

  public function preImport(Event $event): void {
    $source = $event->getMigration();
  }
}

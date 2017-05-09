<?php

namespace Drupal\wget_static;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Permissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  public static function permissions() {
    $perms = [];

    $perms['wget generate static'] = array(
      'title' => t('Use wget static'),
      'description' => t('Access to generate static HTML and download/save to remote server.'),
    );

    $perms['wget use supercmd'] = array(
      'title' => t('Use wget command options directly'),
      'description' => t('Allow to input wget options directly as command.') . ' <em>' . t('Warning: Give to trusted roles only; this permission has security implications.') . '</em>',
    );
    return $perms;
  }
}

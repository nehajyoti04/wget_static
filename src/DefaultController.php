<?php
namespace Drupal\wget_static;

/**
 * Default controller for the wget_static module.
 */
class DefaultController extends ControllerBase {

  public function wget_static_page_callback($form_type) {
    switch ($form_type) {
      case 'node':
        return \Drupal::formBuilder()->getForm('wget_static_form', 'node');

      case 'path':
        return \Drupal::formBuilder()->getForm('wget_static_form', 'path');

      case 'website':
        return \Drupal::formBuilder()->getForm('wget_static_form', 'website');

      default:
        return drupal_not_found();
    }
  }

}

<?php
namespace Drupal\wget_static;

class WgetStaticConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wget_static_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('wget_static.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wget_static.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    if (!(\Drupal::config('wget_static.settings')->get('wget_static_cmd_availability'))) {
      $form['wget_static_warning'] = [
        '#markup' => '<div class= "messages warning"> ' . t('Wget Static Module was not able to find wget command access') . '</div>'
        ];
      $form['wget_static_command'] = [
        '#type' => 'textfield',
        '#title' => t('Wget URL on the server'),
        '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_command'),
        '#description' => t('Important! If incorrect value provided, this module is of no use.<br> Please contact your server administrators for confirmation.'),
      ];
    }

    $form['wget_static_usage'] = [
      '#markup' => t('To use Wget Static Module use following urls: <br>
      a) For NODE - wget_static/node <br>
      b) For PATH - wget_static/path <br>
      c) For WEBSITE - wget_static/website <br>')
      ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_content_tab_title'] = [
      '#type' => 'textfield',
      '#title' => 'Wget Static form Content Selection Tab Title',
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_content_tab_title'),
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_content_tab_description'] = [
      '#type' => 'textfield',
      '#title' => 'Wget Static form Content Selection Tab Description',
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_content_tab_description'),
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_settings_tab_title'] = [
      '#type' => 'textfield',
      '#title' => 'Wget Static form Settings Tab Title',
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_settings_tab_title'),
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_settings_tab_description'] = [
      '#type' => 'textfield',
      '#title' => 'Wget Static form Settings Tab Description',
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_settings_tab_description'),
    ];

    $form['wget_static_success'] = [
      '#type' => 'select',
      '#title' => t('After successful wget operation'),
      '#options' => [
        0 => t('Display Success Message'),
        1 => t('Redirect to page'),
      ],
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_success'),
    ];
    $form['wget_static_success_message'] = [
      '#type' => 'textfield',
      '#title' => t('Enter success message here.'),
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_success_message'),
      '#states' => [
        'visible' => [
          ':input[name="wget_static_success"]' => [
            'value' => 0
            ]
          ]
        ],
    ];
    $form['wget_static_success_redirect'] = [
      '#type' => 'textfield',
      '#title' => t('Enter url here.'),
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_success_redirect'),
      '#states' => [
        'visible' => [
          ':input[name="wget_static_success"]' => [
            'value' => 1
            ]
          ]
        ],
    ];

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_save_download'] = [
      '#type' => 'checkboxes',
      '#title' => t('Wget Save/Download Option\'s Configuration'),
      '#descriptions' => t('Selected Options would be available for users.'),
      '#options' => [
        'download' => t('Download to Local'),
        'ftp' => t('Save to FTP Server'),
        'webdav' => t('Save to Webdav Server'),
      ],
      '#required' => TRUE,
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_save_download'),
    ];

    $form['wget_static_enable_wget_log'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable Wget debug mode'),
      '#description' => t('When enabled, wget will write logs to dblog each time it operates. Used for developer purpose only.'),
      '#default_value' => \Drupal::config('wget_static.settings')->get('wget_static_enable_wget_log'),
    ];

    return parent::buildForm($form, $form_state);
  }

}

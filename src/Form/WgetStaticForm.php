<?php
namespace Drupal\wget_static\Form;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\wget_static\WgetStaticFTPClient;
use Drupal\wget_static\WgetStaticRecursiveZip;

/**
 * Class AddForm.
 *
 * @package Drupal\wget_static\Form\WgetStaticForm
 */
class WgetStaticForm extends FormBase {

  protected $form_type;
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wget_static_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $form_type = NULL) {

    $form = array();

    $form['wget_static_of'] = array(
      '#type' => 'hidden',
      '#value' => $form_type,
    );

    if ($form_type != 'website') {
      // Content selection part.
      $form['wget_static'] = array(
        '#type' => 'vertical_tabs',
      );
      // @FIXME
      // Could not extract the default value because it is either indeterminate, or
      // not scalar. You'll need to provide a default value in
      // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
      // @FIXME
      // Could not extract the default value because it is either indeterminate, or
      // not scalar. You'll need to provide a default value in
      // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
      $form['wget_static_content'] = array(
        '#type' => 'details',
        '#title' => \Drupal::config('wget_static.settings')->get('wget_static_content_tab_title'),
        '#open' => TRUE,
        '#group' => 'wget_static',
        '#description' => \Drupal::config('wget_static.settings')->get('wget_static_content_tab_description'),
      );

      $wget_static_content_form = '_wget_static_' . $form_type . '_contentform';
      self::$wget_static_content_form($form, $form_state);
    }

    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    // @FIXME
    // Could not extract the default value because it is either indeterminate, or
    // not scalar. You'll need to provide a default value in
    // config/install/wget_static.settings.yml and config/schema/wget_static.schema.yml.
    $form['wget_static_settings'] = array(
      '#type' => 'details',
      '#title' => \Drupal::config('wget_static.settings')->get('wget_static_settings_tab_title'),
      '#open' => TRUE,
      '#group' => 'wget_static',
      '#description' => \Drupal::config('wget_static.settings')->get('wget_static_settings_tab_description'),
    );

    self::_wget_static_wget_options($form, $form_state);
    self::_wget_static_final_settings($form, $form_state);

    return $form;
  }

  //  /**
  //   * {@inheritdoc}
  //   */
  //  public function validateForm(array &$form, FormStateInterface $form_state) {
  //
  //  }
  //
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $temp_dir = file_directory_temp();
    $timestamp = time();
    $wget_dir = 'wget/' . $form_state->getValues()['wget_static_of'] . '/' . $timestamp;

    // Create static html at temporary directory.
    if (!$this->_wget_static_generate_static_html($temp_dir, $wget_dir, $form_state)) {
      drupal_set_message(t('Error creating temporary static html.'), 'error', FALSE);
      return;
    }

    switch ($form_state->getValues()['final']) {
      case 'download':
        $download_url = $this->_wget_static_create_archive($temp_dir, $wget_dir, $form_state->getValues()['download_file'], $timestamp, TRUE);
        if (!$download_url) {
          file_unmanaged_delete_recursive($temp_dir . "/wget");
          return;
        }
        break;

      case 'ftp':
        if (!$this->_wget_static_ftp($temp_dir, $wget_dir, $form_state, $timestamp)) {
          file_unmanaged_delete_recursive($temp_dir . "/wget");
          return;
        }
        break;

      case 'webdav':
        if (!$this->_wget_static_webdav($temp_dir, $wget_dir, $form_state, $timestamp)) {
          file_unmanaged_delete_recursive($temp_dir . "/wget");
          return;
        }
    }

    if (\Drupal::state()->get('wget_static_success', 0)) {
      $form_state['redirect'] = Url::fromUri(\Drupal::state()->get('wget_static_success_redirect', '<front>'), array('absolute' => TRUE));
    }
    else {
      $form_state['rebuild'] = TRUE;
      drupal_set_message(\Drupal::state()->get('wget_static_success_message', 'Operation Successfully Completed'), 'status', FALSE);
    }


  }

  /**
   * Constructs Content form for node form type..
   */
  public function _wget_static_node_contentform(array &$form, FormStateInterface $form_state, $query = array()) {

    if (!isset($query)) {
      $query = UrlHelper::filterQueryParameters(\Drupal::request()->query->all(), array('page'));
    }
    // Access Query parameters.
    $query_params = \Drupal\Component\Utility\UrlHelper::filterQueryParameters(\Drupal::request()->query->all(), $query);
    $default = isset($query_params['nid']) ? $query_params['nid'] : NULL;
    $node = self::_wget_static_verify_nid_parameter($default);
    $form['wget_static_content']['content_type'] = array(
      '#type' => 'select',
      '#title' => t('Select Content Type'),
      '#required' => TRUE,
      '#options' => self::_wget_static_getcontenttypes(),
      '#ajax' => array(
        'callback' => array($this, 'wgetStaticNodeContentFormAjax'),
        'wrapper' => 'node-contentform-data',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
//    kint("hello");
//    kint($form['wget_static_content']['content_type']);
    $form['wget_static_content']['data'] = array(
      '#type' => 'fieldset',
      '#prefix' => "<div id='node-contentform-data'>",
      '#suffix' => "</div>",
    );
    if ($node) {
      $form['wget_static_content']['data']['nid'] = array(
        '#type' => 'select',
        '#title' => t('Select Content'),
        '#required' => TRUE,
        '#options' => self::_wget_static_getcontent($node['content_type']),
      );
      // Assigning default values.
      $form['wget_static_content']['content_type']['#default_value'] = $node['content_type'];
      $form['wget_static_content']['data']['nid']['#default_value'] = $node['nid'];
      $form['wget_static']['#default_tab'] = 'edit-wget-static-settings';
    }
  }

  /**
   * Ajax callback for node contentform.
   */
  function wgetStaticNodeContentFormAjax(array &$form, FormStateInterface $form_state) {
    $form['wget_static_content']['data']['nid'] = array(
      '#type' => 'select',
      '#title' => t('Select Content'),
      '#required' => TRUE,
      '#options' => array_merge(
        array('0' => t('-- Please Select --')),
        self::_wget_static_getcontent($form_state->getValues()['content_type'])),
    );
    return $form['wget_static_content']['data'];
  }

  /**
   * Function returns array of available content types.
   */
  function _wget_static_getcontenttypes() {
    $all_content_types = NodeType::loadMultiple();
    $types = array();
    /** @var NodeType $content_type */
    foreach ($all_content_types as $machine_name => $content_type) {
      $types[$content_type->get('type')] = $content_type->get('name');
    }

    return $types;
  }

  /**
   * Returns array of all the contents of provided content type.
   */
  function _wget_static_getcontent($content_type) {
    $node_array = array();
//    print '<pre>'; print_r("wget_static_get_content"); print '</pre>'; exit;
//    $nids = db_select('node', 'n')
//      ->fields('n', array('nid'))
//      ->condition('n.type', $content_type)
//      ->execute()
//      ->fetchCol();

    $nids = db_select('node_field_data', 'n')
      ->fields('n', array('nid', 'title'))
      ->condition('n.type', $content_type)
      ->execute()
      ->fetchAll();

    foreach ($nids as $n) {
//      $node = \Drupal::entityManager()->getStorage('node')->load($n);
      $node_array[$n->nid] = $n->title;
    }
    return $node_array;
  }

  /**
   * Constructs Content form for path form type..
   */
  function _wget_static_path_contentform(array &$form, FormStateInterface $form_state) {
//    print '<pre>'; print_r("_wget_static_path_contentform"); print '</pre>'; exit;
    // Access Query parameters.
    $query_params = UrlHelper::filterQueryParameters();
    $default = isset($query_params['url']) ? $query_params['url'] : NULL;
    $form['wget_static_content']['path'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter internal path'),
      '#default_value' => $default,
      '#element_validate' => array('wget_static_path_validate'),
      '#required' => TRUE,
    );
    $form['wget_static']['#default_tab'] = 'edit-wget-static-settings';
  }

  /**
   * Validates Internal Path.
   */
  function wget_static_path_validate($element, FormStateInterface $form_state, $form) {
    print '<pre>'; print_r("wget_static_path_validate"); print '</pre>';
    if (!(!empty($element['#value']) && \Drupal::service("path.validator")->isValid(\Drupal::service('path.alias_manager')->getPathByAlias($element['#value'])) && !\Drupal\Component\Utility\UrlHelper::isExternal($element['#value']))) {
      $form_state->setError($element, t('Please enter valid internal path.'));
    }
  }
  /**
   * Adds wget options form elements.
   */
  public function _wget_static_wget_options(array &$form, FormStateInterface $form_state) {
    $form['wget_static_settings']['wget'] = array(
      '#type' => 'details',
      '#title' => t('Wget Options'),
      '#open' => TRUE,
      '#description' => t('Configure Wget for static HTML generation'),
    );

    // Directory Options.
    $form['wget_static_settings']['wget']['directory'] = array(
      '#type' => 'details',
      '#title' => t('Directory Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['wget_static_settings']['wget']['directory']['create_directory'] = array(
      '#type' => 'checkbox',
      '#title' => t('Create a hierarchy of directories when retrieving recursively.'),
      '#default_value' => FALSE,
    );
    $form['wget_static_settings']['wget']['directory']['no_host_directory'] = array(
      '#type' => 'checkbox',
      '#title' => t('Disable generation of host-prefixed directories.'),
      '#default_value' => TRUE,
    );

    // HTTP Options.
    $form['wget_static_settings']['wget']['http'] = array(
      '#type' => 'details',
      '#title' => t('HTTP/HTTPS Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['wget_static_settings']['wget']['http']['default_page'] = array(
      '#type' => 'textfield',
      '#title' => t('Default File Name'),
      '#description' => t('When it isn’t known (i.e., for URLs that end in a slash), instead of index.html.'),
    );
    $form['wget_static_settings']['wget']['http']['adjust_extension'] = array(
      '#type' => 'checkbox',
      '#title' => t('Adjust extensions automatically.'),
      '#default_value' => TRUE,
    );
    $form['wget_static_settings']['wget']['http']['cache'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable server-side cache'),
      '#default_value' => TRUE,
    );

    $form['wget_static_settings']['wget']['http']['secure_protocol'] = array(
      '#type' => 'select',
      '#title' => t('HTTPS secure Protocol'),
      '#options' => array(
        'auto' => t('auto'),
        'SSLv2' => t('SSLv2'),
        'SSLv3' => t('SSLv3'),
        'TLSv1' => t('TLSv1'),
        'TLSv1_1' => t('TLSv1_1'),
        'TLSv1_2' => t('TLSv1_2'),
        'PFS' => t('PFS'),
      ),
      '#default_value' => 'auto',
    );

    $form['wget_static_settings']['wget']['http']['httpsonly'] = array(
      '#type' => 'checkbox',
      '#title' => t('HTTPS Links only'),
      '#description' => t('When Enabled in recursive mode, only HTTPS links will be followed.'),
      '#default_value' => FALSE,
    );

    // Recursive Retrieval Options.
    $form['wget_static_settings']['wget']['rec'] = array(
      '#type' => 'details',
      '#title' => t('Recursive Retrieval Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['wget_static_settings']['wget']['rec']['enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable recursive retrieving'),
      '#default_value' => TRUE,
    );
    $form['wget_static_settings']['wget']['rec']['depth'] = array(
      '#type' => 'number',
      '#title' => t('Specify recursion maximum depth level depth'),
      '#description' => t('Enter depth level, -1 for maximum depth'),
      '#size' => 2,
      '#default_value' => 5,
    );
    $form['wget_static_settings']['wget']['rec']['convert_links'] = array(
      '#type' => 'checkbox',
      '#title' => t('Convert Links'),
      '#description' => t('After the download is complete, convert the links in the document to make them suitable for local viewing.'),
      '#default_value' => TRUE,
    );
    $form['wget_static_settings']['wget']['rec']['page_requisites'] = array(
      '#type' => 'checkbox',
      '#title' => t('Page Requisites'),
      '#description' => t('Downloads all the files that are necessary to properly display a given HTML page.'),
      '#default_value' => TRUE,
    );

    // Accept/Reject List.
    $form['wget_static_settings']['wget']['accept'] = array(
      '#type' => 'details',
      '#title' => t('Accept/Reject Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['wget_static_settings']['wget']['accept']['domains'] = array(
      '#type' => 'select',
      '#title' => t('Domains Options'),
      '#options' => array(
        0 => t('Do nothing'),
        'accept' => t('Follow these domains only'),
        'reject' => t('Exclude these domains'),
      ),
      '#default_value' => 0,
      '#description' => t('Set domains to be followed or excluded.'),
    );
    $form['wget_static_settings']['wget']['accept']['domainsaccept'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter domains to be followed'),
      '#description' => t('Use comma for multiple domains. Ex: example.com,drupal.org'),
      '#states' => array(
        'visible' => array(
          ':input[name="domains"]' => array('value' => 'accept'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['domainsreject'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter domains to be excluded'),
      '#description' => t('Use comma for multiple domains. Ex: example.com,drupal.org'),
      '#states' => array(
        'visible' => array(
          ':input[name="domains"]' => array('value' => 'reject'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['tags'] = array(
      '#type' => 'select',
      '#title' => t('Tags Options'),
      '#options' => array(
        0 => t('Do nothing'),
        'accept' => t('Consider these tags only'),
        'reject' => t('Exclude these tags'),
      ),
      '#default_value' => 0,
      '#description' => t('Set tags to be considered or excluded.'),
    );
    $form['wget_static_settings']['wget']['accept']['tagsaccept'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter tags to be considered'),
      '#description' => t('Use comma for multiple tags. Ex: a,area'),
      '#states' => array(
        'visible' => array(
          ':input[name="tags"]' => array('value' => 'accept'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['tagsreject'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter tags to be excluded'),
      '#description' => t('Use comma for multiple tags. Ex: a,area'),
      '#states' => array(
        'visible' => array(
          ':input[name="tags"]' => array('value' => 'reject'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['files'] = array(
      '#type' => 'select',
      '#validated' => TRUE,
      '#title' => t('Files Options'),
      '#options' => array(
        0 => t('Do nothing'),
        'accept' => t('Consider these files only'),
        'reject' => t('Exclude these files'),
      ),
      '#default_value' => 0,
      '#description' => t('Set files to be considered or excluded.'),
    );
    $form['wget_static_settings']['wget']['accept']['filesaccept'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter file extensions to be considered'),
      '#description' => t('Use comma for multiple extensions. Ex: png,mp3'),
      '#states' => array(
        'visible' => array(
          ':input[name="files"]' => array('value' => 'accept'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['filesreject'] = array(
      '#type' => 'textfield',
      '#title' => t('Enter file extensions to be excluded'),
      '#description' => t('Use comma for multiple extensions. Ex: png,mp3'),
      '#states' => array(
        'visible' => array(
          ':input[name="files"]' => array('value' => 'reject'),
        ),
      ),
    );
    $form['wget_static_settings']['wget']['accept']['relative_links_only'] = array(
      '#type' => 'checkbox',
      '#title' => t('Follow relative links only.'),
      '#description' => t('Useful for retrieving a specific home page without any distractions, not even those from the same hosts.'),
      '#default_value' => FALSE,
    );
    $form['wget_static_settings']['wget']['accept']['robots'] = array(
      '#type' => 'checkbox',
      '#title' => t('Consider Robots'),
      '#description' => t('Specify whether the robots is to be respected by Wget.'),
      '#default_value' => TRUE,
    );
    if (\Drupal::currentUser()->hasPermission('wget use supercmd')) {
      $form['wget_static_settings']['wget']['supercmd'] = array(
        '#type' => 'textfield',
        '#title' => t('Specify wget options directly other than provided ones.'),
        '#description' => t('This input will directly appended to the wget command responsible for generation of static HTML'),
      );
    }
  }

  /**
   * Function verifies default variable.
   */
  function _wget_static_verify_nid_parameter($default) {

    if (empty($default)) {
      return FALSE;
    }
    if (is_numeric($default)) {
//      $node = node_load($default);
      $node = Node::load($default);
      if ($node) {
        return array(
          'nid' => $node->nid,
          'title' => $node->title,
          'content_type' => $node->type,
        );
      }
    }
  }

  /**
   * Adds save/download settings for elements.
   */
  function _wget_static_final_settings(array &$form, FormStateInterface $form_state) {
    $form['wget_static_settings']['final'] = array(
      '#type' => 'select',
      '#title' => t('Save generated static HTML'),
      '#options' => array(
        'none' => t('Select'),
      ),
    );

    foreach (\Drupal::state()->get('wget_static_save_download', array('download' => 'download')) as $key => $value) {
      if ($value) {
        $form['wget_static_settings']['final']['#options'][$key] = ucfirst($key);
      }
    }

    $form['wget_static_settings']['download_file'] = array(
      '#type' => 'textfield',
      '#maxlength' => 30,
      '#title' => t('Name for compressed file'),
      '#states' => array(
        'visible' => array(
          ':input[name="final"]' => array('value' => 'download'),
        ),
      ),
    );
    $form['wget_static_settings']['download'] = array(
      '#type' => 'submit',
      '#value' => t('Download'),
      '#states' => array(
        'visible' => array(
          ':input[name="final"]' => array('value' => 'download'),
        ),
      ),
    );
    $form['wget_static_settings']['ftp'] = array(
      '#type' => 'details',
      '#title' => t('FTP settings'),
      '#states' => array(
        'visible' => array(
          ':input[name="final"]' => array('value' => 'ftp'),
        ),
      ),
    );
    $form['wget_static_settings']['ftp']['host'] = array(
      '#type' => 'textfield',
      '#title' => t('FTP Server Location'),
      '#description' => t('Please exclude protocol ftp:// at beginning and trailing slashes (/) at the end.'),
//      '#element_validate' => array('_wget_static_validate_host'),
      '#element_validate' => array(array($this, 'myElementValidator')),
    );
    $form['wget_static_settings']['ftp']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
    );
    $form['wget_static_settings']['ftp']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
    );
    $form['wget_static_settings']['ftp']['location'] = array(
      '#type' => 'textfield',
      '#title' => t('Remote Folder Name'),
      '#description' => t('Folder name in which static html has to be saved on remote server. Any existing directory of the same name would be removed.'),
    );
    $form['wget_static_settings']['ftp']['compressed_file'] = array(
      '#type' => 'checkbox',
      '#title' => t('Send as Compressed File on FTP Server'),
      '#default_value' => FALSE,
      '#description' => t('Compressed files would be sent faster.'),
    );
    $form['wget_static_settings']['ftp']['ftp_filename'] = array(
      '#type' => 'textfield',
      '#maxlength' => 30,
      '#title' => t('Name for compressed file'),
      '#states' => array(
        'visible' => array(
          ':input[name="compressed_file"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['wget_static_settings']['ftp']['ftp'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );
    $form['wget_static_settings']['webdav'] = array(
      '#type' => 'details',
      '#title' => t('Webdav settings'),
      '#states' => array(
        'visible' => array(
          ':input[name="final"]' => array('value' => 'webdav'),
        ),
      ),
    );
    $form['wget_static_settings']['webdav']['protocol'] = array(
      '#type' => 'select',
      '#title' => t('Webdav Protocol'),
      '#description' => t('Select Remote Webdav Server Protocol.'),
      '#options' => array(
        'http' => t('HTTP'),
        'https' => t('HTTPS'),
      ),
    );

    $form['wget_static_settings']['webdav']['host'] = array(
      '#type' => 'textfield',
      '#title' => t('Webdav Server Location'),
      '#description' => t('Please exclude protocol http:// or https:// at beginning and trailing slashes (/) at the end.'),
//      '#element_validate' => array('_wget_static_validate_host'),
      '#element_validate' => array(array($this, 'myElementValidator')),
    );
    $form['wget_static_settings']['webdav']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
    );
    $form['wget_static_settings']['webdav']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
    );
    $form['wget_static_settings']['webdav']['location'] = array(
      '#type' => 'textfield',
      '#title' => t('Remote Folder Name'),
      '#description' => t('Folder name in which static html has to be saved on remote server. Any existing directory of the same name would be removed.'),
    );
    $form['wget_static_settings']['webdav']['compressed_file'] = array(
      '#type' => 'checkbox',
      '#title' => t('Send as Compressed File on Webdav Server'),
      '#default_value' => FALSE,
      '#description' => t('Compressed files would be sent faster.'),
    );
    $form['wget_static_settings']['webdav']['webdav_filename'] = array(
      '#type' => 'textfield',
      '#maxlength' => 30,
      '#title' => t('Name for compressed file'),
      '#states' => array(
        'visible' => array(
          ':input[name="compressed_file"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['wget_static_settings']['webdav']['webdav_tfa'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable Two Factor Authentication'),
      '#default_value' => FALSE,
    );
    $form['wget_static_settings']['webdav']['tfa_data'] = array(
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#title' => t('Two Factor Authentication Settings'),
      '#states' => array(
        'visible' => array(
          ':input[name="webdav_tfa"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['wget_static_settings']['webdav']['tfa_data']['pem'] = array(
      '#type' => 'managed_file',
      '#title' => t('PEM/P12 File'),
      '#description' => t('Accepts PEM/P12 file containing public and private key (containing passphrase)'),
      '#upload_validators' => array(
        'file_validate_extensions' => array('pem p12'),
      ),
    );
    $form['wget_static_settings']['webdav']['tfa_data']['cert'] = array(
      '#type' => 'managed_file',
      '#title' => t('Cert File'),
      '#description' => t('Accepts CERT file : public key'),
      '#upload_validators' => array(
        'file_validate_extensions' => array('crt'),
      ),
    );
    $form['wget_static_settings']['webdav']['tfa_data']['passph'] = array(
      '#type' => 'textfield',
      '#title' => t('Passphrase'),
      '#description' => t('Enter Passphrase Required for Private Key'),
    );

    // Advanced Options.
    $form['wget_static_settings']['webdav']['advanced_options'] = array(
      '#type' => 'details',
      '#title' => t('Advanced Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['wget_static_settings']['webdav']['advanced_options']['auth'] = array(
      '#type' => 'select',
      '#title' => t('Authentication Method'),
      '#options' => array(
        'anyauth' => t('Any Authentication'),
        'basic' => t('Basic'),
        'digest' => t('Digest'),
      ),
    );
    if (\Drupal::currentUser()->hasPermission('wget use supercmd')) {
      $form['wget_static_settings']['webdav']['advanced_options']['curl'] = array(
        '#type' => 'textfield',
        '#title' => t('Direct Curl Options'),
        '#description' => t('The value will directly append to the curl command being executed. Please refer to curl documentation for valid options.'),
      );
    }
    $form['wget_static_settings']['webdav']['webdav'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );
  }

  /**_wget_static_validate_host
   * Validates my element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function myElementValidator(&$element, FormStateInterface $form_state, &$complete_form) {
    // Validate something.
    if (!empty($element['#value'])) {
      if (preg_match('/http:/', $element['#value']) || preg_match('/ftp:/', $element['#value']) || preg_match('/https:/', $element['#value']) || preg_match('/ftps:/', $element['#value'])) {
        $form_state->setError($element, t('Please ensure Host field does not contain protocol like http://, https:// or ftp://, ftps://.'));
      }
      if (preg_match('#/$#', $element['#value'])) {
        $form_state->setError($element, t('Please ensure Host field does not end with "/"'));
      }
      if (!@ftp_connect($element['#value']) && $form_state->getValues()['final'] == 'ftp') {
        $form_state->setError($element, t('Failed to connect to FTP server'));
      }
    }
    else {
      if ($form_state->getValues()['final'] == 'ftp') {
        $form_state->setError($element, t('FTP host field is required'));
      }
    }
  }

  /**
   * Generates static html.
   */
  function _wget_static_generate_static_html($temp_dir, $wget_dir,FormStateInterface $form_state) {
    $wget_options = $this->_wget_static_build_options($form_state);
    $wget_url = $this->_wget_static_build_url($form_state->getValues());
    $wget_cmd = $this->_wget_static_build_command($wget_options, $wget_url, $temp_dir, $wget_dir);
    file_unmanaged_delete_recursive($temp_dir . "/wget");
    // Check for debug mode.
    if (\Drupal::state()->get('wget_static_enable_wget_log', FALSE)) {
      $log = shell_exec($wget_cmd . " 2>&1");
    }
    else {
      shell_exec($wget_cmd);
    }
    return TRUE;
  }


  /**
   * Returns array of wget options.
   */
  function _wget_static_build_options(FormStateInterface $form_state) {
    $wget = array(
      'no_dir' => array(
        'use' => ($form_state->getValues()['create_directory']) ? FALSE : TRUE,
        'cmd' => '-nd',
      ),
      'force_dir' => array(
        'use' => ($form_state->getValues()['create_directory']) ? TRUE : FALSE,
        'cmd' => '-x',
      ),
      'no_host_dir' => array(
        'use' => ($form_state->getValues()['no_host_directory']) ? TRUE : FALSE,
        'cmd' => '-nH',
      ),
      'default_page' => array(
        'use' => ($form_state->getValues()['default_page']) ? TRUE : FALSE,
        'cmd' => '--default-page=' . preg_replace("/[^\p{L}\p{N}\.\-\_]/", "", trim($form_state->getValues()['default_page'])),
      ),
      'adjust_extension' => array(
        'use' => ($form_state->getValues()['adjust_extension']) ? TRUE : FALSE,
        'cmd' => '-E',
      ),
      'nocache' => array(
        'use' => ($form_state->getValues()['cache']) ? FALSE : TRUE,
        'cmd' => '--no-cache',
      ),
      'httpssecureprotocol' => array(
        'use' => ($form_state->getValues()['secure_protocol'] == 'auto') ? FALSE : TRUE,
        'cmd' => '--secure-protocol=' . $form_state->getValues()['secure_protocol'],
      ),
      'httpsonly' => array(
        'use' => ($form_state->getValues()['httpsonly']) ? TRUE : FALSE,
        'cmd' => '--https-only',
      ),
      'recretrieval' => array(
        'use' => ($form_state->getValues()['enable']) ? TRUE : FALSE,
        'cmd' => '-r',
      ),
      'depthlevel' => array(
        'use' => (($form_state->getValues()['enable'] == TRUE) && $form_state->getValues()['depth'] != '5') ? TRUE : FALSE,
        'cmd' => '--level=' . $form_state->getValues()['depth'],
      ),
      'convertlinks' => array(
        'use' => ($form_state->getValues()['convert_links']) ? TRUE : FALSE,
        'cmd' => '-k',
      ),
      'page_requisites' => array(
        'use' => ($form_state->getValues()['page_requisites']) ? TRUE : FALSE,
        'cmd' => '-p',
      ),
      'followdomains' => array(
        'use' => ($form_state->getValues()['domains'] == 'accept') ? TRUE : FALSE,
        'cmd' => '--domains=' . preg_replace('/\ /', '', $form_state->getValues()['domainsaccept']),
      ),
      'excludedomains' => array(
        'use' => ($form_state->getValues()['domains'] == 'reject') ? TRUE : FALSE,
        'cmd' => '--exclude-domains ' . preg_replace('/\ /', '', $form_state->getValues()['domainsreject']),
      ),
      'followtags' => array(
        'use' => ($form_state->getValues()['tags'] == 'accept') ? TRUE : FALSE,
        'cmd' => '--follow-tags=' . preg_replace('/[^\p{L}\,]/', '', $form_state->getValues()['tagsaccept']),
      ),
      'ignoretags' => array(
        'use' => ($form_state->getValues()['tags'] == 'reject') ? TRUE : FALSE,
        'cmd' => '--ignore-tags=' . preg_replace('/[^\p{L}\,]/', '', $form_state->getValues()['tagsreject']),
      ),
      'acceptfiles' => array(
        'use' => ($form_state->getValues()['files'] == 'accept') ? TRUE : FALSE,
        'cmd' => '-A ' . preg_replace("/[^\p{L}\p{N}\,]/", "", trim($form_state->getValues()['filesaccept'])),
      ),
      'rejectlist' => array(
        'use' => ($form_state->getValues()['files'] == 'reject') ? TRUE : FALSE,
        'cmd' => '-A ' . preg_replace("/[^\p{L}\p{N}\,]/", "", trim($form_state->getValues()['filesreject'])),
      ),
      'followrellinksonly' => array(
        'use' => ($form_state->getValues()['relative_links_only']) ? TRUE : FALSE,
        'cmd' => '-L',
      ),
      'robotsoff' => array(
        'use' => ($form_state->getValues()['robots']) ? FALSE : TRUE,
        'cmd' => '-e robots=off',
      ),
      'supercmd' => array(
        'use' => (isset($form_state->getValues()['supercmd']) && !empty($form_state->getValues()['supercmd'])) ? TRUE : FALSE,
        'cmd' => isset($form_state->getValues()['supercmd']) ? $form_state->getValues()['supercmd'] : '',
      ),
    );
    return $wget;
  }

  /**
   * Builds wget url.
   */
  function _wget_static_build_url($values) {

    switch ($values['wget_static_of']) {
      case 'node':
        if (isset($values['nid'])) {
          return Url::fromUri('base:/node/' . $values['nid'], array('absolute' => TRUE))->toString();
        } else {
          return Url::fromUri('base:/node', array('absolute' => TRUE))->toString();
        }


      case 'path':
        return Url::fromUri($values['path'], array('absolute' => TRUE));

      case 'website':
        return Url::fromUri('', array('absolute' => TRUE));
    }
  }

  /**
   * Builds wget final command.
   */
  function _wget_static_build_command($options, $url, $temp_dir, $wget_dir) {
    $wget = trim(shell_exec("which wget"));
    $wget = ($wget) ? $wget : \Drupal::state()->get('wget_static_command', '/usr/local/bin/wget');
    $cmd = $wget . " ";
    foreach ($options as $option) {
      if ($option['use']) {
        $cmd .= $option['cmd'] . " ";
      }
    }
    $cmd .= "-o " . $temp_dir . "/wget.log -P" . $temp_dir . "/" . $wget_dir . "/ " . $url;
    return $cmd;
  }

/**
* Generates zip archive.
*/
  function _wget_static_create_archive($temp_dir, $wget_dir, $filename, $timestamp, $download = FALSE) {

    $zip = new WgetStaticRecursiveZip();
    $filename = preg_replace('/[^\p{L}\p{N}\-\_]/', '', $filename);
    $filename = ($filename) ? $filename . '.zip' : $timestamp . '.zip';
    $filepath = $zip->compress($temp_dir . "/" . $wget_dir, $temp_dir . "/wget/", $filename);

    if (!$filepath) {
      drupal_set_message(t('Unable to compress'), 'error', FALSE);
      return FALSE;
    }
    if ($download) {
      // TODO port

      header('Content-Type: application/force-download');
      header('Content-Disposition: inline; filename='.basename($filename));

      readfile($filepath);
      exit;
    }
    else {
      return $filepath;
    }
  }

  /**
   * Uses ftp library to upload content on remote ftp server.
   */
  function _wget_static_ftp($temp_dir, $wget_dir,FormStateInterface $form_state, $timestamp) {
    // *** Include the class.
//    include_once 'ftp/ftp_class.php';

    // *** Create the FTP object.
    $ftpobj = new WgetStaticFTPClient();
    // *** Connect.
    if (!$ftpobj->connect($form_state->getValues()['host'], $form_state->getValues()['username'], $form_state->getValues()['password'], TRUE)) {
      drupal_set_message(t('Failed to connect to FTP server'), 'error', FALSE);
      return FALSE;
    }
    // *** Make fresh directory.
    $folder = preg_replace("/[^\p{L}\p{N}\-\_]/", "", $form_state->getValues()['location']);
    if ($folder) {
      if (!$ftpobj->makedir($folder)) {
        drupal_set_message(t('Unable to create directory at the Remote FTP server'), 'error', FALSE);
        return FALSE;
      }
    }

    if ($form_state->getValues()['compressed_file']) {
      $filepath = $this->_wget_static_create_archive($temp_dir, $wget_dir, $form_state->getValues()['download_file'], $timestamp, FALSE);
      if (!$filepath) {
        return FALSE;
      }
      $filename = preg_replace('/[^\p{L}\p{N}\-\_]/', '', $form_state->getValues()['ftp_filename']);
      $filename = ($filename) ? $filename : $timestamp;
      if (!$ftpobj->uploadfile($filepath, $folder . '/' . $filename . '.zip')) {
        drupal_set_message(t('Unable to upload compressed at the Remote FTP server'), 'error', FALSE);
        return FALSE;
      }
    }
    else {
      // *** Upload static content complete directory on remote server.
      if (!$ftpobj->ftpputall($temp_dir . "/" . $wget_dir, $folder)) {
        drupal_set_message(t('Unable to upload files at the Remote FTP server'), 'error', FALSE);
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Uses webdav library to upload content on remote webdav server.
   */
  function _wget_static_webdav($temp_dir, $wget_dir,FormStateInterface $form_state, $timestamp) {
    // *** Include the class.
    include_once 'webdav/wget_static.webdav.inc';
    $debug_mode = \Drupal::state()->get('wget_static_enable_wget_log', NULL);
    $process = array();
    $cert = "";
    if ($form_state->getValues()['webdav_tfa']) {
      $cert = _wget_static_webdav_cert_cmd($form_state);
    }

    $auth = "--" . $form_state->getValues()['auth'] . " ";
    $user = "-u '" . $form_state->getValues()['username'] . ":" . $form_state->getValues()['password'] . "' ";
    $http_code = "-sw '%{http_code}'";

    // *** Make fresh directory for the country.
    // Delete existing folder.
    $folder = preg_replace("/[^\p{L}\p{N}\-\_]/", "", $form_state->getValues()['location']);
    $req = _wget_static_webdav_delete_req_cmd($form_state, $folder);
    $process['delete']['cmd'] = "curl " . $cert . $user . $req . $auth . $http_code;
    $process['delete']['response'] = shell_exec($process['delete']['cmd']);
    $process['delete']['code'] = _wget_static_parse_reponse($process['delete']['response']);
    if (!(($process['delete']['code'] == 404) || preg_match("/\A20[0-9]/", $process['delete']['code']))) {
      // Try Renaming index file.
      $req = _wget_static_webdav_rename_cmd($form_state, $folder);
      $process['rename']['cmd'] = "curl " . $cert . $user . $req . $auth . $http_code;
      $process['rename']['response'] = shell_exec($process['rename']['cmd']);
      $process['rename']['code'] = _fila_publish_parse_reponse($process['rename']['response']);
      if (!(($process['rename']['code'] == 404) || preg_match("/\A20[0-9]/", $process['rename']['code']))) {
        drupal_set_message(t('Error Deleting Existing folder, returned : @code', array('@code' => $process['rename']['code'])), 'warning', FALSE);
      }
      else {
        // If rename successful.
        $process['delete']['response'] = shell_exec($process['delete']['cmd']);
        $process['delete']['code'] = _wget_static_parse_reponse($process['delete']['response']);
      }
    }

    // Creating Directory.
    $req = _wget_static_webdav_create_dir_cmd($form_state, $folder);
    $process['create']['cmd'] = "curl " . $cert . $user . $req . $auth . $http_code;
    $process['create']['response'] = shell_exec($process['create']['cmd']);
    $process['create']['code'] = _wget_static_parse_reponse($process['create']['response']);
    if (!(preg_match("/\A20[0-9]/", $process['create']['code']))) {
      drupal_set_message(t('Error Creating new directory, returned : @code', array('@code' => $process['create']['code'])), 'warning', FALSE);
      if ($debug_mode) {
        drupal_set_message("<pre>" . print_r($process, TRUE) . "</pre>", 'warning', FALSE);
      }
      return FALSE;
    }

    // *** Upload static content on remote server.
    if ($form_state->getValues()['compressed_file']) {
      $filepath = $this->_wget_static_create_archive($temp_dir, $wget_dir, $form_state->getValues()['download_file'], $timestamp, FALSE);
      if (!$filepath) {
        return FALSE;
      }
      $filename = preg_replace('/[^\p{L}\p{N}\-\_]/', '', $form_state->getValues()['webdav_filename']);
      $filename = ($filename) ? $filename : $timestamp;
      $des = $form_state->getValues()['protocol'] . "://" . $form_state->getValues()['host'] . "/" . $folder;
      $req = _wget_static_webdav_upload_file_cmd($filepath, $des, $filename . '.zip');
      $process['upload']['cmd'] = "curl " . $cert . $user . $req . $auth . $http_code;
      $process['upload']['response'] = shell_exec($process['upload']['cmd']);
      $process['upload']['code'] = _wget_static_parse_reponse($process['upload']['response']);
      if (!(preg_match("/\A20[0-9]/", $process['upload']['code']))) {
        drupal_set_message(t('Unable to upload compressed at the Remote Webdav server'), 'error', FALSE);
        return FALSE;
      }
    }
    else {
      // *** Upload static content complete directory on remote server.
      $d = dir($temp_dir . "/" . $wget_dir);
      // Do this for each file in the directory.
      $count = 0;
      while ($file = $d->read()) {
        // To prevent an infinite loop.
        if ($file != "." && $file != "..") {
          $des = $form_state->getValues()['protocol'] . "://" . $form_state->getValues()['host'] . "/" . $folder;
          $req = _wget_static_webdav_upload_content_cmd($temp_dir . "/" . $wget_dir, $des, $file);
          $process['upload']['cmd'] = "curl " . $cert . $user . $req . $auth . $http_code;
          $process['upload']['response'] = shell_exec($process['upload']['cmd']);
          $process['upload']['code'] = _wget_static_parse_reponse($process['upload']['response']);

          // Check uploading of first file only.
          if ($count == 0) {
            if (!(preg_match("/\A20[0-9]/", $process['upload']['code']))) {
              drupal_set_message(t('Error Uploading file, returned : @code', array('@code' => $process['upload']['code'])), 'warning', FALSE);
              if ($debug_mode) {
                drupal_set_message("<pre>" . print_r($process, TRUE) . "</pre>", 'warning', FALSE);
              }
              return FALSE;
            }
            $count++;
          }
        }
      }
    }
    return TRUE;
  }

}

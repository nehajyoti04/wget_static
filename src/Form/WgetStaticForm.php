<?php
namespace Drupal\wget_static\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeTypeInterface;
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
        '#type' => 'fieldset',
        '#title' => \Drupal::config('wget_static.settings')->get('wget_static_content_tab_title'),
        '#collapsible' => TRUE,
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
      '#type' => 'fieldset',
      '#title' => \Drupal::config('wget_static.settings')->get('wget_static_settings_tab_title'),
      '#collapsible' => TRUE,
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

  }

  /**
   * Constructs Content form for node form type..
   */
  public function _wget_static_node_contentform(&$form, &$form_state, $query= array()) {
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
        'callback' => '_wget_static_node_contentform_ajax',
        'wrapper' => 'node-contentform-data',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    $form['wget_static_content']['data'] = array(
      '#type' => 'fieldset',
      '#prefix' => '<div id="node-contentform-data">',
      '#suffix' => '</div>',
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
  function _wget_static_node_contentform_ajax($form, $form_state) {
    $form['wget_static_content']['data']['nid'] = array(
      '#type' => 'select',
      '#title' => t('Select Content'),
      '#required' => TRUE,
      '#options' => array_merge(
        array('0' => t('-- Please Select --')),
        self::_wget_static_getcontent($form_state['values']['content_type'])),
    );
    return $form['wget_static_content']['data'];
  }

  /**
   * Function returns array of available content types.
   */
  function _wget_static_getcontenttypes() {
    $all_content_types = NodeType::loadMultiple();
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
    $nids = db_select('node', 'n')
      ->fields('n', array('nid'))
      ->condition('n.type', $content_type)
      ->execute()
      ->fetchCol();
    foreach ($nids as $n) {
      $node = \Drupal::entityManager()->getStorage('node')->load($n);
      $node_array[$n] = $node->title;
    }
    return $node_array;
  }

  /**
   * Constructs Content form for path form type..
   */
  function _wget_static_path_contentform(&$form, &$form_state) {
    // Access Query parameters.
    $query_params = \Drupal\Component\Utility\UrlHelper::filterQueryParameters();
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
  function wget_static_path_validate($element, &$form_state, $form) {
    if (!(!empty($element['#value']) && \Drupal::service("path.validator")->isValid(drupal_get_normal_path($element['#value'])) && !\Drupal\Component\Utility\UrlHelper::isExternal($element['#value']))) {
      form_error($element, t('Please enter valid internal path.'));
    }
  }
  /**
   * Adds wget options form elements.
   */
  public function _wget_static_wget_options(&$form, &$form_state) {
    $form['wget_static_settings']['wget'] = array(
      '#type' => 'fieldset',
      '#title' => t('Wget Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Configure Wget for static HTML generation'),
    );

    // Directory Options.
    $form['wget_static_settings']['wget']['directory'] = array(
      '#type' => 'fieldset',
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
      '#type' => 'fieldset',
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
      '#type' => 'fieldset',
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
      '#type' => 'textfield',
      '#title' => t('Specify recursion maximum depth level depth'),
      '#description' => t('Enter depth level, -1 for maximum depth'),
      '#maxlength' => 2,
      '#element_validate' => array('element_validate_integer'),
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
      '#type' => 'fieldset',
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
      $node = node_load($default);
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
  function _wget_static_final_settings(&$form, &$form_state) {
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
      '#type' => 'fieldset',
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
      '#element_validate' => array('_wget_static_validate_host'),
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
      '#type' => 'fieldset',
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
      '#element_validate' => array('_wget_static_validate_host'),
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
      '#type' => 'fieldset',
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
      '#type' => 'fieldset',
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
}

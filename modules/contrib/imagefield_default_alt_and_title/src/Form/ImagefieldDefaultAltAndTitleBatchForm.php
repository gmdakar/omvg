<?php

namespace Drupal\imagefield_default_alt_and_title\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Batch form.
 *
 * @package Drupal\imagefield_default_alt_and_title\Form
 */
class ImagefieldDefaultAltAndTitleBatchForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * ImagefieldDefaultAltAndTitleBatchForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity manager.
   * @param \Drupal\Core\Database\Connection $database
   *   Database.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $etm, Connection $database) {
    $this->configFactory = $config_factory;
    $this->etm = $etm;
    $this->database = $database;
  }

  /**
   * Param container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Class param.
   *
   * @return \Drupal\imagefield_default_alt_and_title\Form\ImagefieldDefaultAltAndTitleBatchForm
   *   Return new class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('config.factory'),
        $container->get('entity_type.manager'),
        $container->get('database')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'imagefield-alt-title-batch';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['default_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Alt'),
    ];
    $form['default_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Title'),
    ];

    $node_options = $this->imagefieldDefaultAltAndTitleEntityListByType('node_type');
    if (!empty($node_options)) {
      $form['node_type'] = [
        '#type' => 'details',
        '#title' => $this->t('Node types'),
        '#open' => TRUE,
      ];
      $form['node_type']['node_entity_types'] = [
        '#type' => 'checkboxes',
        '#options' => $this->imagefieldDefaultAltAndTitleEntityListByType('node_type'),
      ];
    }

    $taxonomy_options = $this->imagefieldDefaultAltAndTitleEntityListByType('taxonomy_vocabulary');
    if (!empty($taxonomy_options)) {
      $form['taxonomy_vocabulary'] = [
        '#type' => 'details',
        '#title' => $this->t('Taxonomy vocabularies'),
        '#open' => TRUE,
      ];
      $form['taxonomy_vocabulary']['taxonomy_entity_types'] = [
        '#type' => 'checkboxes',
        '#options' => $this->imagefieldDefaultAltAndTitleEntityListByType('taxonomy_vocabulary'),
      ];
    }

    $commerce_options = $this->imagefieldDefaultAltAndTitleEntityListByType('commerce_product_type');
    if (!empty($commerce_options)) {
      $form['commerce_product'] = [
        '#type' => 'details',
        '#title' => $this->t('Commerce product type'),
        '#open' => TRUE,
      ];
      $form['commerce_product']['commerce_entity_types'] = [
        '#type' => 'checkboxes',
        '#options' => $this->imagefieldDefaultAltAndTitleEntityListByType('commerce_product_type'),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = [];
    $site_config = $this->configFactory->get('system.site');
    $site_config->set('imagefield_default_alt_and_title_default_values', [
      'alt' => $form_state->getValue(['default_alt']),
      'title' => $form_state->getValue(['default_title']),
    ])->save();

    if (!empty($form_state->getValue(['node_entity_types']))) {
      $node_query = $this->database->select('node', 'n');
      $node_query->fields('n', ['nid']);
      $node_query->condition('n.type', $form_state->getValue(['node_entity_types']), 'IN');
      $node_result = $node_query->execute()->fetchCol();
      $operations[] = [
        '\Drupal\imagefield_default_alt_and_title\ImagefieldDefaultAltAndTitleBatch::addedData',
        [$node_result, 'node_entity'],
      ];
    }
    if (!empty($form_state->getValue(['taxonomy_entity_types']))) {
      $tax_query = $this->database->select('taxonomy_term_field_data', 't');
      $tax_query->fields('t', ['tid']);
      $tax_query->condition('t.vid', $form_state->getValue(['taxonomy_entity_types']), 'IN');
      $node_result = $tax_query->execute()->fetchCol();

      $operations[] = [
        '\Drupal\imagefield_default_alt_and_title\ImagefieldDefaultAltAndTitleBatch::addedData',
        [$node_result, 'taxonomy_vocabulary'],
      ];
    }
    if (!empty($form_state->getValue(['commerce_entity_types']))) {
      $com_query = $this->database->select('commerce_product', 'cp');
      $com_query->fields('cp', ['product_id']);
      $com_query->condition('cp.type', $form_state->getValue(['commerce_entity_types']), 'IN');
      $node_result = $com_query->execute()->fetchCol();

      $operations[] = [
        '\Drupal\imagefield_default_alt_and_title\ImagefieldDefaultAltAndTitleBatch::addedData',
        [$node_result, 'commerce_product_type'],
      ];
    }

    $batch = [
      'title' => $this->t('Update images...'),
      'operations' => $operations,
      'finished' => '\Drupal\imagefield_default_alt_and_title\ImagefieldDefaultAltAndTitleBatch::addedDataFinishedCallback',
      'init_message' => $this->t('Processing Modules'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('Batch has encountered an error.'),
    ];
    batch_set($batch);
  }

  /**
   * Load all entity types available on the site.
   */
  public function imagefieldDefaultAltAndTitleEntityListByType($type) {
    $all_types = [];
    $entity_all_types_list = $this->etm->getDefinitions();
    if (!empty($entity_all_types_list)) {
      foreach ($entity_all_types_list as $key => $data) {
        if ($key == $type) {
          $contentTypesList = $this->etm->getStorage($key)->loadMultiple();
          if (!empty($contentTypesList)) {
            foreach ($contentTypesList as $key_bundles => $data_bundles) {
              $all_types[$key_bundles] = $key_bundles;
            }
          }
        }
      }
    }

    return $all_types;
  }

}

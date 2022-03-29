<?php

namespace Drupal\imagefield_default_alt_and_title\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ImagefieldDefaultAltAndTitleForm.
 *
 * @package Drupal\imagefield_default_alt_and_title\Form
 */
class ImagefieldDefaultAltAndTitleForm extends ConfigFormBase {

  /**
   * Entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * ImagefieldDefaultAltAndTitleForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $etm) {
    parent::__construct($config_factory);
    $this->etm = $etm;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('entity_type.manager'));
  }

  /**
   * Imagefield default alt and title form ID.
   */
  public function getFormId() {
    return 'imagefield_default_alt_and_title';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'imagefield_default_alt_and_title.settings',
    ];
  }

  /**
   * Imagefield default alt and title config form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('imagefield_default_alt_and_title.settings');
    $form['imagefield_default_alt_and_title_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types'),
      '#options' => $this->imagefieldDefaultAltAndTitleEntityList(),
      '#default_value' => !empty($config->get('imagefield_default_alt_and_title_entity_types')) ?
      $config->get('imagefield_default_alt_and_title_entity_types') : [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Imagefield default alt and title config form submit.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('imagefield_default_alt_and_title.settings')
      ->set('imagefield_default_alt_and_title_entity_types', $form_state->getValue('imagefield_default_alt_and_title_entity_types'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Load all entity types available on the site.
   */
  public function imagefieldDefaultAltAndTitleEntityList() {
    $all_types = [];
    $use_entitys = [
      'commerce_product_type',
      'node_type',
      'taxonomy_vocabulary',
    ];
    $entity_all_types_list = $this->etm->getDefinitions();
    if (!empty($entity_all_types_list)) {
      foreach ($entity_all_types_list as $key => $data) {
        if (in_array($key, $use_entitys)) {
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

<?php

namespace Drupal\file_pdf_preview\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media_library\Form\AddFormBase;
use Spatie\PdfToImage\Pdf;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file_pdf_preview' widget.
 *
 * @FieldWidget(
 *   id = "file_pdf_preview",
 *   label = @Translation("File PDF preview"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FilePdfPreviewWidget extends FileWidget implements ContainerFactoryPluginInterface {

  /**
   * FileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ElementInfoManagerInterface $element_info, FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $element_info);
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('element_info'),
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'preview_image_style' => '',
      'target_field_name' => '',
      'force_save' => FALSE,
      'description' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['preview_image_style'] = [
      '#title' => t('Preview image style'),
      '#type' => 'select',
      '#options' => image_style_options(TRUE),
      '#default_value' => $this->getSetting('preview_image_style'),
      '#description' => t('The preview image of the PDF file will be shown while editing the content.'),
      '#weight' => 15,
    ];
    $options = [];
    $fields_image = $this->entityFieldManager->getFieldMapByFieldType('image');
    $fields_image = array_intersect_key($fields_image[$form['#entity_type']], array_flip($form['#fields']));
    foreach($fields_image as $field_name => $info) {
      if (in_array($form['#bundle'], $info['bundles'])) {
        $options[$field_name] = $field_name;
      }
    }
    $element['target_field_name'] = [
      '#title' => t('Target field name'),
      '#type' => 'select',
      '#options' => ['' => $this->t('None')] + $options,
      '#default_value' => $this->getSetting('target_field_name'),
      '#description' => $this->t('Store the preview image of the PDF in the field name if empty.'),
      '#weight' => 15,
    ];
    $element['force_save'] = [
      '#title' => t('Force saving the preview image'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('force_save'),
      '#description' => $this->t('By default, if a target field name is selected, the preview image is saved into it only if this field is empty. Check this option to force saving the preview image even if the target field is not empty.'),
      '#weight' => 15,
    ];
    $element['description'] = [
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#rows' => 2,
      '#default_value' => $this->getSetting('description'),
      '#description' => $this->t('A text shown under the preview image generated for the PDF file.'),
      '#weight' => 15,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $preview_image_style = $this->getSetting('preview_image_style');
    $images_styles = image_style_options();
    if ($preview_image_style) {
      $summary[] = $this->t('Preview Image style used : @image_style', ['@image_style' => $images_styles[$preview_image_style]]);
    }
    else {
      $summary[] = $this->t('Original preview image');
    }
    $target_field_name = $this->getSetting('target_field_name');
    if ($target_field_name) {
      $summary[] = $this->t('Field <b>@field_name</b> used to save image preview.', ['@field_name' => $target_field_name]);
    }
    else {
      $summary[] = $this->t('PDF image preview no stored.');
    }
    $force_save = $this->getSetting('force_save');
    if ($force_save) {
      $summary[] = $this->t('Image preview always saved in field <b>@field_name</b>.', ['@field_name' => $target_field_name]);
    }
    else {
      $summary[] = $this->t('Image preview saved in field <b>@field_name</b> only if it is empty.', ['@field_name' => $target_field_name]);
    }
    $description = Xss::filter($this->getSetting('description'));
    if ($description) {
      $summary[] = $this->t('Description: @description', ['@description' => $description]);
    }
    else {
      $summary[] = $this->t('No description');
    }
    return $summary;
  }

  /**
   * Overrides \Drupal\Core\Field\WidgetBase::formMultipleElements().
   *
   * Special handling for draggable multiple widgets and 'add more' button.
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'file_pdf_preview/file_pdf_preview';
    $target_field_name = $this->getSetting('target_field_name') ?: '';
    $form_state->set('target_field_name', $target_field_name);
    $force_save = $this->getSetting('force_save') ?: FALSE;
    $form_state->set('force_save', $force_save);
    $description = Xss::filter(trim($this->getSetting('description')));
    $form_state->set('description', $description);
    $preview_image_style = $this->getSetting('preview_image_style') ?: '';
    $form_state->set('preview_image_style', $preview_image_style);
    return parent::formMultipleElements($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $target_field_name = $form_state->get('target_field_name');
    $element['#target_field_name'] = $target_field_name;
    $element['#file_pdf_preview_description'] = $form_state->get('description');
    $element['#preview_image_style'] = $form_state->get('preview_image_style');
    $element['#force_save'] = $form_state->get('force_save');

    // We try to retrieve the destination uri from the target field settings.
    $entity = $items[$delta]->getEntity();
    if ($target_field_name && $entity instanceOf ContentEntityInterface && $entity->hasField($target_field_name)) {
      $imageItem = $entity->get($target_field_name)->first();
      if ($imageItem instanceOf ImageItem) {
        $target_uri = $imageItem->getUploadLocation();
        $form_state->set('target_uri', $target_uri);
      }
    }

    // We look if a preview image has not been yet generated to prevent to
    // generate another one in the static process callback.
    $this->checkExistingFilePdfPreview($items, $delta, $element, $form, $form_state);
    return $element;
  }

  /**
   * Checks existing file preview and set it on the form_state if available.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The items.
   * @param $delta
   *   The delta.
   * @param array $element
   *   The element array.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function checkExistingFilePdfPreview(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_value = $element['#default_value'];
    if (!empty($default_value['target_id']) && empty($form_state->get('file_pdf_preview'))) {
      $target_id = $default_value['target_id'];
      $pdf = File::load($target_id);
      if ($pdf instanceof FileInterface) {
        $target_uri = $form_state->get('target_uri');
        $infos = static::getSourceDestination($pdf, $target_uri);
        if (!empty($infos['destination_uri'])) {
          try {
            $previews = $this->entityTypeManager->getStorage('file')->loadByProperties([
              'uri' => $infos['destination_uri'],
            ]);
          }
          catch (\Exception $e) {
            static::getLogger()->error($e->getMessage());
          }
          if (!empty($previews)) {
            $preview = reset($previews);
            if ($preview instanceof FileInterface) {
              $form_state->set('file_pdf_preview', $preview);
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);
    $fids = $element['fids']['#value'];
    if (!empty($fids)) {
      $fid = reset($fids);
      $file = File::load($fid);
      if ($file instanceof FileInterface) {
        $preview = $form_state->get('file_pdf_preview');
        if (!$preview instanceof FileInterface) {
          $target_uri = $form_state->get('target_uri');
          $preview = static::getImageFromPDF($file, $target_uri);
        }
        if ($preview instanceof FileInterface) {
          $uri = $preview->getFileUri();
          $image = \Drupal::service('image.factory')->get($uri);
          if ($image->isValid()) {
            $width = $image->getWidth();
            $height = $image->getHeight();
          }
          else {
            $width = $height = NULL;
          }
          if (!empty($uri)) {
            $element['preview'] = [
              '#weight' => -10,
              '#type' => 'container',
              '#attributes' => [
                'class' => [
                  'column--elements',
                  'file-pdf-preview--container',
                ],
              ],
            ];
            $element['preview']['image'] = [
              '#weight' => -10,
              '#theme' => 'image_style',
              '#width' => $width,
              '#height' => $height,
              '#style_name' => $element['#preview_image_style'],
              '#uri' => $uri,
            ];
            if (!empty($element['#file_pdf_preview_description'])) {
              $description = $element['#file_pdf_preview_description'];
              $element['preview']['description'] = [
                '#type' => '#markup',
                '#markup' => '<div class="description description--file-pdf-preview">' . $description . '</div>',
              ];
            }
            $element['#file_pdf_preview'] = $preview;
            $element['#attributes']['class'][] = 'inline--elements';
            if (empty($form_state->get('file_pdf_preview'))) {
              $form_state->set('file_pdf_preview', $preview);
            }
          }
        }
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function submit($form, FormStateInterface $form_state) {
    static::registerSaveAction($form, $form_state);
    parent::submit($form, $form_state);
  }

  /**
   * Register the save action into the private temp store.
   *
   * @param $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function registerSaveAction($form, FormStateInterface $form_state) {
    $save_action_registered = $form_state->get('save_action_registered');
    $triggering_element = $form_state->getTriggeringElement();
    $clicked_button = end($triggering_element['#parents']);
    if ($clicked_button === 'remove_button') {
      $form_state->set('file_pdf_preview', NULL);
      if ($save_action_registered) {
        $store = static::getTempStore();
        try {
          $store->delete($save_action_registered);
          $form_state->set('save_action_registered', FALSE);
        }
        catch (\Exception $e) {
          static::getLogger()->error($e->getMessage());
        }
      }
    }
    if ($save_action_registered) {
      return;
    }
    $entity = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof ContentEntityFormInterface) {
      $entity = $form_object->getEntity();
    }
    // We support the AddFormBase provided by media library for media entities.
    elseif ($form_object instanceof AddFormBase) {
      if (method_exists($form_object, 'getAddedMediaItems')) {
        $medias = $form_state->get('media');
        // Currently we only support only one media added form media library
        // widget.
        if (!empty($medias)) {
          $entity = reset($medias);
        }
      }
    }

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    $target_field_name = $form_state->get('target_field_name');
    $preview = $form_state->get('file_pdf_preview');
    $force_save = $form_state->get('force_save') ?: FALSE;
    if ($target_field_name && $preview instanceof FileInterface) {
      $store = static::getTempStore();
      $uuid = $entity->uuid();
      $key = $entity->getEntityTypeId() . ':' . $uuid;
      $values = [
        'file' => $preview,
        'target_field_name' => $target_field_name,
        'force_save' => $force_save,
      ];
      try {
        $store->set($key, $values);
        $form_state->set('save_action_registered', $key);
      }
      catch (\Exception $e) {
        static::getLogger()->error($e->getMessage());
        $form_state->set('save_action_registered', FALSE);
        return;
      }
    }
  }

  /**
   * Gets the private temp store.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   */
  protected static function getTempStore() {
    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory */
    $temp_store_factory = \Drupal::service('tempstore.private');
    return $temp_store_factory->get('file_pdf_preview');
  }

  /**
   * Gets the logger channel.
   *
   * @return \Psr\Log\LoggerInterface
   */
  protected static function getLogger() {
    return \Drupal::logger('file_pdf_preview');
  }

  /**
   * Generate image from PDF file.
   *
   * @param string $source
   *   File source path.
   * @param string $target
   *   File target path.
   *
   * @return string
   *   File path.
   *
   */
  protected static function generateImageFromPDF(string $source, string $target) {
    try {
      $pdf = new Pdf($source);
      $pdf->saveImage($target);
    }
    catch (\Exception $e) {
      static::getLogger()->error($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Create file entity.
   *
   * @param \Drupal\file\FileInterface $pdf
   *   The source file entity.
   * @param string|NULL $target_uri
   *   The specific target uri to save the pdf image.
   *   If NULL destination uri is defined from $pdf file .
   *
   * @return \Drupal\file\FileInterface|NULL $file
   *   The File entity.
   */
  protected static function getImageFromPDF(FileInterface $pdf, ?string $target_uri) {
    $file = NULL;
    if ($pdf->getMimeType() !== 'application/pdf') {
      return NULL;
    }
    $infos = static::getSourceDestination($pdf, $target_uri);
    $success = static::generateImageFromPDF($infos['source_path'], $infos['destination_path']);
    if ($success) {
      try {
        /** @var \Drupal\file\FileInterface $file */
        $file = File::create([
          'uri' => $infos['destination_uri'],
          'status' => FILE_STATUS_PERMANENT,
        ]);
        $file->save();
      }
      catch (\Exception $e) {
        static::getLogger()->error($e->getMessage());
        $file = NULL;
      }
    }
    return $file;
  }

  /**
   * Get the source and destination path from a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   * @param string|NULL $target_uri
   *   (optional) The destination uri for the generated image.
   *   If NULL, destination uri is defined from the pdf file.
   *
   * @return array
   *   An array with the source and destination.
   */
  protected static function getSourceDestination(FileInterface $file, ?string $target_uri) {
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $sourceUri = $file->getFileUri();
    $sourcePath = $file_system->realpath($sourceUri);

    if ($target_uri) {
      $file_system->prepareDirectory($target_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $filename = $file->getFilename();
      $destinationUri = $target_uri . DIRECTORY_SEPARATOR . $filename . '.jpg' ;
      $targetPath = $file_system->realpath($target_uri);
      $destinationPath = $targetPath . DIRECTORY_SEPARATOR . $filename . '.jpg';
    }
    else {
      $destinationUri = $sourceUri . '.jpg';
      $destinationPath = $sourcePath . '.jpg';
    }

    return [
      'source_uri' => $sourceUri,
      'destination_uri' => $destinationUri,
      'source_path' => $sourcePath,
      'destination_path' => $destinationPath,
    ];
  }
}

<?php

/**
 * @file
 * Contains \Drupal\page_manager\Form\StaticContextFormBase.
 */

namespace Drupal\page_manager\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\page_manager\PageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for editing and adding an access condition.
 */
abstract class StaticContextFormBase extends FormBase {

  /**
   * The page entity this static context belongs to.
   *
   * @var \Drupal\page_manager\PageInterface
   */
  protected $page;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The static context configuration.
   *
   * @var array
   */
  protected $staticContext;

  /**
   * Construct a new StaticContextFormBase.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }

  /**
   * Returns the text to use for the submit button.
   *
   * @return string
   *   The submit button text.
   */
  abstract protected function submitButtonText();

  /**
   * Returns the text to use for the submit message.
   *
   * @return string
   *   The submit message text.
   */
  abstract protected function submitMessageText();

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, PageInterface $page = NULL, $name = '') {
    $this->page = $page;
    $this->staticContext = $this->page->getStaticContext($name);

    // Allow the condition to add to the form.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->staticContext['label'] ?: '',
      '#required' => TRUE,
    ];
    $form['machine_name'] = [
      '#type' => 'machine_name',
      '#maxlength' => 64,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => [$this, 'contextExists'],
        'source' => ['label'],
      ],
      '#default_value' => $name,
    ];
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->entityManager->getEntityTypeLabels(TRUE),
      '#limit_validation_errors' => array(array('entity_type')),
      '#submit' => ['::rebuildSubmit'],
      '#executes_submit_callback' => TRUE,
      '#ajax' => array(
        'callback' => '::updateEntityType',
        'wrapper' => 'add-static-context-wrapper',
        'method' => 'replace',
      ),
    ];

    $entity = NULL;
    if ($form_state->hasValue('entity_type')) {
      $entity_type = $form_state->getValue('entity_type');
      if ($this->staticContext['value']) {
        $entity = $this->entityManager->loadEntityByUuid($entity_type, $this->staticContext['value']);
      }
    }
    elseif (!empty($this->staticContext['type'])) {
      list(, $entity_type) = explode(':', $this->staticContext['type']);
      $entity = $this->entityManager->loadEntityByUuid($entity_type, $this->staticContext['value']);
    }
    elseif ($this->entityManager->hasDefinition('node')) {
      $entity_type = 'node';
    }
    else {
      $entity_type = 'user';
    }

    $form['entity_type']['#default_value'] = $entity_type;

    $form['selection'] = [
      '#type' => 'entity_autocomplete',
      '#prefix' => '<div id="add-static-context-wrapper">',
      '#suffix' => '</div>',
      '#required' => TRUE,
      '#target_type' => $entity_type,
      '#default_value' => $entity,
      '#title' => $this->t('Select entity'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->submitButtonText(),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selection = $form_state->getValue('selection');
    $entity_type = $form_state->getValue('entity_type');
    $entity = $this->getEntityFromSelection($entity_type, $selection);

    $this->staticContext = [
      'label' => $form_state->getValue('label'),
      'type' => 'entity:' . $entity_type,
      'value' => $entity->uuid(),
    ];
    $this->page->setStaticContext($form_state->getValue('machine_name'), $this->staticContext);
    $this->page->save();

    // Set the submission message.
    drupal_set_message($this->submitMessageText());

    $form_state->setRedirectUrl($this->page->urlInfo('edit-form'));
  }

  /**
   * Get the entity from the selection.
   *
   * @param string $selection
   *   The value from the selection box.
   *
   * @return \Drupal\Core\Entity\Entity|null
   *   The entity reference in selection.
   */
  function getEntityFromSelection($entity_type, $selection) {
    if (!isset($selection)) {
      return NULL;
    }
    return $this->entityManager->getStorage($entity_type)->load($selection);
  }

  /**
   * Determines if a context with that name already exists.
   *
   * @param string $name
   *   The context name
   *
   * @return bool
   *   TRUE if the format exists, FALSE otherwise.
   */
  public function contextExists($name) {
    return isset($this->page->getContexts()[$name]);
  }

  /**
   * Submit handler for the entity_type select field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return $this
   */
  public function rebuildSubmit($form, FormStateInterface $form_state) {
    return $form_state->setRebuild();
  }

  /**
   * AJAX callback for the entity_type select field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The updated entity auto complete widget on the form.
   */
  public function updateEntityType($form, FormStateInterface $form_state) {
    return $form['selection'];
  }

}

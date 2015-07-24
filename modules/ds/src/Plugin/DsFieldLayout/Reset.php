<?php

/**
 * @file
 * Contains \Drupal\ds\Plugin\DsFieldLayout\Minimal.
 */

namespace Drupal\ds\Plugin\DsFieldLayout;

use Drupal\Component\Utility\SafeMarkup;

/**
 * Plugin for the reset field template.
 *
 * @DsFieldLayout(
 *   id = "reset",
 *   title = @Translation("Full reset"),
 *   theme = "theme_ds_field_reset",
 *   path = "includes/theme.inc"
 * )
 */
class Reset extends DsFieldLayoutBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(&$form) {
    $config = $this->getConfiguration();

    $form['lb'] = array(
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#size' => '10',
      '#default_value' => SafeMarkup::checkPlain($config['lb']),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = array();
    $config['lb'] = '';

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function massageRenderValues(&$field_settings, $values) {
    if (!empty($values['lb'])) {
      $field_settings['lb'] = $values['lb'];
    }
  }

}

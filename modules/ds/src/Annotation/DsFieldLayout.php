<?php

/**
 * @file
 * Contains Drupal\ds\Annotation\DsFieldLayout.
 */

namespace Drupal\ds\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a DsFieldLayout annotation object.
 *
 * @Annotation
 */
class DsFieldLayout extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the DS field layout plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The theme function for this field layout.
   *
   * @var string
   */
  public $theme;

}

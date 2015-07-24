<?php

/**
 * @file
 * Contains \Drupal\ds\Plugin\DsFieldLayout\Expert.
 */

namespace Drupal\ds_test\Plugin\DsFieldLayout;

use Drupal\ds\Plugin\DsFieldLayout\DsFieldLayoutBase;

/**
 * Plugin for the expert field template.
 *
 * @DsFieldLayout(
 *   id = "ds_test_theming_function",
 *   title = @Translation("Field test function"),
 *   theme = "ds_test_theming_function",
 * )
 */
class TestLayout extends DsFieldLayoutBase {

}

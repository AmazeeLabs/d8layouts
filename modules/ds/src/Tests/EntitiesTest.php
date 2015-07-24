<?php

/**
 * @file
 * Contains Drupal\ds\Tests\EntitiesTest.
 */

namespace Drupal\ds\Tests;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Cache\Cache;

/**
 * Tests for display of nodes and fields.
 *
 * @group ds
 */
class EntitiesTest extends BaseTest {

  /**
   * Disabled config schema checking temporarily until all errors are resolved.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Utility function to setup for all kinds of tests.
   *
   * @param $label
   *   How the body label must be set.
   */
  function entitiesTestSetup($label = 'above') {

    // Create a node.
    $settings = array('type' => 'article', 'promote' => 1);
    $node = $this->drupalCreateNode($settings);

    // Create field CSS classes.
    $edit = array('fields' => "test_field_class\ntest_field_class_2|Field class 2");
    $this->drupalPostForm('admin/structure/ds/classes', $edit, t('Save configuration'));

    // Create a token field.
    $token_field = array(
      'name' => 'Token field',
      'id' => 'token_field',
      'entities[node]' => '1',
      'content[value]' => '[node:title]',
    );
    $this->dsCreateTokenField($token_field);

    // Select layout.
    $this->dsSelectLayout();

    // Configure fields.
    $fields = array(
      'fields[dynamic_token_field:node-token_field][region]' => 'header',
      'fields[body][region]' => 'right',
      'fields[node_link][region]' => 'footer',
      'fields[body][label]' => $label,
      'fields[node_submitted_by][region]' => 'header',
      'fields[comment][region]' => 'hidden',
    );
    $this->dsConfigureUI($fields);

    return $node;
  }

  /**
   * Utility function to clear field settings.
   */
  function entitiesClearFieldSettings() {
    $display = entity_get_display('node', 'article', 'default');

    // Remove all third party settings from components.
    foreach ($display->getComponents() as $key => $info) {
      $info['third_party_settings'] = array();
      $display->setComponent($key, $info);
    }

    // Remove entity display third party settings.
    $tps = $display->getThirdPartySettings('ds');
    if (!empty($tps)) {
      foreach (array_keys($tps) as $key) {
        $display->unsetThirdPartySetting('ds', $key);
      }
    }

    // Save.
    $display->save();
  }

  /**
   * Set the label.
   */
  function entitiesSetLabelClass($label, $field_name, $text = '', $class = '', $show_colon = FALSE) {
    $edit = array(
      'fields[' . $field_name . '][label]' => $label,
    );
    if (!empty($text)) {
      $edit['fields[' . $field_name . '][settings_edit_form][third_party_settings][ds][ft][settings][lb]'] = $text;
    }
    if (!empty($class)) {
      $edit['fields[' . $field_name . '][settings_edit_form][third_party_settings][ds][ft][settings][classes][]'] = $class;
    }
    if ($show_colon) {
      $edit['fields[' . $field_name . '][settings_edit_form][third_party_settings][ds][ft][settings][lb-col]'] = '1';
    }
    $this->dsEditFormatterSettings($edit);
  }

  /**
   * Test basic node display fields.
   */
  function testDSNodeEntity() {

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entitiesTestSetup();

    // Look at node and verify token and block field.
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw('view-mode-full', 'Template file found (in full view mode)');
    $this->assertRaw('<div class="field-item">' . $node->getTitle() . '</div>', t('Token field found'));
    $this->assertRaw('group-header', 'Template found (region header)');
    $this->assertRaw('group-footer', 'Template found (region footer)');
    $this->assertRaw('group-left', 'Template found (region left)');
    $this->assertRaw('group-right', 'Template found (region right)');
    $this->assertPattern('/<div[^>]*>Submitted[^<]*<a[^>]+href="' . preg_quote(base_path(), '/') . 'user\/' . $node->getOwnerId() . '"[^>]*>' . SafeMarkup::checkPlain($node->getOwner()->getUsername()) . '<\/a>.*<\/div>/', t('Submitted by line found'));

    // Configure teaser layout.
    $teaser = array(
      'layout' => 'ds_2col',
    );
    $teaser_assert = array(
      'regions' => array(
        'left' => '<td colspan="8">' . t('Left') . '</td>',
        'right' => '<td colspan="8">' . t('Right') . '</td>',
      ),
    );
    $this->dsSelectLayout($teaser, $teaser_assert, 'admin/structure/types/manage/article/display/teaser');

    $fields = array(
      'fields[dynamic_token_field:node-token_field][region]' => 'left',
      'fields[body][region]' => 'right',
      'fields[node_links][region]' => 'right',
    );
    $this->dsConfigureUI($fields, 'admin/structure/types/manage/article/display/teaser');

    // Switch view mode on full node page.
    $edit = array('ds_switch' => 'teaser');
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertRaw('view-mode-teaser', 'Switched to teaser mode');
    $this->assertRaw('group-left', 'Template found (region left)');
    $this->assertRaw('group-right', 'Template found (region right)');
    $this->assertNoRaw('group-header', 'Template found (no region header)');
    $this->assertNoRaw('group-footer', 'Template found (no region footer)');

    $edit = array('ds_switch' => '');
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertRaw('view-mode-full', 'Switched to full mode again');

    // Test all options of a block field.
    $block = array(
      'name' => 'Test block field',
    );
    $this->dsCreateBlockField($block);
    $fields = array(
      'fields[dynamic_block_field:node-test_block_field][region]' => 'left',
      'fields[dynamic_token_field:node-token_field][region]' => 'hidden',
      'fields[body][region]' => 'hidden',
      'fields[node_links][region]' => 'hidden',
    );
    $this->dsConfigureUI($fields);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw('view-id-content_recent');

    /*
    $block = array(
      'block_render' => DS_BLOCK_TITLE_CONTENT,
    );
    $this->dsCreateBlockField($block, 'admin/structure/ds/fields/manage_block/test_block_field', FALSE);
    $this->drupalGet('node/' . $node->id());
    $this->assertNoRaw('<h2>Recent content</h2>');
    $this->assertRaw('Recent content');

    $block = array(
      'block_render' => DS_BLOCK_CONTENT,
    );
    $this->dsCreateBlockField($block, 'admin/structure/ds/fields/manage_block/test_block_field', FALSE);
    $this->drupalGet('node/' . $node->id());
    $this->assertNoRaw('<h2>Recent content</h2>');
    $this->assertNoRaw('Recent content');
    */

    // Test revisions. Enable the revision view mode
    $edit = array(
      'display_modes_custom[revision]' => '1'
    );
    $this->drupalPostForm('admin/structure/types/manage/article/display', $edit, t('Save'));

    // Enable the override revision mode and configure it
    $edit = array(
      'fs3[override_node_revision]' => TRUE,
      'fs3[override_node_revision_view_mode]' => 'revision'
    );
    $this->drupalPostForm('admin/structure/ds/settings', $edit, t('Save configuration'));

    // Select layout and configure fields.
    $edit = array(
      'layout' => 'ds_2col',
    );
    $assert = array(
      'regions' => array(
        'left' => '<td colspan="8">' . t('Left') . '</td>',
        'right' => '<td colspan="8">' . t('Right') . '</td>',
      ),
    );
    $this->dsSelectLayout($edit, $assert, 'admin/structure/types/manage/article/display/revision');
    $edit = array(
      'fields[body][region]' => 'left',
      'fields[node_link][region]' => 'right',
      'fields[node_author][region]' => 'right',
    );
    $this->dsConfigureUI($edit, 'admin/structure/types/manage/article/display/revision');

    // Create revision of the node.
    $edit = array(
      'revision' => TRUE,
      'revision_log[0][value]' => 'Test revision',
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertText('Revisions');

    // Assert revision is using 2 col template.
    $this->drupalGet('node/' . $node->id() . '/revisions/1/view');
    $this->assertText('Body', 'Body label');

    // Assert full view is using stacked template.
    $this->drupalGet('node/' . $node->id());
    $this->assertNoText('Body', 'No Body label');

    // Test formatter limit on article with tags.
    $edit = array(
      'ds_switch' => '',
      'field_tags[target_id]' => 'Tag 1, Tag 2'
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $edit = array(
      'fields[field_tags][region]' => 'right',
    );
    $this->dsConfigureUI($edit, 'admin/structure/types/manage/article/display');
    $this->drupalGet('node/' . $node->id());
    $this->assertText('Tag 1');
    $this->assertText('Tag 2');
    $edit = array(
      'fields[field_tags][settings_edit_form][third_party_settings][ds][ds_limit]' => '1',
    );
    $this->dsEditLimitSettings($edit, 'field_tags');
    $this->drupalGet('node/' . $node->id());
    $this->assertText('Tag 1');
    $this->assertNoText('Tag 2');

    // Test \Drupal\Component\Utility\SafeMarkup::checkPlain() on ds_render_field() with the title field.
    $edit = array(
      'fields[node_title][region]' => 'right',
    );
    $this->dsConfigureUI($edit, 'admin/structure/types/manage/article/display');
    $edit = array(
      'title[0][value]' => 'Hi, I am an article <script>alert(\'with a javascript tag in the title\');</script>',
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw('<h2>Hi, I am an article &lt;script&gt;alert(&#039;with a javascript tag in the title&#039;);&lt;/script&gt;</h2>');
  }

  /**
   * Tests on field templates.
   */
  function testDSFieldTemplate() {
    // Get a node.
    $node = $this->entitiesTestSetup('hidden');
    $body_field = $node->body->value;

    // -------------------------
    // Default theming function.
    // -------------------------
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div data-quickedit-field-id=\"node/1/body/en/full\" class=\"field field-node--body field-name-body field-type-text-with-summary field-label-hidden\">");
    $this->entitiesSetLabelClass('above', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div data-quickedit-field-id=\"node/1/body/en/full\" class=\"field field-node--body field-name-body field-type-text-with-summary field-label-above\">
      <div class=\"field-label\">Body</div>
    <div class=\"field-items\">
          <div property=\"schema:text\" class=\"field-item\">" . $body_field . "</div>");

    $this->entitiesSetLabelClass('above', 'body', 'My body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div data-quickedit-field-id=\"node/1/body/en/full\" class=\"field field-node--body field-name-body field-type-text-with-summary field-label-above\">
      <div class=\"field-label\">My body</div>
    <div class=\"field-items\">
          <div property=\"schema:text\" class=\"field-item\">" . $body_field . "</div>");

    $this->entitiesSetLabelClass('hidden', 'body', '', 'test_field_class');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"test_field_class field field-node--body field-name-body field-type-text-with-summary field-label-hidden\" data-quickedit-field-id=\"node/1/body/en/full\">
    <div class=\"field-items\">
          <div property=\"schema:text\" class=\"field-item\">" . $body_field . "</div>");
  }

  /**
   * Tests on field templates.
   */
  function testDSFieldTemplate2() {
    // Get a node.
    $node = $this->entitiesTestSetup('hidden');
    $body_field = $node->body->value;

    // -----------------------
    // Reset theming function.
    // -----------------------
    $edit = array(
      'fs1[ft-default]' => 'reset',
    );
    $this->drupalPostForm('admin/structure/ds/settings', $edit, t('Save configuration'));
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          " . $body_field);

    $this->entitiesSetLabelClass('above', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"field-label-above\">Body</div>" . $body_field);

    $this->entitiesSetLabelClass('inline', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"field-label-inline\">Body</div>" . $body_field);

    $this->entitiesSetLabelClass('above', 'body', 'My body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"field-label-above\">My body</div>" . $body_field);

    $this->entitiesSetLabelClass('inline', 'body', 'My body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"field-label-inline\">My body</div>" . $body_field);

    $edit = array(
      'fs1[ft-show-colon]' => 'reset',
    );
    $this->drupalPostForm('admin/structure/ds/settings', $edit, t('Save configuration'));
    $tags = $node->getCacheTags();
    Cache::invalidateTags($tags);

    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"field-label-inline\">My body:</div>" . $body_field);

    $this->entitiesSetLabelClass('hidden', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          " . $body_field);
  }

  /**
   * Tests on field templates.
   */
  function testDSFieldTemplate3() {
    // Get a node.
    $node = $this->entitiesTestSetup('hidden');
    $body_field = $node->body->value;

    // ----------------------
    // Custom field function.
    // ----------------------

    // With outer wrapper.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][id]' => 'expert',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
    );
    $this->dsEditFormatterSettings($edit);

    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div>" . $body_field . "</div>");

    // With outer div wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\">" . $body_field . "</div>");

    // With outer span wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class-2'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <span class=\"ow-class-2\">" . $body_field . "</span>");
  }

  /**
   * Tests on field templates.
   */
  function testDSFieldTemplate4() {

    // Get a node.
    $node = $this->entitiesTestSetup('hidden');
    $body_field = $node->body->value;

    // With outer wrapper and field items wrapper.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][id]' => 'expert',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div><div>" . $body_field . "</div></div>");

    // With outer wrapper and field items div wrapper with class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div><div class=\"fi-class\">" . $body_field . "</div></div>");

    // With outer wrapper and field items span wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div><span class=\"fi-class\">" . $body_field . "</span></div>");

    // With outer wrapper class and field items span wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><span class=\"fi-class\">" . $body_field . "</span></div>");

    // With outer wrapper span class and field items span wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class-2'
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <span class=\"ow-class\"><span class=\"fi-class-2\">" . $body_field . "</span></span>");
  }

  /**
   * Tests on field templates.
   */
  function testDSFieldTemplate5() {
    // Get a node.
    $node = $this->entitiesTestSetup('hidden');
    $body_field = $node->body->value;

    // With field item div wrapper.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][id]' => 'expert',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div>" . $body_field . "</div>");

    // With field item span wrapper.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'span',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <span>" . $body_field . "</span>");

    // With field item span wrapper and class.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-cl]' => 'fi-class',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <span class=\"fi-class\">" . $body_field . "</span>");

    // With fis and fi.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class-2',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-cl]' => 'fi-class',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"fi-class-2\"><div class=\"fi-class\">" . $body_field . "</div></div>");
    // With all wrappers.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class-2',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-cl]' => 'fi-class',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    // With all wrappers and attributes.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-at]' => 'name="ow-att"',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class-2',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-at]' => 'name="fis-att"',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-cl]' => 'fi-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-at]' => 'name="fi-at"',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\" name=\"ow-att\"><div class=\"fi-class-2\" name=\"fis-att\"><span class=\"fi-class\" name=\"fi-at\">" . $body_field . "</span></div></div>");

    // Remove attributes.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-at]' => '',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-cl]' => 'fi-class-2',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fis-at]' => '',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-el]' => 'span',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-cl]' => 'fi-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-at]' => '',
    );
    $this->dsEditFormatterSettings($edit);

    // Label tests with custom function.
    $this->entitiesSetLabelClass('above', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"field-label-above\">Body</div><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    $this->entitiesSetLabelClass('inline', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"field-label-inline\">Body</div><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    $this->entitiesSetLabelClass('above', 'body', 'My body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"field-label-above\">My body</div><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    $this->entitiesSetLabelClass('inline', 'body', 'My body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"field-label-inline\">My body</div><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    $this->entitiesSetLabelClass('inline', 'body', 'My body', '', TRUE);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"field-label-inline\">My body:</div><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    $this->entitiesSetLabelClass('hidden', 'body');
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class\"><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    // Test default classes on outer wrapper.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-def-cl]' => '1',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class field-node--body field-name-body field-type-text-with-summary field-label-hidden\"><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    // Test default attributes on field item.
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-el]' => 'div',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-cl]' => 'ow-class',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][ow-def-cl]' => '1',
      'fields[body][settings_edit_form][third_party_settings][ds][ft][settings][fi-def-at]' => '1',
    );
    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          <div class=\"ow-class field-node--body field-name-body field-type-text-with-summary field-label-hidden\"><div class=\"fi-class-2\"><span class=\"fi-class\">" . $body_field . "</span></div></div>");

    // Use the test field theming function to test that this function is
    // registered in the theme registry through ds_extras_theme().
    $edit = array(
      'fields[body][settings_edit_form][third_party_settings][ds][ft][id]' => 'ds_test_theming_function',
    );

    $this->dsEditFormatterSettings($edit);
    $this->drupalGet('node/' . $node->id());
    $this->assertRaw("<div class=\"group-right\">
          Testing field output through custom function
      </div>");
  }
}

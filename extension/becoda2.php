<?php

require_once 'becoda2.civix.php';
require_once 'hooks.php';

/**
 * Implementation of hook_civicrm_config
 */
function becoda2_civicrm_config(&$config) {
  _becoda2_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function becoda2_civicrm_xmlMenu(&$files) {
  _becoda2_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function becoda2_civicrm_install() {
  //add the required option groups
  becoda2_civicrm_install_options(becoda2_civicrm_options());

  return _becoda2_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function becoda2_civicrm_uninstall() {
  return _becoda2_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function becoda2_civicrm_enable() {
  return _becoda2_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function becoda2_civicrm_disable() {
  return _becoda2_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function becoda2_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _becoda2_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function becoda2_civicrm_managed(&$entities) {
  return _becoda2_civix_civicrm_managed($entities);
}

function becoda2_civicrm_install_options($data) {
  foreach ($data as $groupName => $group) {
    // check group existence
    $result = civicrm_api('option_group', 'getsingle', array('version' => 3, 'name' => $groupName));
    if (isset($result['is_error']) && $result['is_error']) {
      $params = array(
          'version' => 3,
          'sequential' => 1,
          'name' => $groupName,
          'is_reserved' => 1,
          'is_active' => 1,
          'title' => $group['title'],
          'description' => $group['description'],
      );
      $result = civicrm_api('option_group', 'create', $params);
      $group_id = $result['values'][0]['id'];
    }
    else
      $group_id = $result['id'];

    if (is_array($group['values'])) {
      $groupValues = $group['values'];
      $weight = 1;
      //print_r(array_keys($groupValues));
      foreach ($groupValues as $valueName => $value) {
        $result = civicrm_api('option_value', 'getsingle', array('version' => 3, 'name' => $valueName));
        if (isset($result['is_error']) && $result['is_error']) {
          $params = array(
              'version' => 3,
              'sequential' => 1,
              'option_group_id' => $group_id,
              'name' => $valueName,
              'label' => $value['label'],
              'value' => $value['value'],
              'weight' => $weight,
              'is_default' => $value['is_default'],
              'is_active' => 1,
          );
          $result = civicrm_api('option_value', 'create', $params);
        } else {
          $weight = $result['weight'] + 1;
        }
      }
    }
  }
}

function becoda2_civicrm_options() {
  return array(
      'civicrm_banking.plugin_types' => array(
          'values' => array(
              'CODA 2.3 (Belgium)' => array(
                  'label' => 'CODA 2.3 Import Plugin',
                  'value' => 'CRM_Becoda2_Plugin_Becoda2',
                  'is_default' => 0,
              ),
          ),
      ),
  );
}

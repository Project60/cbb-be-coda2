<?php

/**
 * This hook makes it possible to implement PRE hooks by definine the appropriate method in a logic class
 * 
 * @param type $op
 * @param type $objectName
 * @param type $id
 * @param type $params
 */
function becoda23_civicrm_pre($op, $objectName, $id, &$params) {
  $parts = array(
      'hook',
      'pre',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Becoda23_Logic', $methodName)) {
    CRM_Becoda23_Logic::debug(ts('Calling CODA32 Logic'), $methodName, 'alert');
    CRM_Becoda23_Logic::$methodName($id, $params);
  }
}

/**
 * This hook makes it possible to implement POST hooks by definine the appropriate method in a logic class
 * 
 * @param type $op
 * @param type $objectName
 * @param type $id
 * @param type $params
 */
function becoda23_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  $parts = array(
      'hook',
      'post',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Becoda23_Logic', $methodName)) {
    CRM_Becoda23_Logic::debug(ts('Calling CODA32 Logic'), $methodName, 'alert');
    CRM_Becoda23_Logic::$methodName($objectId, $objectRef);
  }
}


// totten's addition
function becoda23_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
}

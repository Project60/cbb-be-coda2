<?php

/**
 * This hook makes it possible to implement PRE hooks by definine the appropriate method in a logic class
 * 
 * @param type $op
 * @param type $objectName
 * @param type $id
 * @param type $params
 */
function becoda2_civicrm_pre($op, $objectName, $id, &$params) {
  $parts = array(
      'hook',
      'pre',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Becoda2_Logic', $methodName)) {
    CRM_Becoda2_Logic::debug(ts('Calling CODA2 Logic'), $methodName, 'alert');
    CRM_Becoda2_Logic::$methodName($id, $params);
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
function becoda2_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  $parts = array(
      'hook',
      'post',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Becoda2_Logic', $methodName)) {
    CRM_Becoda2_Logic::debug(ts('Calling CODA2 Logic'), $methodName, 'alert');
    CRM_Becoda2_Logic::$methodName($objectId, $objectRef);
  }
}


// totten's addition
function becoda2_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
}

function civicrm_banking_format_iban($v) {
  switch (substr($v,0,2)) {
    case 'BE' :
      return substr($v,0,4) . ' ' . substr($v,4,4) . ' ' . substr($v,8,4) . ' ' . substr($v,12,4);
      break;
    default:
      return $v;
  }
}


<?php
/*
    org.project60.banking extension for CiviCRM

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 *
 * @package org.project60.banking
 * @copyright GNU Affero General Public License
 * $Id$
 *
 */
class CRM_Becoda23_PluginImpl_Becoda23 extends CRM_Banking_PluginModel_Importer {

  /**
   * class constructor
   */ function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;
  }

  /** 
   * the plugin's user readable name
   * 
   * @return string
   */
  static function displayName()
  {
    return 'CODA 2.3 Importer';
  }

  /** 
   * Report if the plugin is capable of importing files
   * 
   * @return bool
   */
  static function does_import_files()
  {
    return true;
  }

  /** 
   * Report if the plugin is capable of importing streams, i.e. data from a non-file source, e.g. the web
   * 
   * @return bool
   */
  static function does_import_stream()
  {
    return false;
  }

  /** 
   * Test if the given file can be imported
   * 
   * @var 
   * @return TODO: data format? 
   */
  function probe_file( $file_path, $params )
  {
    // TODO: implement
    return true;
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_file( $file_path, $params )
  {
    // begin
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));

    // put real code here 
    
    $this->reportDone();
  }



  /** 
   * Test if the configured source is available and ready
   * 
   * @var 
   * @return TODO: data format?
   */
  function probe_stream( $params )
  {
    return false;
  }

  /** 
   * Import the given file
   * 
   * @return TODO: data format? 
   */
  function import_stream( $params )
  {
    $this->reportDone(ts("Importing streams not supported by this plugin."));
  }
}


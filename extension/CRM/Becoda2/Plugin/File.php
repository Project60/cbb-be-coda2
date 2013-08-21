<?php

class CRM_Becoda2_PluginImpl_File {
  /*
   * Contains the full pathname of the file being processed
   */

  protected $_pathname;

  /*
   * File handle used by all operations
   */
  protected $_fh;

  public function __construct($pathname) {
    $this->_pathname = $pathname;
    $this->init();
  }

  protected function init() {
    $this->fh = fopen($this->_pathname);
  }

  protected function close() {
    fclose( $this->_fh);
  }
  
  /**
   * Return a CRM_Banking_BAO_BankTransactionBatch instance
   * 
   * Every file contains a sequence of batch representations. This function 
   * iterates over all of them. Returning null is the equivalent of EOF.
   */
  public function nextBatch() {
    // read next header/balance lines
    // create batch instance and return it
    return null;
  }

  /**
   * Return a CRM_Banking_BAO_BankTransaction instance
   * 
   * Every section of the file, which represents an individual CODA file, 
   * contains a sequence of individual records, some of which represent an
   * individual BankTransaction. Returning null is the equivalent of EOF. 
   */
  public function nextRecord() {
    // while there are additional movements, return them
    // read the end balance/footer records and update the batch instance
    return null;
  }

}

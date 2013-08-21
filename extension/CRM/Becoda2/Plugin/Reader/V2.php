<?php

/*
 * From the CODA specification : 
 * 
 * "...
 * Each file containing movement at least consists of records 0, 1, 2, 8 and 9.
 * Records 3 will be included if they give extra information about record 2, 
 * which precedes.
 * 
 * The codes serve to identify the various records :
 *    0 = header record;
 *    1 = old balance;
 *    2 = movement. Part 1 is always mentioned, parts 2 and 3 will be mentioned 
 *        if necessary.
 *    3 = additional information
 *    8 = new balance  
 *    (4) = free communications
 *    9 = trailer record
 * ..."
 * 
 * The Reader instance needs to be able to process the header/footer and balance
 * records to serve the nextBatch() functionality of the File class. 
 * 
 * It aso needs to process the record types 2 and 3 representing movements.
 */

class CRM_Becoda2_PluginImpl_Reader_V2 {
  
}

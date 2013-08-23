<?php

/*
 * Find Bic using Bban
 */

class CodaBbanToBic{

    public function __construct() {
    }

    public function filter($bban){
		$bankidnumber = substr($bban, 0, 3);
        $qs = new dao('bic', 'codaDBO');
        $qs->f('T_Identification_Number', $bankidnumber);
        $res = $qs->read();		
		if(!$res) {
			throw new Exception("Couldn't find BIC for BBAN: $bban");
		}
        $biccode = $res[0]['Biccode'];
		return $biccode;
	}
   
}


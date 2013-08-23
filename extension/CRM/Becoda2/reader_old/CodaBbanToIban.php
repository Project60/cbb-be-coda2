<?php

/*
 * Find Iban using Bban
 * 
 */
class CodaBbanToIban{

    public function __construct() {

    }
    
	public function filter($params){
		if(empty($params['bban'])){
			return null;
		}
		$bban = $params['bban'];

		if(!array_key_exists('bic', $params) || empty($params['bic']))
		{
			$filter = new CodaBbanToBic();
			$bic = $filter->filter($bban);
		} else {
			$bic = $params['bic'];
		}

		$bban = substr(preg_replace("/\-|\ /", "", $bban), 0, 12);

		$bic = substr(preg_replace("/\ /", "", $bic), 0, 8);

		$countrycode = substr($bic, 4, 2);

		$tempban = $bban . $countrycode . "00";

		$patternArray = array("/A/","/B/","/C/","/D/","/E/","/F/","/G/","/H/","/I/","/J/",
				"/K/","/L/","/M/","/M/","/N/","/O/","/P/","/Q/","/R/","/S/","/T/","/U/",
				"/V/","/W/","/X/","/Y/","/Z/");

		$replaceArray = range(10, 35);

		//alfa waarden($patternArray) vervangen met numerieke($replaceArray)
		$tempban = preg_replace($patternArray, $replaceArray, $tempban);

		//modulo 97 aftrekken van 98
		$mod = 98 - (bcmod($tempban, "97"));

		if(strLen($mod)==1){
			$mod = "0" . $mod;
		}

		$iban = $countrycode . $mod . $bban;

		return $iban;
	}
}

<?php
/**
* CURL POST
* @param string $url
* @param string $fields_string
* @return void
* @author 2c2p
*/

namespace P2c2p\P2c2pPayment\Helper;

Class HTTP {
       function post($url,$fields_string)
       {
            //open connection
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
			//curl_setopt ($ch, CURLOPT_SSLVERSION, 6);

			
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

            //execute post
            $result = curl_exec($ch); //close connection
            curl_close($ch);
            return $result;
    } 
}
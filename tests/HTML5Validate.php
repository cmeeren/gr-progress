<?php

/**
* PHP class for validating HTML5
* 
* Free to use as you please. If you redistribute, in original or in modified form, you must reference to https://www.404it.no/en/blog/php_class_for_validating_html5  
* This software has no warranty. Use at your own risk. Author takes no responsibility for any consequences of using this software.  
*
* @author Per Kristian Haakonsen
* @copyright 2015 404it
* @link https://www.404it.no/en/blog/php_class_for_validating_html5
*
*/

class HTML5Validate {
	
	public $message;
	public $html;
	
	private $urlHTTP='http://validator.w3.org/nu/?out=gnu';
	private $urlHTTPS='https://validator.nu/?out=gnu';
	private $url;
	
	/**
	* Constructor.
	*
	* @param bool $ssl
	*/
	function HTML5Validate($ssl=true) {
		$this->url=$ssl?$this->urlHTTPS:$this->urlHTTP;
	}
	
	/**
	* Validates whether a string is valid HTML5 or not. String can be snippet of HTML or complete document.
	*
	* @param string $html
	*/
	function Assert($html) {
		$this->html=$this->HasDoctype($html)?$html:$this->WrapDoctype($html);
		$this->message=$this->SendPOSTRequest($this->html);
		return $this->ResponseHasErrors($this->message);
	}
	
	private function SendPOSTRequest($html) {

		$request=curl_init($this->url);
		curl_setopt($request, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($request, CURLOPT_POSTFIELDS, $html);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($request, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
		curl_setopt($request, CURLOPT_HTTPHEADER, array(
		    'Content-type: text/html; charset=utf-8',
    		'Content-Length: ' . strlen($html))
		);

		$result=curl_exec($request);
		
		if(curl_errno($request))
			throw new Exception('cURL error (are you connected to Internet?): '.curl_error($request));
		
		return $result;
	}
	
	private function ResponseHasErrors($response) {
		return (stripos($response, 'error') === false && stripos($response, 'warning') === false); 
	}
	
	private function HasDoctype($html) {
		return stripos($html, 'html>')!==false;
	}
	
	private function WrapDoctype($html) {
		return '<!DOCTYPE html><html><head><meta charset=utf-8 /><title>Any Title</title></head><body>'.$html.'</body></html>';
	}
	
}

?>
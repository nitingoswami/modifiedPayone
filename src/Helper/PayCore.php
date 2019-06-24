<?php
namespace Mapa\Helper;

use Plenty\Plugin\ConfigRepository;

/**
 * Class PayCore
 * @package Mapa\Helper
 */
class PayCore
{

  /**
   * ContactService constructor.
   */
  public function __construct()
  {
  }

  var $response   = '';
  var $error      = '';

  var $live_url       = 'https://ctpe.net/frontend/payment.prc';
  var $demo_url       = 'https://test.ctpe.net/frontend/payment.prc';
  var $live_xml_url   = 'https://ctpe.io/payment/ctpe';
  var $test_xml_url   = 'https://test.ctpe.io/payment/ctpe';
  var $live_query_url = 'https://ctpe.io/payment/query';
  var $test_query_url = 'https://test.ctpe.io/payment/query';

  var $availablePayments = array('CC','DD','DC','VA','OT','IV','PP','UA');
  var $pageURL = '';
  var $actualPaymethod = 'CC';

  var $lastError = '';
  var $lastErrorCode = '';

  function prepareData($orderId, $amount, $currency, $conf, $userData, $capture = false, $uniqueId = NULL)
  {
    $mode = $conf['PAY_MODE'];
    $lang = $conf['LANGUAGE'];
    $payCode = strtoupper($conf['PAY_CODE']);
    $amount = sprintf('%1.2f', $amount);
    $currency = strtoupper($currency);

    $parameters['SECURITY.SENDER']              = $conf['SECURITY_SENDER'];
    $parameters['USER.LOGIN']                   = $conf['USER_LOGIN'];
    $parameters['USER.PWD']                     = $conf['USER_PWD'];
    $parameters['TRANSACTION.CHANNEL']          = $conf['TRANSACTION_CHANNEL'];
    $parameters['TRANSACTION.MODE']             = $conf['TRANSACTION_MODE'];
    $parameters['REQUEST.VERSION']              = "1.0";
    $parameters['IDENTIFICATION.TRANSACTIONID'] = $orderId;

    if (!empty($userData['userid']))
      $parameters['IDENTIFICATION.SHOPPERID']   = $userData['userid'];

    if ($payCode == 'RM'){
      $parameters['FRONTEND.ENABLED']           = "false";
    } else if ($capture){
      $parameters['FRONTEND.ENABLED']           = "false";
      if (!empty($uniqueId)){
        $parameters['ACCOUNT.REGISTRATION']     = $uniqueId;
      }
    } else {
      $parameters['FRONTEND.ENABLED']           = "true";
    }

    if (!empty($conf['FRONTEND_HEIGHT'])){
      $parameters['FRONTEND.HEIGHT']            = $conf['FRONTEND_HEIGHT'];
    } else {
      $parameters['FRONTEND.HEIGHT']            = "250";
    }

		$parameters['FRONTEND.REDIRECT_TIME']       = "0";
    $parameters['FRONTEND.POPUP']               = "false";
    $parameters['FRONTEND.MODE']                = "DEFAULT";
    $parameters['FRONTEND.LANGUAGE']            = $lang;
    $parameters['FRONTEND.LANGUAGE_SELECTOR']   = "true";
    $parameters['FRONTEND.ONEPAGE']             = "true";
    #$parameters['FRONTEND.RETURN_ACCOUNT']      = "true";
    $parameters['FRONTEND.NEXTTARGET']          = "top.location.href";

    if (!empty($conf['STYLE_URL'])){
      $parameters['FRONTEND.CSS_PATH']          = $conf['STYLE_URL'];
    }
    if (!empty($conf['IMG_PAY_URL'])){
      $parameters['FRONTEND.BUTTON.1.NAME']     = 'PAY';
      $parameters['FRONTEND.BUTTON.1.TYPE']     = 'IMAGE';
      $parameters['FRONTEND.BUTTON.1.LINK']     = $conf['IMG_PAY_URL'];
    }
    if (!empty($conf['IMG_BACK_URL'])){
      $parameters['FRONTEND.BUTTON.2.NAME']     = 'CANCEL';
      $parameters['FRONTEND.BUTTON.2.TYPE']     = 'IMAGE';
      $parameters['FRONTEND.BUTTON.2.LINK']     = $conf['IMG_BACK_URL'];
    }

    if ($conf['ACTPM'] == 'PP'){
      $parameters['ACCOUNT.BRAND']          = 'PAYPAL';
      $parameters['FRONTEND.PM.DEFAULT_DISABLE_ALL']  = 'true';
      $parameters['FRONTEND.PM.1.ENABLED']            = 'true';
      $parameters['FRONTEND.PM.1.METHOD']             = 'VA';
      $parameters['FRONTEND.PM.1.SUBTYPES']           = 'PAYPAL';
      $payCode = 'VA';
    } else if ($conf['ACTPM'] == 'PF'){
      $parameters['ACCOUNT.BRAND']          = 'PF_KARTE_DIRECT';
      $parameters['ACCOUNT.ID']             = $userData['email'];
      $parameters['FRONTEND.ENABLED']       = "false";
      //$currency = 'CHF';
      $payCode = 'VA';
    }
    
    if ($conf['ACTPM'] != 'PP'){
      foreach($this->availablePayments as $key=>$value) {
        if ($value != $payCode) {
          $parameters["FRONTEND.PM." . (string)($key + 1) . ".METHOD"] = $value;
          $parameters["FRONTEND.PM." . (string)($key + 1) . ".ENABLED"] = "false";
        }
      }
    }
    
    $parameters['PAYMENT.CODE']                 = $payCode.".".$mode;
    $parameters['FRONTEND.RESPONSE_URL']        = $conf['RESPONSE_URL'];
    $parameters['NAME.GIVEN']                   = trim($userData['firstname']);
    $parameters['NAME.FAMILY']                  = trim($userData['lastname']);
    $parameters['ADDRESS.STREET']               = $userData['street'];
    $parameters['ADDRESS.ZIP']                  = $userData['zip'];
    $parameters['ADDRESS.CITY']                 = $userData['city'];
    $parameters['ADDRESS.COUNTRY']              = $userData['country'];
    $parameters['CONTACT.EMAIL']                = $userData['email'];
    $parameters['PRESENTATION.AMOUNT']          = $amount; // 99.00
    $parameters['PRESENTATION.CURRENCY']        = $currency; // EUR
    $parameters['ACCOUNT.COUNTRY']              = $userData['country'];
    $parameters['CONTACT.IP']                   = $userData['ip'];
    $parameters['CONTACT.PHONE']                = $userData['phone'];

    if (!empty($userData['mobile']))
      $parameters['CONTACT.MOBILE']             = $userData['mobile'];

    if (!empty($userData['dob']))
      $parameters['NAME.BIRTHDATE']             = $userData['dob'];

    if (!empty($userData['sex']))
      $parameters['NAME.SEX']                   = $userData['sex'];

    if (!empty($userData['company']))
      $parameters['NAME.COMPANY']               = $userData['company'];

    return $parameters;
  }

  function doRequest($data, $xml = NULL, $query = false)
  {
    $url = $this->demo_url;
    $xmlUrl = $this->test_xml_url;
    if ($query) $xmlUrl = $this->test_query_url;
    if ($data['TRANSACTION.MODE'] == 'LIVE'){
      $url = $this->live_url;
      $xmlUrl = $this->live_xml_url;
      if ($query) $xmlUrl = $this->live_query_url;
    }

    $pString = '';
    foreach ($data AS $k => $v) {
      $pString.= '&'.strtoupper($k).'='.urlencode(utf8_decode($v));
      //$pString.= '&'.strtoupper($k).'='.$v;
    }
    $pString = stripslashes($pString);
    if (!empty($xml)) {
      $pString = 'load='.urlencode($xml);
      //$pString = 'load='.$xml;
      $url = $xmlUrl;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $pString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
    curl_setopt($ch, CURLOPT_USERAGENT, "payment request");

    $this->response     = curl_exec($ch);
    $this->error        = curl_error($ch);
    curl_close($ch);

    $res = $this->response;
    if (!$this->response && $this->error){
      $msg = urlencode('Curl Fehler');
      //$msg = 'Curl Fehler';
      $res = 'status=FAIL&msg='.$this->error;
    }

    return $res;
  }

  function parseResult($curlresultURL)
  {
    $r_arr=explode("&",$curlresultURL);
    foreach($r_arr AS $buf) {
      $temp=urldecode($buf);
      $temp=explode("=",$temp,2);
      $postatt=$temp[0];
      $postvar=$temp[1];
      $returnvalue[$postatt]=$postvar;
    }
    $processingresult = $returnvalue['PROCESSING.RESULT'];
    if (empty($processingresult)) $processingresult = $returnvalue['POST.VALIDATION'];
    $redirectURL = $returnvalue['FRONTEND.REDIRECT_URL'];
    if (!isset($returnvalue['PROCESSING.RETURN']) && $returnvalue['POST.VALIDATION'] > 0){
      $returnvalue['PROCESSING.RETURN'] = 'Errorcode: '.$returnvalue['POST.VALIDATION'];
    }
    ksort($returnvalue);
    return array('result' => $processingresult, 'url' => $redirectURL, 'all' => $returnvalue);
  }

} // end of class
?>

<?php

/**
 * The AuthorizeNet PHP SDK. Include this file in your project.
 *
 * @package AuthorizeNet
 */

 App::import('Vendor', 'AuthorizeNetRequest', array('file' => 'AuthorizeNet'.DS.'shared'.DS.'AuthorizeNetRequest.php'));
 App::import('Vendor', 'AuthorizeNetTypes', array('file' => 'AuthorizeNet'.DS.'shared'.DS.'AuthorizeNetTypes.php'));
 App::import('Vendor', 'AuthorizeNetXMLResponse', array('file' => 'AuthorizeNet'.DS.'shared'.DS.'AuthorizeNetXMLResponse.php'));
 App::import('Vendor', 'AuthorizeNetResponse', array('file' => 'AuthorizeNet'.DS.'shared'.DS.'AuthorizeNetResponse.php'));
 App::import('Vendor', 'AuthorizeNetAIM', array('file' => 'AuthorizeNet'.DS.'AuthorizeNetAIM.php'));

//require dirname(__FILE__) . '/lib/shared/AuthorizeNetRequest.php';
//require dirname(__FILE__) . '/lib/shared/AuthorizeNetTypes.php';
//require dirname(__FILE__) . '/lib/shared/AuthorizeNetXMLResponse.php';
//require dirname(__FILE__) . '/lib/shared/AuthorizeNetResponse.php';
//require dirname(__FILE__) . '/lib/AuthorizeNetAIM.php';
//require dirname(__FILE__) . '/lib/AuthorizeNetARB.php';
//require dirname(__FILE__) . '/lib/AuthorizeNetDPM.php';
//require dirname(__FILE__) . '/lib/AuthorizeNetTD.php';
//require dirname(__FILE__) . '/lib/AuthorizeNetCP.php';

//if (class_exists("SoapClient")) {
//    
//    require dirname(__FILE__) . '/lib/AuthorizeNetSOAP.php';
//}

/**
 * Exception class for AuthorizeNet PHP SDK.
 *
 * @package AuthorizeNet
 */
class AuthorizeNetComponent extends Component {

    function validate_card($loginid = null, $trankey = null, $amount = null, $card_num = null, $exp_date = null) {
        $transaction = new AuthorizeNetAIM($loginid, $trankey);
        //pr($exp_date);die;
        $transaction->amount = $amount;
        $transaction->card_num = $card_num;
        $transaction->exp_date = $exp_date;
        //$transaction->cardholder_authentication_value = '778';
        return $transaction->authorizeAndCapture();
    }

}
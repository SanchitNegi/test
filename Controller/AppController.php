<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package		app.Controller
 * @link		http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {
            
             
            
            public $components = array('Session','Auth','Paginator');
            public $helpers = array('Common');
        public function beforeFilter() {
           
            $this->Auth->allow('fetchCoupon','items','fetchProduct','sizePrice','fetchToppingPrice','fetchCategoryInfo','cart','removeItem','addQuantity','orderDetails','cancelOffer','orderSave','success','status','guestOrdering','login','registration','store','checkEmail','forgetPassword','selectStore','paymentSection');
            header('Cache-Control: no-store, private, no-cache, must-revalidate'); //header for disable back button after logout
            $siteSettingData = ClassRegistry::init('MainSiteSetting')->getSiteSettings();// global values
            $this->Auth->fields = array('username' => 'email', 'password' => 'password');
            if($siteSettingData){
                $this->smtp_host = $siteSettingData['MainSiteSetting']['smtp_host']; //SITE VARIABLES
                $this->smtp_port = $siteSettingData['MainSiteSetting']['smtp_port'];
                $this->smtp_username = $siteSettingData['MainSiteSetting']['smtp_username'];
                $this->smtp_password = $siteSettingData['MainSiteSetting']['smtp_password'];
                $this->google_site_key = $siteSettingData['MainSiteSetting']['google_site_key'];
                $this->google_secret_key = $siteSettingData['MainSiteSetting']['google_secret_key'];

            }
           //echo $this->params['action'];die;
            if($this->params['action']=='store'){
                  $requestParam=explode('/',$this->params->url);                 
                  $store_name=trim($requestParam[0]); // Name of the store which we will change later with Saas 
                 if($store_name){
                      $this->loadModel('Store');
                       $store_result=$this->Store->store_info($store_name);
                       
                      $storeName=$store_result['Store']['store_name'];
                      $store_url=$store_result['Store']['store_url'];
                      //$this->set(compact('storeName'));
                      if(isset($store_result['Store']['service_fee'])){
                        $this->Session->write('service_fee',$store_result['Store']['service_fee']);
                      }
                       if(isset($store_result['Store']['delivery_fee'])){
                        $this->Session->write('delivery_fee',$store_result['Store']['delivery_fee']);
                      }
                      $this->Session->write('minprice',$store_result['Store']['minimum_order_price']);
                       $this->Session->write('storeName',$storeName);
                      $this->Session->write('store_url',$store_url);
                      $this->Session->write('store_id',$store_result['Store']['id']);
                      $this->Session->write('merchant_id',$store_result['Store']['merchant_id']);
                 
                  
                 }
                 
            }
            $store_id="";
            
            if($this->Session->check('store_id') && $this->params['action'] !='selectStore'){
                $store_id=$this->Session->read('store_id');
            }else{
                if($this->params['action'] !='selectStore'){
                    $this->redirect(array('controller' => 'users','action' => 'selectStore'));   
                }
            }
          
            if(($store_id) && ($this->params['controller']=='users')){  // Auth Conditions for front user
                $this->Auth->loginAction = array('controller' => 'users','action' => 'login');
                $this->Auth->logoutRedirect = array('controller' => 'users','action' => 'login');
                $this->Auth->logoutRedirect = array('controller' => 'users','action' => 'customerDashboard');
                $this->Auth->authenticate = array(
                'Form' => array(
                    'userModel' => 'User',
                    'fields' => array('username' =>'email','password' => 'password','store_id'),
                    'scope'=>array('User.store_id'=>$store_id,'User.is_active'=>1,'User.is_deleted'=>0)

                )
            );
            }
            elseif(($store_id) && ($this->params['controller']=='stores')){                
                $this->Auth->loginAction = array('controller' => 'stores','action' => 'login');
                $this->Auth->logoutRedirect = array('controller' => 'stores','action' => 'login');
                $this->Auth->logoutRedirect = array('controller' => 'stores','action' => 'dashboard');
                $this->Auth->authenticate = array(
                        'Form' => array(
                            'userModel' => 'User',
                            'fields' => array('username' =>'email','password' => 'password','store_id'),
                            'scope'=>array('User.store_id'=>$store_id,'User.is_active'=>1,'User.is_deleted'=>0)
        
                        )
                );                       
            }
        
   
        }
        
        
        
            /*---------------------------------------------
             Function name:checkAddress
             Description:To verify the address
            -----------------------------------------------*/
        
            function checkAddress($address=null,$state=null,$city=null,$zip=null){
                    $this->autoRender = false;
                    if(isset($_POST)){
                        $zipCode = ltrim($zip," ");
                        $stateName =$state;
                        $cityName = strtolower($city);
                        $cityName = ucwords($cityName);
                        $address=$address;
                        $dlocation = $address." ".$cityName." ".$stateName." ".$zipCode;
                        $adjuster_address2 = str_replace(' ','+',$dlocation);
                        $geocode=file_get_contents('http://maps.google.com/maps/api/geocode/json?address='.$adjuster_address2.'&sensor=false');
                        $output= json_decode($geocode);
                      
                        if($output->status=="ZERO_RESULTS" || $output->status !="OK"){
                            echo 2;die;// Bad Address
                        }else{
                             $latitude  = @$output->results[0]->geometry->location->lat;
                            $longitude = @$output->results[0]->geometry->location->lng;
                            $formated_address=@$output->results[0]->formatted_address;
                            if($latitude){
                                echo 1;die;// Good Address
                            }
                    }
            }
            
            }
            
            
            
            
            

            
            
     
    

}

<?php
App::uses('AppController', 'Controller');
class UsersController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses='User';
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
             parent::beforeFilter();
            
   }
   
   public function store(){
     $this->autoRender=false;
     $this->redirect(array('controller'=>'users','action'=>'login'));
     
   }
   
   /*------------------------------------------------
   Function name:login()
   Description:Registration  Form for the  End customer
   created:22/7/2015
  -----------------------------------------------------*/
   public function registration(){
      
      $this->layout="default_reg";
      if($this->request->is('post')){
            $this->User->set($this->request->data);
            if ($this->User->validates()) {
                  $recaptcha=$_POST['g-recaptcha-response'];
                  $google_url="https://www.google.com/recaptcha/api/siteverify";
                  $secret=$this->google_secret_key;
                  $ip=$_SERVER['REMOTE_ADDR'];
                  $url=$google_url."?secret=".$secret."&response=".$recaptcha."&remoteip=".$ip;
                  $res=$this->Common->getCurlData($url);
                   
                  $res= json_decode($res, true);
                  if($res['success']){
                  $storeId="";
                  $merchantId="";
                  $storeId= $this->Session->read('store_id'); // It will read from session when a customer will try to register on store
                  $merchantId= $this->Session->read('merchant_id');
                  $email=trim($this->request->data['User']['email']); //Here username is email
                  $this->request->data['User']['store_id']=$storeId;// Store Id 
                  $this->request->data['User']['merchant_id']=$merchantId;// Merchant Id
                  $roleId=$this->request->data['User']['role_id'];// Role Id of the user
                  $userName=trim($this->request->data['User']['email']); //Here username is email
                  $this->request->data['User']['username']=trim($userName);
                  $current_time= date("Y-m-d H:i:s");
                  $this->request->data['User']['dateOfjoin']=$current_time;
                  //echo $actualDbDate=date("Y-m-d",strtotime($this->request->data['User']['dateOfBirth']));die;  //Not working
                  $actualDbDate=$this->Dateform->formatDate($this->request->data['User']['dateOfBirth']);// calling formatDate function in Appcontroller to format the date (Y-m-d) format 
                  $this->request->data['User']['dateOfBirth']=$actualDbDate;
                  $result=$this->User->saveUserInfo($this->request->data);   // We are calling function written on Model to save data 
                  $this->loadModel('Store');
                  $storeEmail=$this->Store->fetchStoreDetail($storeId);
                  if($result==1){
                    
                     $this->loadModel('EmailTemplate');
                     if($roleId==4){
                         $storeId=$this->Session->read('store_id');
                         $merchantId=$this->Session->read('merchant_id');
                         $template_type=USER_REGISTRATION;
                     }
                     $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$storeId,$merchantId,$template_type);
                     
                     if($emailSuccess){
                           if($this->request->data['User']['lname']){
                              $fullName=$this->request->data['User']['fname']." ".$this->request->data['User']['lname'];
                           }else{
                              $fullName=$this->request->data['User']['fname'];

                           }
                              $emailData = $emailSuccess['EmailTemplate']['template_message'];
                              $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                              $emailData = str_replace('{USERNAME}',$userName, $emailData);
                              $emailData =str_replace('{PASSWORD}',trim($this->request->data['User']['password']), $emailData);
                              //$activationLink=HTTP_ROOT."users/login";
                               $activationLink=HTTP_ROOT.$storeEmail['Store']['store_url'];
                              $emailData = str_replace('{ACTIVE_LINK}', $activationLink, $emailData);
                              $subject = ucwords(str_replace('_', ' ', $emailSuccess['EmailTemplate']['template_subject']));
                              $this->Email->to = $email;
                              $this->Email->subject =$subject;
                              $this->Email->from = $storeEmail['Store']['email_id'];
                              $this->set('data', $emailData);
                              $this->Email->template ='template';
                              //echo $this->smtp_port;die;
                              $this->Email->smtpOptions = array(
                                 'port' => "$this->smtp_port",
                                 'timeout' => '100',
                                 'host' => "$this->smtp_host",
                                 'username' => "$this->smtp_username",
                                 'password' => "$this->smtp_password"
                              ); 
                              $this->Email->sendAs = 'html'; // because we like to send pretty mail
                             // $this->Email->delivery ='smtp';
                              $this->Email->send();
                     }
                   
                    
                     $this->Session->setFlash(__('You have been registered successfully, Please proceed with the login'));

                     $this->redirect(array('controller'=>'users','action'=>'login'));
                  }else{
                     $this->Session->setFlash(__('You have not been registered successfully, Please try again'));
                     //$this->redirect(array('controller'=>'users','action'=>'registration'));

                  }
            }else{
               
                  $this->Session->setFlash(__('Please re-enter your reCAPTCHA'));
                  //$this->redirect(array('controller'=>'users','action'=>'registration'));

            }
            }else {
                  $errors = $this->User->validationErrors;
            }
         
        
      }
    
    if($this->google_site_key){
       $googleSiteKey=$this->google_site_key;
       $this->set(compact('googleSiteKey'));
    }
   }
   
   
   
   /*------------------------------------------------
   Function name:login()
   Description:Registration  Form for the  End customer
   created:22/7/2015
  -----------------------------------------------------*/
     public function login($layout_type = null) {  
        $this->layout="default";
        $this->set('title','Store Login');
        
        /*******************Guest Ordering*************/

        $this->Session->delete('Cart');
        $this->Session->delete('cart');
        $this->Session->delete('Coupon');
        $this->Session->delete('Discount');
        $decrypt_storeId= $this->Session->read('store_id'); 
        $decrypt_merchantId= $this->Session->read('merchant_id');
        $encrypted_storeId=$this->Encryption->encode($decrypt_storeId);
        $encrypted_merchantId=$this->Encryption->encode($decrypt_merchantId);
        $avalibilty_status=$this->Common->checkStoreAvalibility($decrypt_storeId); 
        if($avalibilty_status==1){
            if($this->Session->check('Order')){
                $this->Session->delete('Order');
            }
        } else {
            $this->set(compact('avalibilty_status'));
        }
        $this->loadModel('Store');
        $current_date=date('Y-m-d');
        $date = new DateTime($current_date);
        $current_day=$date->format('l');
        $this->Store->bindModel(
         array(
           'hasMany'=>array(
               'StoreAvailability' =>array(
                'className' => 'StoreAvailability',
                'foreignKey' => 'store_id',
                'conditions' => array('StoreAvailability.day_name' =>$current_day,'StoreAvailability.is_deleted' =>0,'StoreAvailability.is_active' =>1,'is_closed'=>0),
                'fields'=>array('start_time','end_time')
            )         
           )
         )
        ); 
        $store_data=$this->Store->fetchStoreDetail($decrypt_storeId,$decrypt_merchantId); // call model function to fetch store details
        if($store_data){
            if(!empty($store_data['StoreAvailability'])){
                $start=$store_data['StoreAvailability'][0]['start_time'];
                $end=$store_data['StoreAvailability'][0]['end_time'];
                $time_ranges=$this->Common->getStoreTime($start,$end);// calling Common Component
            }
            $current_array=array();
            foreach($time_ranges as $time_key=>$time_val){
                $current_time=strtotime(date("H:i:s"));
                $time_key_str=strtotime($time_key);
                if($time_key_str > $current_time){
                    $current_array[$time_key]=$time_val;
                }
            }
        }
        $time_range = $current_array;
        if($store_data){
           $this->set(compact('time_range','store_data','encrypted_storeId','encrypted_merchantId','time_ranges'));
        }else{
           $this->set(compact('time_range','encrypted_storeId','encrypted_merchantId'));
        }
        
        /*******************************************************************/
            if ($this->request->is('post')) {
                  $storeId= $this->Session->read('store_id'); // It will read from session when a customer will try to register on store
                  $merchantId= $this->Session->read('merchant_id');
                  if($storeId){
                      $this->request->data['User']['store_id']=$storeId;
                  }
                 
                  $this->User->set($this->request->data);
                  if ($this->User->validates()) {
                        if($this->data['User']['remember']==1) {
                                  // Cookie is valid for 7 days
                                 $this->Cookie->write('Auth.email',$this->data['User']['email'], false, 604800);
                                 $this->Cookie->write('Auth.password', $this->data['User']['password'], false, 604800);
                                 $this->set('cookies','1');
                                 unset($this->request->data['User']['remember_me']);

                        }
                        else{
                                
                                 $this->Cookie->delete('Auth');
                                 $this->Cookie->delete('Auth');
                           }
                      
                        if ($this->Auth->login()) {
                              $roleId=AuthComponent::User('role_id'); // ROLE OF THE USER [5=>Customer]
                              $this->Session->write('login_date_time',date('Y-m-d H:i:s'));
                              
                              if($roleId==4){  // End Customer will redirect to his related dashboard
                                   
                                    $encrypted_storeId=$this->Encryption->encode(AuthComponent::User('store_id')); // Encrypted Store Id
                                    
                                    $encypted_merchantId=$this->Encryption->encode(AuthComponent::User('merchant_id'));// Encrypted Merchant Id
                                   // $this->Session->setFlash(__('Welcome to your account, You are logged In successfully.'));
                                    $this->redirect(array('controller'=>'users','action'=>'customerDashboard',$encrypted_storeId,$encypted_merchantId));
                              }else{
                                  $this->Session->setFlash(__('Invalid username or password, try again.'));
                                 $this->redirect(array('controller'=>'users','action'=>'logout'));

                              }
                        }else{
                        $this->Session->setFlash(__('Invalid username or password, try again'));
 
                        //$this->Session->setFlash("<div class='alert_error'>".LOGINNOTSUCCESSFULL."</div>");
                        }
                  }
            }else
            {		
                       // echo $this->Cookie->read('Auth.email');die;
                          //$storeId= $this->Session->read('store_id');
                          //if($storeId){
                          //  $store_result=$this->Store->store_info($store_name);
                          //}
                        $this->set('rem',$this->Cookie->read('Auth.email'));
                        if($this->Cookie->read('Auth.email')) {
                              $this->request->data['User']['email']=$this->Cookie->read('Auth.email');
                              $this->request->data['User']['password']=$this->Cookie->read('Auth.password');
                        }
            }
      }
        
   /*------------------------------------------------
   Function name:dashboard()
   Description:Registration  Form for the  End customer
   created:22/7/2015
  -----------------------------------------------------*/
        public function customerDashboard($encrypted_storeId,$encrypted_merchantId,$orderId=null){
         
              //echo $current_time=date("Y-m-d H:i:s");
              //echo date("D",strtotime($current_time));die;
              
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $avalibilty_status=$this->Common->checkStoreAvalibility($decrypt_storeId); // I will check the time avalibility of the store
               if($avalibilty_status==1){
                    if($this->Session->check('Order')){
                    
                          $this->Session->delete('Order');
                    }
                    
                    $this->set(compact('encrypted_storeId','encrypted_merchantId','orderId'));
                    
                 }else{
                 $this->set(compact('avalibilty_status','encrypted_storeId','encrypted_merchantId','orderId'));
               }
  
        }
   /*------------------------------------------------
   Function name:logout()
   Description:For logout of the user
   created:22/7/2015
  -----------------------------------------------------*/     
        
        public function logout() {
             //$this->Session->setFlash(__('You have been logged out successfully'));
             $this->Session->delete('Order');
            $this->Session->delete('Cart');
            $this->Session->delete('cart');
            return $this->redirect($this->Auth->logout());
        }
    /*------------------------------------------------
   Function name:checkEmail()
   Description:For logout of the user
   created:22/7/2015
  -----------------------------------------------------*/     
        
      public function checkEmail($roleId=null) {
        
             $this->autoRender=false;
             
             if($_GET){
              
                $emailEntered=$_GET['data']['User']['email'];
               $storeId="";
               $merchantId="";
                if($roleId==4){
                  $storeId=$this->Session->read('store_id');
                  $merchantId=$this->Session->read('merchant_id');
                  $emailStatus=$this->User->emailCheck($roleId,$storeId,$merchantId,$emailEntered);

                }
              echo json_encode($emailStatus);
            }
        }
        
        
      /*------------------------------------------------
      Function name:forgetPassword()
      Description:For forget password
      created:22/7/2015
     -----------------------------------------------------*/     
        
       public function forgetPassword(){
	    $this->layout='default_reg';
             $this->autorender=false;
            
	    if(!empty($this->data)){
              // print_r($this->data);die;
               $roleId="";
               $email=$this->request->data['User']['email'];
               $roleId=$this->request->data['User']['role_id'];
               $merchantId=$this->Session->read('merchant_id');
               if(!$merchantId){
                  $merchantId="";
               }
               $storeId=$this->Session->read('store_id');
               if(!$storeId){
                  $storeId='';
               }
              // echo $storeId;die;
              $userEmail=$this->User->checkForgetEmail($roleId,$storeId,$merchantId,$email); //Calling function on model for checking the email 
               
		if(!empty($userEmail)){

		  $randomCode =  $this->User->getRandomCode(8);

                  $this->loadModel('EmailTemplate');
                  if($roleId==4){
                     $template_type=FORGET_PASSWORD_CUTOMER;
                  }elseif($roleId==3){
                     $template_type=FORGET_PASSWORD_CUTOMER;
                  }
                   $this->loadModel('Store');
                  $storeEmail=$this->Store->fetchStoreDetail($storeId);
                  $emailTemplate=$this->EmailTemplate->storeTemplates($roleId,$storeId,$merchantId,$template_type);
               //print_r($emailTemplate);die;
                  if($emailTemplate){
                        if($userEmail['User']['lname']){
                              $fullName=$userEmail['User']['fname']." ".$userEmail['User']['lname'];
                        }else{
                              $fullName=$this->request->data['User']['fname'];

                        }
                        $userName=$userEmail['User']['email'];
                        $emailData = $emailTemplate['EmailTemplate']['template_message'];
                        $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                        $emailData = str_replace('{USERNAME}',$userName, $emailData);
                        $emailData =str_replace('{PASSWORD}',$randomCode, $emailData);
                       // $activationLink=HTTP_ROOT."users/login";
                     
                        $activationLink=HTTP_ROOT.$storeEmail['Store']['store_url'];
                        
                        $emailData = str_replace('{ACTIVE_LINK}', $activationLink, $emailData);
                        $subject = ucwords(str_replace('_', ' ', $emailTemplate['EmailTemplate']['template_subject']));
                        $this->Email->to = $email;
                        $this->Email->subject =$subject;
                        $this->Email->from = $storeEmail['Store']['email_id'];
                        $this->set('data', $emailData);
                        $this->Email->template ='template';
                        //echo $this->smtp_port;die;
                        $this->Email->smtpOptions = array(
                           'port' => "$this->smtp_port",
                           'timeout' => '30',
                           'host' => "$this->smtp_host",
                           'username' => "$this->smtp_username",
                           'password' => "$this->smtp_password"
                        );
                        //$this->Email->delivery = "smtp";
                        $this->Email->sendAs = 'html'; // because we like to send pretty mail
                        if($this->Email->send()){
                              $this->request->data['User']['id'] = $userEmail['User']['id'];
                              $this->request->data['User']['password'] = $randomCode;		    
                              $this->User->saveUserInfo($this->data['User']);		    
                              $this->Session->setFlash("<div class='alert_success'>".FORGETMAILSENT."</div>");
                              $this->redirect(array('controller' => 'Users', 'action' => 'login'));   
                        }		 
                     
                     }
		   
                    ////////////Dynamic SMTP//////////
                   
		     
		}else{
                        $this->Session->setFlash(__('Email address is not registered in our system, Please check again.'));
			//$this->redirect(array('controller' => 'Users', 'action' => 'forgetPassword'));   
		}
	    }
	}
        
        
        
      /*------------------------------------------------
      Function name:myProfile()
      Description:This section will manage the profile of the user for Customer 
      created:22/7/2015
     -----------------------------------------------------*/
      
      public function myProfile($encrypted_storeId,$encrypted_merchantId){
        
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $userResult=$this->User->currentUserInfo(AuthComponent::User('id'));
               $roleId=$userResult['User']['role_id'];
               $encryped_userId = $this->Encryption->encode($userResult['User']['id']);
               $this->set(compact('encrypted_storeId','encrypted_merchantId','encryped_userId'));
               $this->User->set($this->request->data);
               if(isset($this->request->data['User']['changepassword'])){
                   if(!($this->request->data['User']['changepassword'])){
                        $this->User->validator()->remove('password');
                        $this->User->validator()->remove('password_match');
                     }
               }
               if ($this->User->validates()) {
                        if($this->request->is('post')){
                             
                              $dbformatDate=$this->Dateform->formatDate($this->data['User']['dateOfBirth']);
                              $this->request->data['User']['dateOfBirth']=$dbformatDate;
                              if($this->request->data['User']['changepassword'] ==1){
                                    $oldPassword= AuthComponent::password($this->data['User']['oldpassword']);
                                    if($oldPassword!=$userResult['User']['password']){
                                          $this->Session->setFlash("<div class='alert_success'>Please Enter correct old password</div>");
                                          $this->redirect(array('controller' => 'Users', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                                    }
                              }
                              $this->User->id=AuthComponent::User('id');
                              if($this->User->saveUserInfo($this->request->data['User'])){
                                 $this->Session->setFlash(__('Profile has been updated successfully.'));
                                 $this->redirect(array('controller' => 'Users', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                              }else{
                                 $this->Session->setFlash(__('Profile not updated successfully.'));
                                 //$this->redirect(array('controller' => 'Users', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                              }
                        
                        }
               }
                $this->set(compact('roleId'));
               $this->request->data['User']=$userResult['User'];
               $this->request->data['User']['dateOfBirth']=$this->Dateform->us_format($userResult['User']['dateOfBirth']);
      }
      
      
   
      /*------------------------------------------------
      Function name:deliveryAddress()
      Description:This section will manage the delivery address portion
      created:27/7/2015
      -----------------------------------------------------*/
      
      public function deliveryAddress($encrypted_storeId=null,$encrypted_merchantId=null,$orderId=null){
             
               
             //print_r($_SESSION);die;
            
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
                  $this->loadModel('Store');
               $current_date=date('Y-m-d');
               $date = new DateTime($current_date);
               $current_day=$date->format('l');
               $this->Store->bindModel(
                array(
                  'hasMany'=>array(
                      'StoreAvailability' =>array(
                       'className' => 'StoreAvailability',
                       'foreignKey' => 'store_id',
                       'conditions' => array('StoreAvailability.day_name' =>$current_day,'StoreAvailability.is_deleted' =>0,'StoreAvailability.is_active' =>1,'is_closed'=>0),
                       'fields'=>array('start_time','end_time')
                   )         
                  )
                )
              ); 
               $store_data=$this->Store->fetchStoreDetail($decrypt_storeId,$decrypt_merchantId); // call model function to fetch store details
              //echo "<pre>";print_r($store_data);die;
               //$time_range="";
               if($store_data){
                  if(!empty($store_data['StoreAvailability'])){
                        $start=$store_data['StoreAvailability'][0]['start_time'];
                        $end=$store_data['StoreAvailability'][0]['end_time'];
                        $time_range=$this->Common->getStoreTime($start,$end);// calling Common Component
                  }
                
               }
               $this->loadModel('DeliveryAddress');
               $userId=AuthComponent::User('id'); // Customer Id
               $roleId=AuthComponent::User('role_id');
               $checkaddress=$this->DeliveryAddress->checkAddress($userId,$roleId,$decrypt_storeId,$decrypt_merchantId);// It will call the function in the model to check the address either exist or not 
               //print_r($checkaddress);die;
               if(!$checkaddress){
                 $checkaddress=array();
               }
               $this->set(compact('orderId','checkaddress','encrypted_storeId','encrypted_merchantId','time_range'));
      }
      
      
      
      /*------------------------------------------------
      Function name:addAddress()
      Description:This section will add the delivery address portion
      created:27/7/2015
      -----------------------------------------------------*/
      
      public function addAddress($encrypted_storeId=null,$encrypted_merchantId=null){
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $this->loadModel('DeliveryAddress');
               $userId=AuthComponent::User('id'); // Customer Id
               $roleId=AuthComponent::User('role_id');
               if($this->request->is('post')){
                
                  $zipCode =trim($this->request->data['DeliveryAddress']['zipcode']," ");
                  $stateName =trim($this->data['DeliveryAddress']['state']," ");
                  $cityName = strtolower($this->request->data['DeliveryAddress']['city']);
                  $cityName = trim(ucwords($cityName));
                  $address=trim(ucwords($cityName));
                  $dlocation = $address." ".$cityName." ".$stateName." ".$zipCode;
                  $adjuster_address2 = str_replace(' ','+',$dlocation);
                  $geocode=file_get_contents('http://maps.google.com/maps/api/geocode/json?address='.$adjuster_address2.'&sensor=false');
                  $output= json_decode($geocode);
                  $this->request->data['DeliveryAddress']['user_id']=AuthComponent::User('id');
                  $this->request->data['DeliveryAddress']['store_id']=$decrypt_storeId;
                  $this->request->data['DeliveryAddress']['merchant_id']=$decrypt_merchantId;
                  if($output->status=="ZERO_RESULTS" || $output->status !="OK"){
                      
                  }else{
                        $latitude  = @$output->results[0]->geometry->location->lat;
                        $longitude = @$output->results[0]->geometry->location->lng;
                        $this->request->data['DeliveryAddress']['latitude']=$latitude;
                        $this->request->data['DeliveryAddress']['longitude']=$longitude;
                       


                  }
                  //print_r($this->request->data);die;
                  $result_sucess=$this->DeliveryAddress->saveAddress($this->request->data);
                 
                  if($result_sucess){
                                 $this->Session->setFlash(__('Address has been saved successfully'));
                                 $this->redirect(array('controller'=>'users','action'=>'deliveryAddress',$encrypted_storeId,$encrypted_merchantId));
                   }else{
                      $this->Session->setFlash(__('Address not saved successfully'));
                     //$this->redirect(array('controller'=>'users','action'=>'addAddress',$encrypted_storeId,$encrypted_merchantId));
                   }
               }
              $this->set(compact('encrypted_storeId','encrypted_merchantId'));

      }
      
      
      /*------------------------------------------------
      Function name:checkusersadddress()
      Description:This section will verify the address
      created:27/7/2015
      -----------------------------------------------------*/
      
      public function checkusersadddress(){
              $this->autoRender=false;
              if($this->request->is('ajax')){
                   $result_address=$this->checkaddress($_POST['address'],$_POST['city'],$_POST['state'],$_POST['zip']);
               
              }

      }
      
        
      /*------------------------------------------------
      Function name:updateAddress()
      Description:This section will manage the delivery address portion
      created:27/7/2015
      -----------------------------------------------------*/
      
      public function updateAddress($encrypted_storeId=null,$encrypted_merchantId=null){
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $this->loadModel('DeliveryAddress');
               $userId=AuthComponent::User('id'); // Customer Id
               $roleId=AuthComponent::User('role_id');
               $resultAddress=$this->DeliveryAddress->checkAddress($userId,$roleId,$decrypt_storeId,$decrypt_merchantId);
               if($this->request->data){
                  $decrypt_storeId=$this->Encryption->decode($this->request->data['DeliveryAddress']['store_id']);
                  $decrypt_merchantId=$this->Encryption->decode($this->request->data['DeliveryAddress']['merchant_id']);
                  $encypted_storeId=$this->request->data['DeliveryAddress']['store_id'];
                  $encypted_merchantId=$this->request->data['DeliveryAddress']['merchant_id'];
                  
                  $zipCode =trim($this->request->data['DeliveryAddress']['zipcode']," ");
                  $stateName =trim($this->data['DeliveryAddress']['state']," ");
                  $cityName = strtolower($this->request->data['DeliveryAddress']['city']);
                  $cityName = trim(ucwords($cityName));
                  $address=trim(ucwords($cityName));
                  $dlocation = $address." ".$cityName." ".$stateName." ".$zipCode;
                  $adjuster_address2 = str_replace(' ','+',$dlocation);
                  $geocode=file_get_contents('http://maps.google.com/maps/api/geocode/json?address='.$adjuster_address2.'&sensor=false');
                  $output= json_decode($geocode);
                  $this->request->data['DeliveryAddress']['user_id']=AuthComponent::User('id');
                  $this->request->data['DeliveryAddress']['store_id']=$decrypt_storeId;
                  $this->request->data['DeliveryAddress']['merchant_id']=$decrypt_merchantId;
                  if($output->status=="ZERO_RESULTS" || $output->status !="OK"){
                      
                  }else{
                        $latitude  = @$output->results[0]->geometry->location->lat;
                        $longitude = @$output->results[0]->geometry->location->lng;
                        $this->request->data['DeliveryAddress']['latitude']=$latitude;
                        $this->request->data['DeliveryAddress']['longitude']=$longitude;
                  }
                  
                  $result_sucess=$this->DeliveryAddress->saveAddress($this->request->data);
                  
                  if($result_sucess){
                                 $this->Session->setFlash(__('Address has been updated successfully'));
                                 $this->redirect(array('controller'=>'users','action'=>'deliveryAddress',$encrypted_storeId,$encrypted_merchantId));
                   }else{
                      $this->Session->setFlash(__('Address not saved successfully'));
                     //$this->redirect(array('controller'=>'users','action'=>'addAddress',$encrypted_storeId,$encrypted_merchantId));
                   }
                  
               }
               if($resultAddress){
                  $this->request->data=$resultAddress;
               }
               
               $addressId=$resultAddress['DeliveryAddress']['id'];
              
              $this->set(compact('addressId','encrypted_storeId','encrypted_merchantId'));

      }
      
       /*------------------------------------------------
         Function name:orderType()
         Description:This section will look the order type
         created:27/7/2015
        -----------------------------------------------------*/
       public function orderType($orderId = null){
                if($this->Session->check('Auth.User.Order')){
                  $this->Session->delete('Auth.User.Order');
                 
                  
               }
               
               $encrypted_storeId=$this->Encryption->encode(AuthComponent::User('store_id'));
               $encrypted_merchantId=$this->Encryption->encode(AuthComponent::User('merchant_id'));
               if($this->data['Order']['type']['1']==3 && $this->data['Order']['type']['2']==0 && $this->data['Order']['type']['3']==0){ // Home Delivery
                        $order_type=$this->data['Order']['type']['1'];
                        
                        $this->Session->write('Order.order_type',$order_type); // Type of Delivery
                        
                        $this->redirect(array('controller'=>'users','action'=>'deliveryAddress',$encrypted_storeId,$encrypted_merchantId,$orderId));
         
                  }elseif($this->data['Order']['type']['1']==0 && $this->data['Order']['type']['2']==2 && $this->data['Order']['type']['3']==0){// Pick Up
                       $order_type=$this->data['Order']['type']['2'];
                       $this->Session->write('Order.order_type',$order_type); //Type of Delivery
                       $this->redirect(array('controller'=>'users','action'=>'pickUp',$encrypted_storeId,$encrypted_merchantId,$orderId));
         
                  }
                 elseif($this->data['Order']['type']['1']==0 && $this->data['Order']['type']['2']==0 && $this->data['Order']['type']['3']==1){// Dinein
                       $order_type=$this->data['Order']['type']['3'];
                       $this->Session->write('Order.order_type',$order_type);//Type of Delivery
                       
                       $this->redirect(array('controller'=>'users','action'=>'dineIn',$encrypted_storeId,$encrypted_merchantId));
         
                  }else{// Nothing Selcteed
                     
                           $this->Session->setFlash(__('Please Select order Type'));
         
                           $this->redirect(array('controller'=>'users','action'=>'customerDashboard',$encrypted_storeId,$encrypted_merchantId,$orderId));// 
         
                  }
       }
      /*------------------------------------------------
         Function name:ordercatCheck()
         Description: // It will check either the order is pre-order  or Now and write the value ito the session
         created:27/7/2015
      -----------------------------------------------------*/
         
         public function ordercatCheck($orderId=null){   // It will check either the order is pre-order  or Now
            $encrypted_storeId=$this->Encryption->encode(AuthComponent::User('store_id'));
            $encrypted_merchantId=$this->Encryption->encode(AuthComponent::User('merchant_id'));
            if($this->request->is('post')){
                $type=$this->Session->read('Order.order_type');
                if($type==2){ 
                     $order_cattype=$this->data['DeliveryAddress']['type'];
                     $this->Session->write('Order.is_preorder',$order_cattype);
                     //echo "<pre>"; print_r(AuthComponent::User());die;
                     if($this->data['DeliveryAddress']['type']==0){ //Now
                           if($this->Session->check('Auth.User.Order.delivery_address_id')){
                                 $this->Session->delete('Order.delivery_address_id');
                           }
                           if(AuthComponent::User('Order.store_pickup_time')){
                               $this->Session->delete('Order.store_pickup_time');
                           }
                           if(AuthComponent::User('Order.store_pickup_date')){
                              $this->Session->delete('Order.store_pickup_date');
                           }
                        
                       $this->Session->write('Order.store_pickup_time',$this->data['Store']['pickup_time_now']);
                        $this->Session->write('Order.pickup_store_id',$this->data['Store']['id']); //Store Id to fin details of store
                     
                        //echo "<pre>"; print_r(AuthComponent::User());die;
                        $this->redirect(array('controller'=>'products','action'=>'items',$encrypted_storeId,$encrypted_merchantId,$orderId));
            
                     }
                     elseif($this->data['DeliveryAddress']['type']==1){ // PreOrder
                              $order_cattype=$this->data['DeliveryAddress']['type'];
                              $this->Session->write('Order.is_preorder',$order_cattype);
                              
                              if($this->request->is('post')){
                                  $this->Session->write('Order.store_pickup_time',$this->request->data['Store']['pickup_time']); // Pick up time of Store
                                  $this->Session->write('Order.store_pickup_date',$this->request->data['Store']['pickup_date']);// Pick up date of
                              }
                        $this->redirect(array('controller'=>'products','action'=>'items',$encrypted_storeId,$encrypted_merchantId,$orderId));
    //echo "<pre>"; print_r(AuthComponent::User());die;

                  }
               }elseif($type==3){
                  
                      if($this->request->is('post')){
                                  $this->Session->write('Order.store_pickup_time',$this->request->data['Store']['pickup_time']); // Pick up time of Store
                                  $this->Session->write('Order.store_pickup_date',$this->request->data['Store']['pickup_date']);// Pick up date of
                     }
                    
                     $order_cattype=$this->data['DeliveryAddress']['type'];
                     $this->Session->write('Order.is_preorder',$order_cattype);
                     $this->Session->write('Order.delivery_address_id',$this->data['DeliveryAddress']['id']);
                     $this->redirect(array('controller'=>'products','action'=>'items',$encrypted_storeId,$encrypted_merchantId,$orderId));
            
               }
            }
         }
         
         
         
         
         
      /*------------------------------------------------
         Function name:pickUp()
         Description: // For Pick Up
         created:27/7/2015
      -----------------------------------------------------*/
         
         public function pickUp($encrypted_storeId=null,$encrypted_merchantId=null,$orderId=null){   // It will check either the order is pre-order  or Now
               
               //echo "<pre>";print_r($_SESSION);die;
               $this->layout="customer_dashboard";
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $this->loadModel('Store');
               $current_date=date('Y-m-d');
               $date = new DateTime($current_date);
               $current_day=$date->format('l');
               $this->Store->bindModel(
                array(
                  'hasMany'=>array(
                      'StoreAvailability' =>array(
                       'className' => 'StoreAvailability',
                       'foreignKey' => 'store_id',
                       'conditions' => array('StoreAvailability.day_name' =>$current_day,'StoreAvailability.is_deleted' =>0,'StoreAvailability.is_active' =>1,'is_closed'=>0),
                       'fields'=>array('start_time','end_time')
                   )         
                  )
                )
              ); 
               $store_data=$this->Store->fetchStoreDetail($decrypt_storeId,$decrypt_merchantId); // call model function to fetch store details
              // echo "<pre>";print_r($store_data);die;
               if($store_data){
                  if(!empty($store_data['StoreAvailability'])){
                        $start=$store_data['StoreAvailability'][0]['start_time'];
                        $end=$store_data['StoreAvailability'][0]['end_time'];
                        $time_ranges=$this->Common->getStoreTime($start,$end);// calling Common Component
                  }
               
                $current_array=array();
                foreach($time_ranges as $time_key=>$time_val){
                  $current_time=strtotime(date("H:i:s"));
                  $time_key_str=strtotime($time_key);
                  if($time_key_str > $current_time){
                      $current_array[$time_key]=$time_val;
                     
                  }
                  
                }
                
               }
               $time_range=$current_array;
               if($store_data){
                  $this->set(compact('orderId','time_range','store_data','encrypted_storeId','encrypted_merchantId','time_ranges'));
               }else{
                  $this->set(compact('orderId','encrypted_storeId','encrypted_merchantId'));
  
               }
            
             
         }
         
         
         
        /*------------------------------------------------
           Function name:dineIn()
           Description: // For dineIn Booking
           created:27/7/2015
        -----------------------------------------------------*/
        public function dineIn($encrypted_storeId=null,$encrypted_merchantId=null){   // It will check either the order is pre-order  or Now
            $this->layout="customer_dashboard";
            $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
            $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
            $this->loadModel('Store');
            $this->loadModel('StoreAvailability');
            $this->loadModel('Booking');
            $current_date=date('Y-m-d');
            $start_date = date('Y-m-d 0:0:0',strtotime($current_date));
            $end_date = date('Y-m-d 23:59:59',strtotime($current_date));
            $date = new DateTime($current_date);
            $current_day=$date->format('l');
            $store = $this->Store->fetchStoreDetail($decrypt_storeId,$decrypt_merchantId);
            $store_data = $this->StoreAvailability->getStoreInfoForDay($current_day,$decrypt_storeId);// get store detail
            $i=1;
            $number_person=array();
            for($i;$i<30;$i++){
               $number_person[$i]=$i;
            }
            $time_range = array();
            $store_booking = array();
            if($store_data){
                $start = $store_data['StoreAvailability']['start_time'];
                $start = date('h:i:s',time());
                $newTime = explode(':',$start);
                if($newTime[1] >= 30){
                    $start = date('h:30:00',time());
                } else {
                    $start = date('h:00:00',time());
                }
                $end = $store_data['StoreAvailability']['end_time'];
                $time_range = $this->Common->getStoreTime($start,$end);// calling Common Component
                $store_booking = $this->Booking->fetchStoreDetailBooked($decrypt_storeId,$start_date,$end_date);// get store detail
            }
            
            $this->set(compact('store_booking','number_person','time_range','store_data','store','encrypted_storeId','encrypted_merchantId'));   
            
            if($this->request->is('post')){
               $this->request->data['Booking']['store_id']=$decrypt_storeId;
               $this->request->data['Booking']['user_id']=AuthComponent::User('id');
               $reservationDate=$this->Dateform->formatDate($this->request->data['Booking']['start_date']);
               $reservationDateTime=$reservationDate." ".$this->request->data['Booking']['start_time'];
               $this->request->data['Booking']['reservation_date']=$reservationDateTime;
               $save_result=$this->Booking->saveBookingDetails($this->data); // call on model to save data
               if($save_result){
                    $template_type=CUSTOME_DINEIN_REQUEST;
                    $this->loadModel('EmailTemplate');
                    $roleId=AuthComponent::User('role_id'); 
                    $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$decrypt_storeId,$decrypt_merchantId,$template_type);
                 
                    if($emailSuccess){
                        $fullName="Admin";
                        $number_person=$this->data['Booking']['number_person'];//no of person
                        $start_date=$this->data['Booking']['start_date'];
                        $start_time=date('H:i a',strtotime($this->data['Booking']['start_time']));
                        $customer_name=AuthComponent::User('fname')." ".AuthComponent::User('lname');
                        if($this->data['Booking']['special_request']){
                            $special_request=$this->data['Booking']['special_request'];
                        }else{
                            $special_request="N/A";

                        }
                        $storeEmail= trim($store['Store']['email_id']);
                        $customerEmail=trim(AuthComponent::User('email'));
                        $emailData = $emailSuccess['EmailTemplate']['template_message'];
                        $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                        $emailData = str_replace('{BOOKING_DATE}',$start_date, $emailData);
                        $emailData =str_replace('{BOOKING_TIME}',$start_time, $emailData);
                        $emailData =str_replace('{NO_PERSON}',$number_person, $emailData);
                        $emailData =str_replace('{SPECIAL_REQUEST}',$special_request, $emailData);
                        $emailData =str_replace('{CUSTOMER_NAME}',$customer_name,$emailData);
                        $subject = ucwords(str_replace('_', ' ', $emailSuccess['EmailTemplate']['template_subject']));
                        $this->Email->to =$storeEmail;
                        $this->Email->subject =$subject;
                        $this->Email->from =$customerEmail;
                        $this->set('data', $emailData);
                        $this->Email->template ='template';
                        $this->Email->smtpOptions = array(
                           'port' => "$this->smtp_port",
                           'timeout' => '30',
                           'host' => "$this->smtp_host",
                           'username' => "$this->smtp_username",
                           'password' => "$this->smtp_password"
                        ); 
                        $this->Email->sendAs = 'html'; // because we like to send pretty mail
                       // $this->Email->delivery = 'smtp';
                        $this->Email->send();
                    }
                    $this->Session->setFlash(__('Booking Request submitted successfully'));
                    $this->redirect(array('controller'=>'users','action'=>'dineIn',$encrypted_storeId,$encrypted_merchantId));// 
                }else{
                    $this->Session->setFlash(__('Booking Request not submitted successfully'));
                    $this->redirect(array('controller'=>'users','action'=>'dineIn',$encrypted_storeId,$encrypted_merchantId));// 
               }
            }
        }
      
        /*------------------------------------------------
         Function name: getStoreTime()
         Description: User for getting time for different date selected in dine in
         created: 12/8/2015
        -----------------------------------------------------*/
        public function getStoreTime(){   // It will check either the order is pre-order  or Now
            $this->layout= 'ajax';
            if($this->request->is('ajax')){
                $storeId = $this->Encryption->decode($_POST['storeId']);
                $merchantId = $this->Encryption->decode($_POST['merchantId']);
                $this->loadModel('StoreAvailability');
                $this->loadModel('Booking');
                $date_shuffle = explode("-", $_POST['date']);
                $new_date = $date_shuffle[2].'-'.$date_shuffle[0].'-'.$date_shuffle[1];
                $selected_day = date('l',strtotime($new_date));
                $todayDate = date('m-d-Y');
                $start_date = date('Y-m-d 0:0:0',strtotime($new_date));
                $end_date = date('Y-m-d 23:59:59',strtotime($new_date));
                $store_data = $this->StoreAvailability->getStoreInfoForDay($selected_day,$storeId);// get store detail
                if($store_data){
                    $start = $store_data['StoreAvailability']['start_time'];
                    if($_POST['date'] == $todayDate){
                        $start = date('h:i:s',time());
                        $newTime = explode(':',$start);
                        if($newTime[1] >= 30){
                            $start = date('h:30:00',time());
                        } else {
                            $start = date('h:00:00',time());
                        }
                    }
                    $end = $store_data['StoreAvailability']['end_time'];
                    $time_range = $this->Common->getStoreTime($start,$end);// calling Common Component
                    $store_booking = $this->Booking->fetchStoreDetailBooked($storeId,$start_date,$end_date);// get store detail
                } else {
                    $time_range = array();
                    $store_booking = array();
                }
                $this->set(compact('time_range','store_booking'));
            }
            
        }
            
        public function selectStore(){
            $this->layout=false;
        }
        
         /*------------------------------------------------
         Function name: guestOrdering()
         Description: User for guest order
         created: 19/8/2015
        -----------------------------------------------------*/
        
        public function guestOrdering(){
            if($this->request->data){
                pr($this->request->data);
                $this->loadModel('DeliveryAddress');
                $encrypted_storeId=$this->Encryption->encode($this->Session->read('store_id'));
                $encrypted_merchantId=$this->Encryption->encode($this->Session->read('merchant_id'));     
                $order_type=$this->data['Order']['type'];
                $this->Session->write('Order.order_type',$order_type); 
                if($order_type == 2){ 
                    $data['DeliveryAddress']['user_id'] = 0;
                    $data['DeliveryAddress']['store_id'] = $this->Session->read('store_id');
                    $data['DeliveryAddress']['merchant_id'] = $this->Session->read('merchant_id');
                    $data['DeliveryAddress']['name_on_bell'] = $this->request->data['PickUpAddress']['name_on_bell'];
                    $data['DeliveryAddress']['phone'] = $this->request->data['PickUpAddress']['phone'];
                    $data['DeliveryAddress']['email'] = $this->request->data['PickUpAddress']['email'];
                    $this->DeliveryAddress->saveAddress($data);
                    $address_id = $this->DeliveryAddress->getLastInsertId();
                    $order_cattype=$this->data['PickUp']['type'];
                    $this->Session->write('Order.is_preorder',$order_cattype);
                    if($this->data['PickUp']['type']==0){    
                        $this->Session->delete('Order.store_pickup_date');
                        $this->Session->write('Order.delivery_address_id',$address_id);
                        $this->Session->write('Order.store_pickup_time',$this->data['PickUp']['pickup_time_now']);
                        $this->Session->write('Order.pickup_store_id',$this->Session->read('store_id')); 
                    }
                    elseif($this->data['PickUp']['type']==1){
                        $order_cattype = $this->data['PickUp']['type'];
                        $this->Session->write('Order.delivery_address_id',$address_id);
                        $this->Session->write('Order.is_preorder',$order_cattype);  
                        $this->Session->write('Order.store_pickup_time',$this->request->data['PickUp']['pickup_time']); 
                        $this->Session->write('Order.store_pickup_date',$this->request->data['PickUp']['pickup_date']);
                    }
                }elseif($order_type == 3){
                    $data['DeliveryAddress']['user_id'] = 0;
                    $data['DeliveryAddress']['store_id'] = $this->Session->read('store_id');
                    $data['DeliveryAddress']['merchant_id'] = $this->Session->read('merchant_id');
                    $data['DeliveryAddress']['name_on_bell'] = $this->request->data['DeliveryAddress']['name_on_bell'];
                    $data['DeliveryAddress']['phone'] = $this->request->data['DeliveryAddress']['phone'];
                    $data['DeliveryAddress']['email'] = $this->request->data['DeliveryAddress']['email'];
                    $data['DeliveryAddress']['address'] = $this->request->data['DeliveryAddress']['address'];
                    $data['DeliveryAddress']['city'] = $this->request->data['DeliveryAddress']['city'];
                    $data['DeliveryAddress']['state'] = $this->request->data['DeliveryAddress']['state'];
                    $data['DeliveryAddress']['zipcode'] = $this->request->data['DeliveryAddress']['zipcode'];
                    $this->DeliveryAddress->saveAddress($data);
                    $address_id = $this->DeliveryAddress->getLastInsertId();
                    $this->Session->write('Order.store_pickup_time',$this->request->data['Delivery']['pickup_time']); // Pick up time of Store
                    $this->Session->write('Order.store_pickup_date',$this->request->data['Delivery']['pickup_date']);// Pick up date of
                    
                    $order_cattype = $this->data['Delivery']['type'];
                    $this->Session->write('Order.is_preorder',$order_cattype);
                    $this->Session->write('Order.delivery_address_id',$address_id);
                }
                $this->redirect(array('controller'=>'products','action'=>'items',$encrypted_storeId,$encrypted_merchantId));
            }   
        }
}
?>
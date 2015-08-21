<?php

App::uses('AppController', 'Controller');


class StoresController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Paginator','Common','Dateform');
   public $helper = array('Encryption','Paginator','Form','DateformHelper','Common');
   public $uses=array('User','StoreGallery','Store','StoreAvailability','StoreHoliday','Category','Tab','Permission');
   public function beforeFilter() {            
      parent::beforeFilter();           
   }
   
   public function store(){
     $this->autoRender=false;
     $this->redirect(array('controller'=>'stores','action'=>'login'));    
   }
   
   /*------------------------------------------------
   Function name:index()
   Description:redirect user to Admin dasboard
   created:22/7/2015
  -----------------------------------------------------*/
   
   public function index(){
      $this->autoRender=false;
      $this->redirect(array('controller'=>'stores','action'=>'dashboard'));    
   }   
   
   /*------------------------------------------------
   Function name:login()
   Description:Registration  Form for the  End customer
   created:22/7/2015
  -----------------------------------------------------*/
   public function login($layout_type = null) {  
            $this->layout="store_login";
             $this->set('title', 'Sign in');
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
                              //$this->Session->setFlash("<div class='alert_success'>".LOGINSUCCESSFULL."</div>");
                              if($roleId==3){  // Store admin will redirect to his related dashboard
                                 $this->redirect(array('controller'=>'stores','action'=>'dashboard'));
                              }else{
                                 $this->redirect(array('controller'=>'stores','action'=>'logout'));

                              }
                        }else{                          
                        $this->Session->setFlash(__("Invalid email or password, try again"));
                        }
                  } 
            }else
            {               
                  $UserId=AuthComponent::User('id');
                  if($UserId){
                     $this->redirect(array('controller' => 'Stores', 'action' => 'logout')); 
                  }     
                       
                  $this->set('rem',$this->Cookie->read('Auth.email'));
                  if($this->Cookie->read('Auth.email')) {
                        $this->request->data['User']['email']=$this->Cookie->read('Auth.email');
                        $this->request->data['User']['password']=$this->Cookie->read('Auth.password');
                  }
            }
      }
      
      /*------------------------------------------------
      7Function name:dashboard()
      Description:Dash Board of Store Admin
      created:27/7/2015
     -----------------------------------------------------*/
        public function dashboard(){             
            $this->layout="admin_dashboard";
            $roleId=AuthComponent::User('role_id'); // ROLE OF THE USER [4=>Customer]
            if($roleId!=3){  // Store admin will redirect to his related dashboard
               $this->redirect(array('controller'=>'stores','action'=>'logout'));
            }
        }
        
      /*------------------------------------------------
      Function name:logout()
      Description:For logout of the user
      created:27/7/2015
      -----------------------------------------------------*/     
        
        public function logout() {            
            return $this->redirect($this->Auth->logout());
        }
        
      /*------------------------------------------------
      Function name:dashboard()
      Description:Dash Board of Store Admin
      created:27/7/2015
     -----------------------------------------------------*/
        public function manageStaff($EncrypteduserID=null){
              if(!$this->Common->checkPermissionByaction($this->params['controller'],$this->params['action'])){
                  $this->Session->setFlash(__("Permission Denied"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
                }  
              $this->layout="admin_dashboard";              
              $userResult=$this->User->currentUserInfo(AuthComponent::User('id'));
              $roleId=$userResult['User']['role_id'];              
              $storeId=$this->Session->read('store_id');
              $this->set(compact('roleId'));                          
              $this->User->set($this->request->data);
              if($EncrypteduserID){
                  $userID=$this->Encryption->decode($EncrypteduserID);
                  $this->Tab->bindModel(
                     array(
                       'hasMany'=>array(
                           'Permission' =>array(
                            'className' => 'Permission',
                            'foreignKey' => 'tab_id',
                            'conditions' => array('Permission.is_deleted' =>0,'Permission.is_active' =>1,'Permission.user_id'=>$userID),
                            'fields'=>array('id','tab_id')
                        )         
                       )
                     ),false
                   );
              }
              $this->loadModel('Tab');
              $Tabs=$this->Tab->getTabs();
              $this->set(compact('Tabs')); 
            if ($this->User->validates()) {
               if($this->request->is('post')){//pr($this->request->data);die;
                  if($this->request->data['User']['id']){
                     $userdata['User']=$this->request->data['User'];
                     if($this->User->saveUserInfo($userdata)){
                           $this->permission($this->request->data['User']['id'],$this->request->data['Permission']);
                           $this->Session->setFlash(__("Staff member details has been updated successfully"));
                           $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));   
                           
                     }else{
                           $this->Session->setFlash(__("Some problem occured"));
                           $this->redirect(array('controller' => 'Stores', 'action' => 'manageStaff'));   
                     }
                  }elseif($this->User->storeemailExists($this->request->data['User']['email'],$roleId,$storeId) && $this->request->data['User']['id']==''){
                     $this->request->data['User']['store_id']=$storeId;
                     $userdata['User']=$this->request->data['User'];
                     if($this->User->saveUserInfo($userdata)){
                        $userid=$this->User->getLastInsertId();
                        $this->permission($userid,$this->request->data['Permission']);
                        $this->Session->setFlash(__("Staff member has been added successfully"));
                        $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));   
                           
                     }else{
                        $this->Session->setFlash(__("Some problem occured"));
                        $this->redirect(array('controller' => 'Stores', 'action' => 'manageStaff'));   
                     }
                  }else{
                     $this->Session->setFlash(__("Email already exists"));
                     //$this->redirect(array('controller' => 'Stores', 'action' => 'manageStaff'));  
                  }                   
              }elseif($EncrypteduserID){
                  $userID=$this->Encryption->decode($EncrypteduserID);
                  $this->request->data=$this->User->currentUserInfo($userID);
              }
            }
        }
        
        
      /*------------------------------------------------
      Function name:staffList()
      Description:Display Staff List of Particular store
      created:27/7/2015
     -----------------------------------------------------*/  
      public function staffList(){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageStaff")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->layout="admin_dashboard";       
            $storeID=$this->Session->read('store_id');
            $value = "";
            $criteria = "User.store_id =$storeID AND User.is_deleted=0 AND User.role_id=3";
            if (!empty($this->params)) {
                if (!empty($this->params->query['keyword'])) {
                    $value = trim($this->params->query['keyword']);
                }
                if ($value != "") {
                    $criteria .= " AND (User.fname LIKE '%" . $value . "%' OR User.lname LIKE '%" . $value . "%' OR User.email LIKE '%" . $value . "%')";
                }
            }
            
            $this->paginate= array('conditions'=>array($criteria),'order'=>array('User.created'=>'DESC'));
            $userdetail=$this->paginate('User');
            $this->set('list',$userdetail);
            $this->set('keyword', $value);
      }
      
      /*------------------------------------------------
      Function name:deleteStaff()
      Description:Delete users
      created:27/7/2015
     -----------------------------------------------------*/  
      public function deleteStaff($EncrypteduserID=null){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageStaff")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['User']['store_id']=$this->Session->read('store_id');
            $data['User']['id']=$this->Encryption->decode($EncrypteduserID);
            $data['User']['is_deleted']=1;
            if($this->User->saveUserInfo($data)){
               $this->Session->setFlash(__("User deleted"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));
            }
      }
      
      
      /*------------------------------------------------
      Function name:manageStoreSliderImages()
      Description:Manage Images for Somepage slider
      created:27/7/2015
     -----------------------------------------------------*/
        public function manageSliderPhotos(){
         if(!$this->Common->checkPermissionByaction($this->params['controller'],$this->params['action'])){
            $this->Session->setFlash(__("Permission Denied"));
            $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
              $this->layout="admin_dashboard";       
              $errormsg="";
              $merchantId= $this->Session->read('merchant_id');
              $storeId= $this->Session->read('store_id');
              $this->set('merchantId',$merchantId);
              $this->set('storeId',$storeId);
              if($this->request->data){              
                     if($this->request->data["StoreGallery"]['image']['name']!=""){
                        $ImageStatus="";
                        $arr=pathinfo($this->request->data["StoreGallery"]['image']['name']);
                        //$arr = explode(".",$_FILES["StoreGallery"]['name']);       
                        $fileextension= $arr['extension'];                                 
                        if(trim((strtolower($fileextension)!="jpg")) && trim((strtolower($fileextension)!="gif")) && trim((strtolower($fileextension)!="jpeg")) && trim((strtolower($fileextension)!="png")))
                        {
                            $errormsg=$errormsg."Only jpg,gif,png type images are allowed<br />";
                            $ImageStatus="false"; 
                        }
                        $maxsize=409600;             
                        $actualSize=$this->request->data["StoreGallery"]['image']['size'];           
                        if(($actualSize > $maxsize) || $this->request->data["StoreGallery"]['image']['error']=="1"){
                            $errormsg=$errormsg."The image you are trying to upload is too large. Please limit the file size to 400kb.";
                            $ImageStatus="false";
                        }
                         $target_dir = WWW_ROOT."img/sliderImages/";                        
                        $uniqueImageName = $arr['filename'].'_'.$storeId.'_.'.$arr['extension'];                     
                        $target_file = $target_dir.$uniqueImageName;
                        if($errormsg==""){
                           if (move_uploaded_file($this->request->data["StoreGallery"]['image']['tmp_name'], $target_file)) {
                                 $data['image']=$uniqueImageName;
                                 $data['store_id']=$storeId;
                                 $data['merchant_id']=$merchantId;
                                 $data['description']=$this->request->data["StoreGallery"]["description"];
                                 if($this->StoreGallery->saveStoreSliderImage($data)){
                                    $this->Session->setFlash(__("File successfully Uploaded"));
                                    $this->redirect(array('controller' => 'Stores', 'action' => 'manageSliderPhotos'));
                                 }
                           }else{
                                 $this->Session->setFlash(__("Some Problem occured"));
                                 $this->redirect(array('controller' => 'Stores', 'action' => 'manageSliderPhotos'));
                           } 
                        }else{
                           $this->Session->setFlash(__(".$errormsg."));
                           $this->redirect(array('controller' => 'Stores', 'action' => 'manageSliderPhotos'));
                        }
                    }
              }              
               $this->set('sliderImages',$this->StoreGallery->getStoreSliderImages($storeId,$merchantId));
        }
        
      /*------------------------------------------------
      Function name:manageStoreSliderImages()
      Description:Manage Images for Somepage slider
      created:27/7/2015
      -----------------------------------------------------*/
      public function deleteSliderPhoto($EncryptedImageID=null){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageSliderPhotos")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->layout="admin_dashboard";       
            $imageID=$this->Encryption->decode($EncryptedImageID);
            if($imageID){
               $merchantId= $this->Session->read('merchant_id');
               $storeId= $this->Session->read('store_id');
               $this->set('merchantId',$merchantId);
               $this->set('storeId',$storeId);
               $data['id']=$imageID;
               $data['is_deleted']=1;
               if($this->StoreGallery->saveStoreSliderImage($data)){
                  $this->Session->setFlash(__("Slider Photo successfully Deleted"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'manageSliderPhotos'));
               }else{
                  $this->Session->setFlash(__("Some Problem occured"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'manageSliderPhotos'));
               }
            }
      }
      
      
      /*------------------------------------------------
      Function name:configuration()
      Description:Manage Images for Somepage slider
      created:27/7/2015
      -----------------------------------------------------*/
      public function configuration(){          
         if(!$this->Common->checkPermissionByaction($this->params['controller'],$this->params['action'])){
            $this->Session->setFlash(__("Permission Denied"));
            $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
         
            $this->layout="admin_dashboard";       
            $merchantId= $this->Session->read('merchant_id');
            $storeId= $this->Session->read('store_id');
            $this->set('userid',AuthComponent::User('id'));
            $this->set('roleid',AuthComponent::User('role_id'));
            $this->set('storeId',$storeId);
            if($this->request->data){
               if(!isset($this->request->data['Store']['is_booking_open'])){
                  $this->request->data['Store']['is_booking_open']=0;
               }
               //echo "<pre>"; print_r($this->request->data); exit;
               $latitude =""; $longitude ="";
               if(trim($this->request->data['Store']['address']) && trim($this->request->data['Store']['city']) && trim($this->request->data['Store']['state']) && trim($this->request->data['Store']['zipcode'])){
                  
                        $dlocation = trim($this->request->data['Store']['address'])." ".trim($this->request->data['Store']['city'])." ".trim($this->request->data['Store']['state'])." ".trim($this->request->data['Store']['zipcode']);
                        $address2 = str_replace(' ','+',$dlocation);
                        $geocode=file_get_contents('http://maps.google.com/maps/api/geocode/json?address='.$address2.'&sensor=false');
                        $output= json_decode($geocode);

                        
                        if($output->status=="ZERO_RESULTS" || $output->status !="OK"){
                           
                        }else{
                            $latitude  = @$output->results[0]->geometry->location->lat;
                            $longitude = @$output->results[0]->geometry->location->lng;

                        }
               }
              
               //Background Image Upload               
               if($this->data['Store']['back_image']['error']==0){
                     $response=$this->Common->uploadMenuItemImages($this->data['Store']['back_image'],'/storeBackground-Image/',$storeId);
               }elseif($this->data['Store']['back_image']['error']==4){
                     $response['status']=true;
                     $response['imagename']='';
               }
               
               if(!$response['status']){
                  $this->Session->setFlash(__($response['errmsg']));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'configuration'));
               }else{
                  //Item Data
                  if($response['imagename']){
                    $this->request->data['Store']['background_image']=$response['imagename'];
                  }
               }
               
               //Background Image Upload
               
               if($latitude && $longitude){
                  $this->request->data['Store']['latitude'] = $latitude;
                  $this->request->data['Store']['logitude'] = $longitude;
               }
               
               if($this->Store->saveStoreInfo($this->request->data['Store'])){
                  $this->Session->setFlash(__("Store Configuration details successfully Updated"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'configuration'));
               }else{
                  $this->Session->setFlash(__("Some problem occured"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'configuration'));
               }
            }
            $storeInfo=$this->Store->fetchStoreDetail($storeId,$merchantId);
            if(!empty($storeInfo)){
               $this->request->data['Store']=$storeInfo['Store'];
            }
      }
      
      
      
      
      /*------------------------------------------------
      Function name:manageTimings()
      Description:Manage Store Open and close Timings
      created:27/7/2015
      -----------------------------------------------------*/
      public function manageTimings(){
         if(!$this->Common->checkPermissionByaction($this->params['controller'],$this->params['action'])){
            $this->Session->setFlash(__("Permission Denied"));
            $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
         
          $this->layout="admin_dashboard";       
            $merchantId= $this->Session->read('merchant_id');
            $storeId= $this->Session->read('store_id');
            $this->set('userid',AuthComponent::User('id'));
            $this->set('roleid',AuthComponent::User('role_id'));
            $this->set('storeId',$storeId);
            if($this->request->data){               
               $this->request->data['Store']['id']=$storeId;               
               if($this->Store->saveStoreInfo($this->request->data['Store'])){
                  $this->Session->setFlash(__("Store Timings successfully Updated"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));
               }else{
                  $this->Session->setFlash(__("Some problem occurred"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));
               }
            }
            $storeInfo=$this->Store->fetchStoreDetail($storeId,$merchantId);
            $start="00:30";
            $end="24:00";
            $timeRange=$this->Common->getStoreTime($start,$end);            
            $this->set('timeOptions',$timeRange);
            $this->request->data['Store']=$storeInfo['Store'];
            $holidayInfo=$this->StoreHoliday->getStoreHolidayInfo($storeId);
            $this->set('holidayInfo',$holidayInfo);
            $availabilityInfo=$this->StoreAvailability->getStoreAvailabilityDetails($storeId);
            $this->set('availabilityInfo',$availabilityInfo);
            
            $daysarr=array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
            $this->set('daysarr',$daysarr);
            
      }
      
      
      /*------------------------------------------------
      Function name:addClosedDate()
      Description:delete closed date from list
      created:29/7/2015
      -----------------------------------------------------*/
      public function addClosedDate(){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageTimings")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->autoRender=false;            
            if($this->request->data){
               $data['store_id']= $this->Session->read('store_id');
               $holidayDate=$this->Dateform->formatDate($this->request->data['StoreHoliday']['holiday_date']);
               $data['holiday_date']=$holidayDate;
               $data['description']=$this->request->data['StoreHoliday']['description'];
               if($this->StoreHoliday->storeHolidayNotExists($holidayDate)){
                  if($this->StoreHoliday->saveStoreHolidayInfo($data)){
                        $this->Session->setFlash(__("Closed Holiday Successfully added"));
                        $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));   
                  }else{
                        $this->Session->setFlash(__("Some Problem occured"));
                        $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));   
                  }
               }else{
                        $this->Session->setFlash(__("Closed Date already exists"));
                        $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings')); 
               }
            }
      }
      
      
      
      /*------------------------------------------------
      Function name:deleteHoliday()
      Description:delete closed date from list
      created:29/7/2015
      -----------------------------------------------------*/
      public function deleteHoliday($EncryptedHolidayID=null){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageTimings")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->autoRender=false;
            $HolidayID=$this->Encryption->decode($EncryptedHolidayID);
            if($HolidayID){
               $data['id']=$HolidayID;
               $data['is_deleted']=$HolidayID;
               if($this->StoreHoliday->saveStoreHolidayInfo($data)){
                     $this->Session->setFlash(__("Closed Holiday Successfully deleted"));
                     $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));   
               }else{
                     $this->Session->setFlash(__("Some Problem occured"));
                     $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings'));   
               }
            }
      }
      
      /*------------------------------------------------
      Function name:updatestoreAvailability()
      Description:Update store timing basis of days
      created:29/7/2015
      -----------------------------------------------------*/
      public function updatestoreAvailability(){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageTimings")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            }
            $this->autoRender=false;
            $storeId=$this->Session->read('store_id');
            if($this->request->data){               
               foreach($this->request->data['StoreAvailability'] as $key => $value){
                     $value['store_id']=$storeId;
                     if(!isset($value['id'])){
                        $this->StoreAvailability->create();
                     }
                     $this->StoreAvailability->saveStoreAvailabilityInfo($value);
               }               
            }
            $this->Session->setFlash(__("Weekly Timings Successfully Updated"));
            $this->redirect(array('controller' => 'Stores', 'action' => 'manageTimings')); 
      }
      
      /*------------------------------------------------
      Function name:forgetPassword()
      Description:For forget password
      created:22/7/2015
     -----------------------------------------------------*/  
      public function forgetPassword(){
	    $this->layout="store_login";       
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
               
              $userEmail=$this->User->checkForgetEmail($roleId,$storeId,$merchantId,$email); //Calling function on model for checking the email 
                //print_r($userEmail);die;
		if(!empty($userEmail)){

		  $randomCode =  $this->User->getRandomCode(8);

                  $this->loadModel('EmailTemplate');
                  if($roleId==4){
                     $template_type=FORGET_PASSWORD_CUTOMER;
                  }elseif($roleId==3){
                     $template_type=FORGET_PASSWORD_CUTOMER;
                  }
                  
                  $emailTemplate=$this->EmailTemplate->storeTemplates($roleId,$storeId,$merchantId,$template_type);
                 // print_r($emailTemplate);die;
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
                        $activationLink=HTTP_ROOT.$this->Session->read('storeName').'/admin';
                        $emailData = str_replace('{ACTIVE_LINK}', $activationLink, $emailData);
                        $subject = ucwords(str_replace('_', ' ', $emailTemplate['EmailTemplate']['template_subject']));
                        $this->Email->to = $email;
                        $this->Email->subject =$subject;
                        $this->Email->from = ADMIN_EMAIL;
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
                              $this->Session->setFlash(__("Please check your registered email address to get new password"));
                              $this->redirect(array('controller' => 'Stores', 'action' => 'login'));   
                        }		 
                     
                     }
		   
                    ////////////Dynamic SMTP//////////
                   
		     
		}else{
		 	$this->Session->setFlash("<div class='alert_error'>".WRONGEMAIL."</div>");
			$this->redirect(array('controller' => 'Stores', 'action' => 'forgetPassword'));   
		}
	    }
	}
        
        
        /*------------------------------------------------
      Function name:myProfile()
      Description:This section will manage the profile of the user for Store Admin 
      created:22/7/2015
     -----------------------------------------------------*/
      
      public function myProfile($encrypted_storeId=null,$encrypted_merchantId=null){
               if(!$this->Common->checkPermissionByaction($this->params['controller'],null)){
                  $this->Session->setFlash(__("Permission Denied"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
               }
               $this->layout="admin_dashboard";       
               $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
               $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
               $this->set(compact('encrypted_storeId','encrypted_merchantId'));
               $userResult=$this->User->currentUserInfo(AuthComponent::User('id'));
               $roleId=$userResult['User']['role_id'];
               $this->User->set($this->request->data);
               if(isset($this->request->data['User']['changepassword'])){
                   if(!($this->request->data['User']['changepassword'])){
                        $this->User->validator()->remove('password');
                        $this->User->validator()->remove('password_match');
                     }
               }
               if ($this->User->validates()) {
                        if($this->request->is('post')){
                             
                              //$dbformatDate=$this->Dateform->formatDate($this->data['User']['dateOfBirth']);
                              //$this->request->data['User']['dateOfBirth']=$dbformatDate;
                              if($this->request->data['User']['changepassword'] ==1){
                                    $oldPassword= AuthComponent::password($this->data['User']['oldpassword']);
                                    if($oldPassword!=$userResult['User']['password']){
                                          $this->Session->setFlash("<div class='alert_success'>Please Enter correct old password</div>");
                                          $this->redirect(array('controller' => 'Stores', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                                    }
                              }
                              $this->User->id=AuthComponent::User('id');
                              if($this->User->saveUserInfo($this->request->data['User'])){
                                 $this->Session->setFlash("<div class='alert_success'>Profile has been updated successfully</div>");
                                 $this->redirect(array('controller' => 'Stores', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                              }else{
                                 $this->Session->setFlash("<div class='alert_error'>Profile not updated successfully</div>");
                                 $this->redirect(array('controller' => 'Stores', 'action' => 'myProfile',$encrypted_storeId,$encrypted_merchantId));   
                              }
                        
                        }
               }
                $this->set(compact('roleId'));
               $this->request->data['User']=$userResult['User'];
               //$this->request->data['User']['dateOfBirth']=$this->Dateform->us_format($userResult['User']['dateOfBirth']);
      }
      
      
      /*------------------------------------------------
      Function name:activateStaff()savePermission
      Description:Delete users
      created:27/7/2015
     -----------------------------------------------------*/  
      public function activateStaff($EncrypteduserID=null,$status=0){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"manageStaff")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            }
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['User']['store_id']=$this->Session->read('store_id');
            $data['User']['id']=$this->Encryption->decode($EncrypteduserID);
            $data['User']['is_active']=$status;
            if($this->User->saveUserInfo($data)){
               if($status){
                  $SuccessMsg="Staff Activated";
               }else{
                  $SuccessMsg="Staff Deactivated and member will not able to log in to system";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'staffList'));
            }
      }
      
      
      /*------------------------------------------------
   Function name:checkStoreEmail()
   Description:For logout of the user
   created:22/7/2015
  -----------------------------------------------------*/     
        
      public function checkStoreEmail($roleId=null) {        
             $this->autoRender=false;             
             if($_GET){              
                $emailEntered=$_GET['data']['User']['email'];
               $storeId="";
               $merchantId="";                
               $storeId=$this->Session->read('store_id');                 
               $emailStatus=$this->User->storeemailExists($emailEntered,$roleId,$storeId);               
               echo json_encode($emailStatus);
            }
        }
        
      public function permission($userid=null,$permission=null){
         $this->autoRender=false;    
         //pr($permission);die;
         //$userid=52;
         if($permission){
            $this->Permission->DeleteAllPermission($userid);
            $permissiondata=array_filter($permission['tab_id']);
            //pr($permissiondata);die;
            foreach($permissiondata as $pkey => $tab_id){
                $permissionid=$this->Permission->checkPermissionExists($tab_id,$userid);                
                if($permissionid){
                  $data['id']=$permissionid['Permission']['id'];                   
                }else{
                  $data['id']='';
                }
                $data['tab_id']=$tab_id;
                $data['user_id']=$userid;
                $data['is_deleted']=0;
                $this->Permission->savePermission($data); 
            }                        
         }
      }
      
      /*------------------------------------------------
      Function name:deleteStoreBackgroundPhoto()
      Description:delete Images store background
      created:27/7/2015
      -----------------------------------------------------*/
      public function deleteStoreBackgroundPhoto($EncryptedStoreID=null){
            if(!$this->Common->checkPermissionByaction($this->params['controller'],"configuration")){
               $this->Session->setFlash(__("Permission Denied"));
               $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
            } 
            $this->layout="admin_dashboard";       
            $storeId=$this->Encryption->decode($EncryptedStoreID);
            if($storeId){
               $merchantId= $this->Session->read('merchant_id');
               $storeId= $this->Session->read('store_id');               
               $data['id']=$storeId;
               $data['background_image']='';
               if($this->Store->saveStoreInfo($data)){
                  $this->Session->setFlash(__("Background Photo successfully Deleted"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'configuration'));
               }else{
                  $this->Session->setFlash(__("Some Problem occured"));
                  $this->redirect(array('controller' => 'Stores', 'action' => 'configuration'));
               }
            }
      }   
}

















?>

<?php
App::uses('AppController', 'Controller');
class CouponsController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses=array('Coupon','UserCoupon');
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
      parent::beforeFilter();
      $adminfunctions=array('addCoupon','index','activateCoupon','deleteCoupon','editCoupon','shareCoupon');
      if(in_array($this->params['action'],$adminfunctions)){
	 if(!$this->Common->checkPermissionByaction($this->params['controller'])){
	   $this->Session->setFlash(__("Permission Denied"));
	   $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
	 }
      }
            
   }
   
   /*------------------------------------------------
      Function name:addCoupon()
      Description:Add New Coupon
      created:8/8/2015
     -----------------------------------------------------*/ 
   
   public function addCoupon() {
       $this->layout="admin_dashboard";
       $storeId=$this->Session->read('store_id');
       $merchantId= $this->Session->read('merchant_id');
           if($this->request->data){
	      $couponTitle=trim($this->data['Coupon']['name']);
	      $couponCode=trim($this->data['Coupon']['coupon_code']);
             $isUniqueName=$this->Coupon->checkCouponUniqueName($couponTitle,$storeId);
	     $isUniqueCode=$this->Coupon->checkCouponUniqueCode($couponCode,$storeId);
             if($isUniqueName){
	       if($isUniqueCode){
               $coupondata['store_id']=$storeId;
               $coupondata['merchant_id']=$merchantId;
	       $coupondata['name']=$this->data['Coupon']['name'];
               $coupondata['coupon_code']=$this->data['Coupon']['coupon_code'];
               $coupondata['number_can_use']=$this->data['Coupon']['number_can_use'];
               $coupondata['discount_type']=$this->data['Coupon']['discount_type'];
	       $coupondata['discount']=$this->data['Coupon']['discount'];
	       $coupondata['is_active']=$this->data['Coupon']['is_active'];
	       if(isset($this->data['Coupon']['promotional_message']) && $this->data['Coupon']['promotional_message']){
		  $coupondata['promotional_message']=$this->data['Coupon']['promotional_message'];
	       }
               $this->Coupon->create();
              $this->Coupon->saveCoupon($coupondata);     
              $this->Session->setFlash(__("Coupon Successfully Added"));
              $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
	       }else{
		   $this->Session->setFlash(__("Coupon Code Already exists"));
	       }
          }
          else{
            $this->Session->setFlash(__("Coupon Title Already exists"));
          }
	   }
         
	}
	
	
	/*------------------------------------------------
      Function name:index()
      Description:Display the list of coupon
      created:8/8/2015
     -----------------------------------------------------*/ 
	
        public function index($clearAction=null){
	 
       $this->layout="admin_dashboard";
       $storeID=$this->Session->read('store_id');    
       $criteria = "Coupon.store_id =$storeID AND Coupon.is_deleted=0";
        if($this->Session->read('CouponSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('CouponSearchData'),true);
      }else{
            $this->Session->delete('CouponSearchData');
      }
       if (!empty($this->request->data)) {
            $this->Session->write('CouponSearchData',json_encode($this->request->data));
         if($this->request->data['Coupon']['is_active']!=''){
              $active = trim($this->request->data['Coupon']['is_active']);
              $criteria .= " AND (Coupon.is_active =$active)";
          }
      }    
       $this->paginate= array('conditions'=>array($criteria),'order'=>array('Coupon.created'=>'DESC'));
       $coupondetail=$this->paginate('Coupon');
      // pr($coupondetail);die;
      $this->set('list',$coupondetail);
    
	}
	
	/*------------------------------------------------
      Function name:activateCoupon()
      Description:Active/deactive Coupon
      created:08/8/2015
     -----------------------------------------------------*/  
      public function activateCoupon($EncryptCouponID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Coupon']['store_id']=$this->Session->read('store_id');
            $data['Coupon']['id']=$this->Encryption->decode($EncryptCouponID);
            $data['Coupon']['is_active']=$status;
            if($this->Coupon->saveCoupon($data)){
               if($status){
                  $SuccessMsg="Coupon Activated";
               }else{
                  $SuccessMsg="Coupon Deactivated and Coupon will not get Display in Menu List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
            }
      }
   
    /*------------------------------------------------
      Function name:deleteCoupon()
      Description:Delete Coupon
      created:08/8/2015
     -----------------------------------------------------*/  
      public function deleteCoupon($EncryptCouponID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Coupon']['store_id']=$this->Session->read('store_id');
            $data['Coupon']['id']=$this->Encryption->decode($EncryptCouponID);
            $data['Coupon']['is_deleted']=1;
            if($this->Coupon->saveCoupon($data)){
               $this->Session->setFlash(__("Coupon deleted"));
               $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
            }
      }
      
       /*------------------------------------------------
      Function name:editCoupon()
      Description:Edit Coupon
      created:08/8/2015
     -----------------------------------------------------*/  
      public function editCoupon($EncryptCouponID=null){
        $this->layout="admin_dashboard";
           
             $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $data['Coupon']['id']=$this->Encryption->decode($EncryptCouponID);
             $this->loadModel('Coupon');
            $couponDetail=$this->Coupon->getCouponDetail($data['Coupon']['id'], $storeId);
             
              if($this->request->data){
           $couponTitle=trim($this->data['Coupon']['name']);
	      $couponCode=trim($this->data['Coupon']['coupon_code']);
             $isUniqueName=$this->Coupon->checkCouponUniqueName($couponTitle,$storeId,$data['Coupon']['id']);
	     $isUniqueCode=$this->Coupon->checkCouponUniqueCode($couponCode,$storeId,$data['Coupon']['id']);
             if($isUniqueName){
	       if($isUniqueCode){
               $coupondata['store_id']=$storeId;
               $coupondata['merchant_id']=$merchantId;
	        $coupondata['id']=$data['Coupon']['id'];
	       $coupondata['name']=$this->data['Coupon']['name'];
               $coupondata['coupon_code']=$this->data['Coupon']['coupon_code'];
               $coupondata['number_can_use']=$this->data['Coupon']['number_can_use'];
               $coupondata['discount_type']=$this->data['Coupon']['discount_type'];
	       $coupondata['discount']=$this->data['Coupon']['discount'];
	       $coupondata['is_active']=$this->data['Coupon']['is_active'];
	       if(isset($this->data['Coupon']['promotional_message']) && $this->data['Coupon']['promotional_message']){
		  $coupondata['promotional_message']=$this->data['Coupon']['promotional_message'];
	       }
               $this->Coupon->create();
              $this->Coupon->saveCoupon($coupondata);     
              $this->Session->setFlash(__("Coupon Successfully Updated"));
              $this->redirect(array('controller' => 'coupons', 'action' => 'index'));
	       }else{
		   $this->Session->setFlash(__("Coupon Code Already exists"));
	       }
          }
          else{
            $this->Session->setFlash(__("Coupon Title Already exists"));
          }
              }
            $this->request->data=$couponDetail;
      }
      
      /*------------------------------------------------
      Function name:shareCoupon()
      Description:Share the coupon to customers
      created:08/8/2015
     -----------------------------------------------------*/ 
	
        public function shareCoupon($EncryptCouponID=null){
	 
       $this->layout="admin_dashboard";
       $storeId=$this->Session->read('store_id');
       if($EncryptCouponID){
	 $data['Coupon']['id']=$this->Encryption->decode($EncryptCouponID);
       }else{
	 $data['Coupon']['id']=$this->data['User']['coupon_id'];
       }
       $couponDetail=$this->Coupon->getCouponDetail($data['Coupon']['id'], $storeId); //pr($couponDetail);
       $storeID=$this->Session->read('store_id');
       $this->set('couponId',$couponDetail);
       $criteria = "User.store_id =$storeID AND User.role_id =4 AND User.is_deleted=0";
       if (!empty($this->request->data)) {
	 $this->request->data['User']['id']=array_filter($this->request->data['User']['id']);         
         $this->loadModel('Store');
         $storeEmail=$this->Store->fetchStoreDetail($storeId);
         $merchantId=$this->Session->read('merchant_id');         
         $roleId = 4;	    
	 $i=0;
	 $alreadyShared=0;
	 $newshared=0;
	 foreach($this->request->data['User']['id'] as  $key => $data){	       
		  $sharecriteria = "User.store_id =$storeId AND User.role_id =4 AND User.id =$data AND User.is_deleted=0";
		  $this->loadModel('User');
		  $this->paginate= array('conditions'=>array($sharecriteria),'order'=>array('User.created'=>'DESC'),'recursive'=>-1);
		  $shareuserdetail=$this->paginate('User');
	    
		  if($shareuserdetail[0]['User']['is_emailnotification'] == 1){
		  $userCoupon['UserCoupon']['user_id']=$data ;
		  $userCoupon['UserCoupon']['store_id']=$storeId ;
		  $userCoupon['UserCoupon']['coupon_id']=$this->request->data['User']['coupon_id'];
		  $userCoupon['UserCoupon']['coupon_code']=$this->request->data['User']['coupon_code'];
		  $userCoupon['UserCoupon']['merchant_id']=$merchantId ;
		 
		 $isUniqueUserShare=$this->UserCoupon->checkUserCouponData($userCoupon['UserCoupon']['user_id'],$userCoupon['UserCoupon']['coupon_code'],$userCoupon['UserCoupon']['store_id'],$userCoupon['UserCoupon']['coupon_id']);
		if($isUniqueUserShare){
		      $i++;
		   if($shareuserdetail[0]['User']['lname']){
		      $fullName=$shareuserdetail[0]['User']['fname']." ".$shareuserdetail[0]['User']['lname'];
		   }else{
		      $fullName=$shareuserdetail[0]['User']['fname'];
		   }
		   $emailSuccess=''; 
		  if($couponDetail['Coupon']['promotional_message']){
		   
			$emailData = nl2br($couponDetail['Coupon']['promotional_message']);
			$subject="Coupon";
			
		  }else{
		   
		     $template_type= 'coupon_offer';
		     $this->loadModel('EmailTemplate');
		     $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$storeId,$merchantId,$template_type);
		     if($emailSuccess){
			$emailData = $emailSuccess['EmailTemplate']['template_message'];
			$subject=$emailSuccess['EmailTemplate']['template_subject'];
		     }
		  }		      
		  $couponcode = $this->request->data['User']['coupon_code'];      
		  $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);		 
		  $emailData = str_replace('{COUPON}',$couponcode, $emailData);
		  //pr($emailData);die;
		  //$activationLink=HTTP_ROOT."users/login";
		  $subject = ucwords(str_replace('_', ' ', $subject));
		  $this->Email->to = $shareuserdetail[0]['User']['email'];
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
		  
		     $this->Email->sendAs = 'html'; // because we like to send pretty mail
		 
		 // $this->Email->delivery = 'smtp';
		  $this->Email->send();       
		  $this->UserCoupon->create();
		  $this->UserCoupon->saveUserCoupon($userCoupon);		 
		  $newshared++;		    
	       }else{
		  $alreadyShared++;
	       }
	       
	       
	 }
	    }      
	 
	 $message="";
	 if($newshared){
	    $message.="Coupon has been shared to ".$newshared." Users <br>";
	 }	 
	 if($alreadyShared){
	    $message.="Coupon has already shared to ".$alreadyShared." Users";
	 }
	 $this->Session->setFlash(_($message));
	 $this->redirect($this->request->referer());    
      }

       $this->loadModel('User');
       $this->paginate= array('conditions'=>array($criteria),'order'=>array('User.created'=>'DESC'),'recursive'=>-1);
       $userdetail=$this->paginate('User');
       //pr($coupondetail);die;
      $this->set('list',$userdetail);
    
	}
        
    /*------------------------------------------------
      Function name:myCoupons()
      Description:List of User Coupons
      created:12/8/2015
    -----------------------------------------------------*/  
   
    public function myCoupons($encrypted_storeId=null,$encrypted_merchantId=null/*,$encrypted_userId=null*/){
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
        $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
        $decrypt_userId=AuthComponent::User('id');
        $this->set(compact('encrypted_storeId','encrypted_merchantId','encrypted_userId'));
        $this->UserCoupon->bindModel(array('belongsTo'=>array('Coupon')), false);
        $myCoupons = $this->UserCoupon->getCouponDetails($decrypt_merchantId,$decrypt_storeId,$decrypt_userId);
        $this->set(compact('myCoupons'));
    }
    
    /*------------------------------------------------
      Function name:deleteUserCoupon()
      Description:Delete User Coupon
      created:12/8/2015
     -----------------------------------------------------*/  
      public function deleteUserCoupon($encrypted_storeId=null,$encrypted_merchantId=null,/*$encrypted_userId=null,*/$encrypted_userCouponId=null){
            $this->autoRender=false;     
            $data['UserCoupon']['id'] = $this->Encryption->decode($encrypted_userCouponId);
            $data['UserCoupon']['is_deleted']=1;
            if($this->UserCoupon->saveUserCoupon($data)){
               $this->Session->setFlash(__("Coupon has been deleted"));
               $this->redirect(array('controller' => 'Coupons', 'action' => 'myCoupons',$encrypted_storeId,$encrypted_merchantId/*,$encrypted_userId*/));
            }else{
               $this->Session->setFlash(__("Some problem has been occured"));
               $this->redirect(array('controller' => 'Coupons', 'action' => 'myCoupons',$encrypted_storeId,$encrypted_merchantId/*,$encrypted_userId*/));
            }
      }
	
	
}
?>
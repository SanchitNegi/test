<?php
App::uses('AppController', 'Controller');
class OrdersController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','Dateform');
   public $uses=array('OrderOffer','Order','Item','ItemPrice','ItemType','Size','OrderItem','StoreReview','Favorite','Topping','OrderTopping');

   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
            parent::beforeFilter();
            $adminfunctions=array('index','orderDetail','UpdateOrderStatus','reviewRating','approvedReview');
            if(in_array($this->params['action'],$adminfunctions)){
               if(!$this->Common->checkPermissionByaction($this->params['controller'])){
                 $this->Session->setFlash(__("Permission Denied"));
                 $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
               }
            }
   }

   /*------------------------------------------------
      Function name:index()
      Description:List Menu Items
      created:5/8/2015
     -----------------------------------------------------*/
   public function index($clearAction=null){
      $this->layout="admin_dashboard";
      $storeID=$this->Session->read('store_id');
      $value = "";
      $criteria = "Order.store_id =$storeID AND Order.is_deleted=0 AND User.role_id=4";
      if($this->Session->read('OrderSearchData') && $clearAction!='clear' && !$this->request->is('post')){
            $this->request->data = json_decode($this->Session->read('OrderSearchData'),true);
      }else{
            $this->Session->delete('OrderSearchData');
      }
      if (!empty($this->request->data)) {
          $this->Session->write('OrderSearchData',json_encode($this->request->data));

          if (!empty($this->request->data['Order']['keyword'])) {
              $value = trim($this->request->data['Order']['keyword']);
              $criteria .= " AND (Order.order_number LIKE '%" . $value . "%' OR User.fname LIKE '%" . $value . "%' OR User.lname LIKE '%" . $value . "%' OR User.email LIKE '%" . $value . "%' OR DeliveryAddress.phone LIKE '%" . $value . "%')";
          }

          if(!empty($this->request->data['OrderStatus']['id'])){
             $orderStatusID = trim($this->request->data['OrderStatus']['id']);
              $criteria .= " AND (Order.order_status_id =$orderStatusID)";
          }

          if($this->request->data['Segment']['id']!=''){
              $type = trim($this->request->data['Segment']['id']);
              $criteria .= " AND (Order.seqment_id =$type)";
          }
      }

      $this->OrderItem->bindModel(array('belongsTo' => array(
                       'Item'=>array( 'className' => 'Item','foreignKey'=>'item_id'),
                       'Type'=>array( 'className' => 'Type','foreignKey'=>'type_id'),
                       'Size'=>array('className' => 'Size','foreignKey'=>'size_id'))), false);
      $this->Order->bindModel(
               array(
                 'belongsTo'=>array(
                    'User' =>array(
                     'className' => 'User',
                      'foreignKey' => 'user_id'


                   ),'Segment' =>array(
                     'className' => 'Segment',
                      'foreignKey' => 'seqment_id'
                   ),
                    'OrderStatus' =>array(
                     'className' => 'OrderStatus',
                      'foreignKey' => 'order_status_id'
                   ),
                    'DeliveryAddress' =>array(
                     'className' => 'DeliveryAddress',
                      'foreignKey' => 'delivery_address_id'
                    )
                  ),
                 'hasMany'=>array(
                     'OrderItem' =>array(
                     'className' => 'OrderItem',
                      'foreignKey' => 'order_id'
                 ),
                 )
               ),false
             );
      $this->paginate= array('recursive'=>2,'conditions'=>array($criteria),'order'=>array('Order.created'=> 'DESC'));
      $orderdetail=$this->paginate('Order');
   //pr($orderdetail);die;
      $this->set('list',$orderdetail);
      $this->loadModel('OrderStatus');
      $statusList=$this->OrderStatus->OrderStatusList($storeID);
      $this->loadModel('Segment');
      $typeList=$this->Segment->OrderTypeList($storeID);
      $this->set('statusList',$statusList);
       $this->set('typeList',$typeList);
      $this->set('keyword', $value);
   }

   /*------------------------------------------------
      Function name:myOrders()
      Description:List Orders and Favourite Orders
      created:11/8/2015
     -----------------------------------------------------*/

    public function myOrders($encrypted_storeId=null,$encrypted_merchantId=null){
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
        $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
        $decrypt_userId = AuthComponent::User('id');
        $this->set(compact('encrypted_storeId','encrypted_merchantId'));
        $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('fields'=>array('name')))), false);
        $this->OrderOffer->bindModel(array('belongsTo'=>array('Item'=>array('foreignKey'=>'offered_item_id','fields'=>array('name')))), false);
        $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderOffer'=>array('fields'=>array('offered_item_id','quantity')),'OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
        $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('DeliveryAddress'=>array('fields'=>array('name_on_bell','city','address')),'OrderStatus'=>array('fields'=>array('name')))), false);
        $this->Favorite->bindModel(array('belongsTo' => array('Order'=>array('fields'=>array('id','user_id','order_number','amount','seqment_id','delivery_address_id')))), false);
        $myOrders = $this->Order->getOrderDetails($decrypt_merchantId,$decrypt_storeId,$decrypt_userId);
        $myFav = $this->Favorite->getFavoriteDetails($decrypt_merchantId,$decrypt_storeId,$decrypt_userId);
        $compare = array();
        foreach($myFav as $fav){
            $compare[] = $fav['Favorite']['order_id'];
        }
        $this->set(compact('myOrders','compare','encrypted_storeId','encrypted_merchantId'));
   }

   /*------------------------------------------------
      Function name:myFavorites()
      Description:List Orders and Favourite Orders
      created:11/8/2015
     -----------------------------------------------------*/

    public function myFavorites($encrypted_storeId=null,$encrypted_merchantId=null){
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
        $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
        $decrypt_userId = AuthComponent::User('id');
        $this->set(compact('encrypted_storeId','encrypted_merchantId'));
         $this->OrderOffer->bindModel(array('belongsTo'=>array('Item'=>array('foreignKey'=>'offered_item_id','fields'=>array('name')))), false);
        $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('fields'=>array('name')))), false);
        $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderOffer'=>array('fields'=>array('offered_item_id','quantity')),'OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
        $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('DeliveryAddress'=>array('fields'=>array('name_on_bell','city','address')),'OrderStatus'=>array('fields'=>array('name')))), false);
        $this->Favorite->bindModel(array('belongsTo' => array('Order'=>array('fields'=>array('id','user_id','order_number','amount','seqment_id','delivery_address_id','order_status_id')))), false);
        $myFav = $this->Favorite->getFavoriteDetails($decrypt_merchantId,$decrypt_storeId,$decrypt_userId);
        $this->set(compact('myFav','encrypted_storeId','encrypted_merchantId'));
   }

    /*------------------------------------------------
      Function name: rating()
      Description: Review and Rating for orders
      created:11/8/2015
    -----------------------------------------------------*/

   public function rating($encrypted_storeId=null,$encrypted_merchantId=null,$order_item_id=null,$order_id=null,$status=null,$orderName=null){
        $this->layout="customer_dashboard";
        $decrypt_storeId = $this->Encryption->decode($encrypted_storeId);
        $decrypt_merchantId = $this->Encryption->decode($encrypted_merchantId);
        $order_item_id = $this->Encryption->decode($order_item_id);
        $order_id = $this->Encryption->decode($order_id);
        $user_id = AuthComponent::User('id');
        $status = $this->Encryption->decode($status);
        $orderName = $this->Encryption->decode($orderName);
        $this->StoreReview->bindModel(array('belongsTo'=>array('User'=>array('fields'=>array('salutation','fname','lname')))), false);
        $allReviews = $this->StoreReview->getReviewDetails($decrypt_storeId,$order_item_id);
        $this->set(compact('orderName','allReviews','status','allReviwes','encrypted_storeId','encrypted_merchantId','decrypt_storeId','decrypt_merchantId','order_item_id','order_id','user_id'));
        if($this->data){
            $data = $this->data;
            $encrypted_storeId=$this->Encryption->encode($data['StoreReview']['store_id']);
            $encrypted_merchantId=$this->Encryption->encode($data['StoreReview']['merchant_id']);
            $this->StoreReview->create();
            if($this->StoreReview->saveReview($data)){
                $template_type = REVIEW_RATING;
                $this->loadModel('EmailTemplate');
                $roleId=AuthComponent::User('role_id');
                $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$decrypt_storeId,$decrypt_merchantId,$template_type);
                if($emailSuccess){
                    $fullName="Admin";
                    $item_name = $data['StoreReview']['item_name'];
                    $review = $data['Booking']['number_person'];//no of person
                    $rating = $data['Booking']['start_date'];
                    $customer_name = AuthComponent::User('fname')." ".AuthComponent::User('lname');
                    $storeEmail= trim($store['Store']['email_id']);
                    $customerEmail=trim(AuthComponent::User('email'));
                    $emailData = $emailSuccess['EmailTemplate']['template_message'];
                    $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                    $emailData = str_replace('{REVIEW}',$review, $emailData);
                    $emailData =str_replace('{RATING}',$rating, $emailData);
                    $emailData =str_replace('{ITEM_NAME}',$number_person, $item_name);
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
                    $this->Email->send();
                }
                $this->Session->setFlash(__("Rating & Review has been saved successfully"));
            }else{
               $this->Session->setFlash(__("Some problem has been occured"));
            }
            $this->redirect(array('controller' => 'orders', 'action' => 'myOrders',$encrypted_storeId,$encrypted_merchantId));
        }
   }

    /*------------------------------------------------
      Function name: myFavorite()
      Description: Add/Remove favorite
      created:11/8/2015
     -----------------------------------------------------*/
      public function myFavorite($encrypted_storeId=null,$encrypted_merchantId=null,$order_id=null,$fav_id=null){
        $this->autoRender=false;
        if(!empty($fav_id)){
            $data['Favorite']['id'] = $this->Encryption->decode($fav_id);
        }
        $data['Favorite']['store_id'] = $this->Encryption->decode($encrypted_storeId);
        $data['Favorite']['user_id'] = AuthComponent::User('id');
        $data['Favorite']['merchant_id'] = $this->Encryption->decode($encrypted_merchantId);
        $data['Favorite']['order_id'] = $this->Encryption->decode($order_id);

        if($this->Favorite->saveFavorite($data)){
            $this->Session->setFlash(__("Your favorite list has been updated"));
            $this->redirect(array('controller' => 'orders', 'action' => 'myFavorites',$encrypted_storeId,$encrypted_merchantId));
        }else{
            $this->Session->setFlash(__("Some problem has been occured"));
            $this->redirect(array('controller' => 'orders', 'action' => 'myFavorites',$encrypted_storeId,$encrypted_merchantId));
        }
    }
      /*------------------------------------------------
      Function name: orderDetail()
      Description: Dispaly the detail of perticular order
      created:12/8/2015
     -----------------------------------------------------*/
      public function orderDetail($order_id=null){
            $this->layout="admin_dashboard";
            $storeID=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $orderId = $this->Encryption->decode($order_id);
            $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('className' => 'Topping','foreignKey'=>'topping_id','fields'=>array('name')))), false);
            $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
            $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('DeliveryAddress'=>array('fields'=>array('name_on_bell','city','address')),'User'=>array('fields'=>array('address')),'OrderStatus'=>array('fields'=>array('name')))), false);
            $orderDetails = $this->Order->getSingleOrderDetail($merchantId,$storeID,$orderId);
            $this->set('orderDetail',$orderDetails);
            $this->loadModel('OrderStatus');
            $statusList=$this->OrderStatus->OrderStatusList($storeID);
            $this->set('statusList',$statusList);
      }
         /*------------------------------------------------
         Function name: UpdateOrderStatus()
         Description: Update the order status
         created:12/8/2015
        -----------------------------------------------------*/
         public function UpdateOrderStatus(){
               $this->autoRender=false;
               $this->layout="admin_dashboard";
               $storeID=$this->Session->read('store_id');
               $merchantId= $this->Session->read('merchant_id');
               $roleId = 4;
               $this->loadModel('Store');
               $storeEmail=$this->Store->fetchStoreDetail($storeID);
               if(!empty($this->request->data['Order']['id'])){
               $filter_array = array_filter($this->request->data['Order']['id']);
               foreach($filter_array as $k=>$orderId){
               $this->loadModel('Order');
               $this->loadModel('OrderTopping');
               $this->loadModel('OrderItem');
               $orderIdn =$orderId;
               $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('className' => 'Topping','foreignKey'=>'topping_id','fields'=>array('name')))), false);
               $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
               $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('User'=>array('fields'=>array('fname','lname','email','phone','is_smsnotification','is_emailnotification')),'OrderStatus'=>array('fields'=>array('name')))), false);
               $this->Order->id=$orderId;
               $this->Order->saveField("order_status_id",$this->request->data['Order']['order_status_id']);

              /*********mail send start**********/
             $this->loadModel('EmailTemplate');
             $template_type= 'order_status';
	    $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$storeID,$merchantId,$template_type);
	    if($emailSuccess){
	       $emailData = $emailSuccess['EmailTemplate']['template_message'];
               $smsData = $emailSuccess['EmailTemplate']['sms_template'];
	       $subject=$emailSuccess['EmailTemplate']['template_subject'];
                     }   
            
            $orderDetails = $this->Order->getSingleOrderDetail($merchantId,$storeID,$orderIdn);
            
            
            
            $fullName=$orderDetails[0]['User']['fname']." ".$orderDetails[0]['User']['lname'];
            $order=$orderDetails[0]['Order']['order_number'];
	    
            $status  =  $orderDetails[0]['OrderStatus']['name'];
               
                         if($orderDetails[0]['User']['is_emailnotification'] == 1){     
               $itemsforMail="";
                foreach($orderDetails[0]['OrderItem'] as $key => $item){
                     $itemsforMail.= $item['quantity']." X ". $item['Item']['name'] ."<br/>";
                  }
                  $total_amount = "$".$orderDetails[0]['Order']['amount'];
                  $emailData = str_replace('{AMOUNT}',$total_amount, $emailData);
                  $emailData = str_replace('{ITEM}',$itemsforMail, $emailData);
		  $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                  $emailData = str_replace('{ORDER}',$order, $emailData);
		  $emailData = str_replace('{STATUS}',$status, $emailData);
                          
		  $subject = ucwords(str_replace('_', ' ', $subject));
                  $orderDetails[0]['User']['email'];
                  $this->Email->to = $orderDetails[0]['User']['email'];
		  $this->Email->subject =$subject;
		  $this->Email->from = $storeEmail['Store']['email_id'];
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
		 $this->Email->send();
                 
               }
                 /********Mail send end************/
               /**************sms gateway data**************/
                  $smsData = str_replace('{username}',$fullName, $smsData);
                  $smsData = str_replace('{Ordernumber}',$order, $smsData);
		  $smsData = str_replace('{OrderStatus}',$status, $smsData);
                  
                  /*************end sms gateway data***********/
            if($orderDetails[0]['User']['is_smsnotification'] == 1){
            $message=$smsData;
            $tonumber="+91".$orderDetails[0]['User']['phone'];
            $this->Common->sendSmsNotification($tonumber,$message);
                 }

           

                    }

         }
       /*********send mail only one user**********/
          if(!empty($this->request->data['Orders']['id'])){
           $this->Order->id=$this->request->data['Orders']['id'];
           $this->Order->saveField("order_status_id",$this->request->data['Order']['order_status_id']);
           $this->loadModel('Order');
           $orderIdn =$this->request->data['Orders']['id'];
           $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('className' => 'Topping','foreignKey'=>'topping_id','fields'=>array('name')))), false);
           $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
           $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('User'=>array('fields'=>array('fname','lname','email','phone','is_smsnotification','is_emailnotification')),'OrderStatus'=>array('fields'=>array('name')))), false);

              /*********mail send start**********/
          $this->loadModel('EmailTemplate');
            $template_type= 'order_status';
	 $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$storeID,$merchantId,$template_type);
	  if($emailSuccess){
	    $emailData = $emailSuccess['EmailTemplate']['template_message'];
            $smsData = $emailSuccess['EmailTemplate']['sms_template'];
	    $subject=$emailSuccess['EmailTemplate']['template_subject'];
                           }

       
         $orderDetails = $this->Order->getSingleOrderDetail($merchantId,$storeID,$orderIdn);
        // pr($orderDetails);die;
         $fullName=$orderDetails[0]['User']['fname']." ".$orderDetails[0]['User']['lname'];
         $order=$orderDetails[0]['Order']['order_number'];
	  $status  =  $orderDetails[0]['OrderStatus']['name'];
          if($orderDetails[0]['User']['is_emailnotification'] == 1){     
                 $itemsforMail="";
                 foreach($orderDetails[0]['OrderItem'] as $key => $item){
                     $itemsforMail.= $item['quantity']." X ". $item['Item']['name'] ."<br/>";
                  }
                  $total_amount = "$".$orderDetails[0]['Order']['amount'];
                  $emailData = str_replace('{AMOUNT}',$total_amount, $emailData);
                  $emailData = str_replace('{ITEM}',$itemsforMail, $emailData);
		  $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                   $emailData = str_replace('{ORDER}',$order, $emailData);
		  $emailData = str_replace('{STATUS}',$status, $emailData);
                  
                  
		  $subject = ucwords(str_replace('_', ' ', $subject));
                  $this->Email->to = $orderDetails[0]['User']['email'];
		  $this->Email->subject =$subject;
		  $this->Email->from = $storeEmail['Store']['email_id'];
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
	       $this->Email->send();
          }
           /********Mail send end************/
           /**************sms gateway data**************/
                  $smsData = str_replace('{username}',$fullName, $smsData);
                  $smsData = str_replace('{Ordernumber}',$order, $smsData);
		  $smsData = str_replace('{OrderStatus}',$status, $smsData);
                  
                  /*************end sms gateway data***********/
           if($orderDetails[0]['User']['is_smsnotification'] == 1){
            $message=$smsData;
            $tonumber="+91".$orderDetails[0]['User']['phone'];
            $this->Common->sendSmsNotification($tonumber,$message);
           }
         }
          $this->Session->setFlash(__("Order status updated successfully."));
          $this->redirect(array('action'=>'index','controller'=>'orders'));
      }

       /*------------------------------------------------
      Function name: reviewRating()
      Description: Display the list of Reviews and Ratings in admin panel
      created:13/8/2015
     -----------------------------------------------------*/

      public function reviewRating($clearAction=null){
          if(!$this->Common->checkPermissionByaction($this->params['controller'],$this->params['action'])){
              $this->Session->setFlash(__("Permission Denied"));
              $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
              }
              $this->layout="admin_dashboard";
              $storeID=$this->Session->read('store_id');
              $merchantId= $this->Session->read('merchant_id');
              $value = "";
              $criteria="";
              $criteria = "StoreReview.store_id =$storeID AND StoreReview.is_deleted=0 AND StoreReview.is_approved=0";
              if($this->Session->read('RatingSearchData') && $clearAction!='clear' && !$this->request->is('post')){
              $this->request->data = json_decode($this->Session->read('RatingSearchData'),true);
             }else{
            $this->Session->delete('RatingSearchData');
           }
          if (!empty($this->request->data)) {
             $this->Session->write('RatingSearchData',json_encode($this->request->data));

             if (!empty($this->request->data['User']['keyword'])) {
              $value = trim($this->request->data['User']['keyword']);
              $criteria .= " AND (StoreReview.review_comment LIKE '%" . $value . "%' OR Order.order_number LIKE '%" . $value . "%')";
          }


         if($this->request->data['StoreReview']['review_rating']!=''){
              $rating = trim($this->request->data['StoreReview']['review_rating']);
              $criteria .= " AND (StoreReview.review_rating =$rating)";
          }


            }
             $this->loadModel('Order');
             $this->loadModel('OrderItem');
            $this->OrderItem->bindModel(array('belongsTo' => array('Item'=>array('className' => 'Item','foreignKey'=>'item_id','fields'=>'name'))));
            $this->loadModel('StoreReview');
            $this->StoreReview->bindModel(array('belongsTo'=>array('Order'=>array('className' => 'Order','foreignKey'=>'order_id') , 'OrderItem'=>array('className' => 'OrderItem','foreignKey'=>'order_item_id'))));
            $this->paginate= array('conditions'=>array($criteria),'order'=>array('StoreReview.created'=>'DESC'),'recursive'=>2);
            $reviewdetail=$this->paginate('StoreReview');
             $this->set('keyword', $value);
             $this->set('list',$reviewdetail);

      }

    /*------------------------------------------------
      Function name: ApprovedReview()
      Description: Review approve and disapproved
      created:14/8/2015
     -----------------------------------------------------*/
      public function approvedReview($EncryptReviewID=null,$status=0){
           $this->autoRender=false;
           $this->layout="admin_dashboard";
           $id=$this->Encryption->decode($EncryptReviewID);
           $this->StoreReview->id=$id;
           $this->StoreReview->saveField("is_approved",$status);
           $this->Session->setFlash(__("Review status updated successfully."));
           $this->redirect($this->request->referer());
      }


      public function ajaxRequest($id=''){
         $this->autoRender=false;
         $this->loadModel('OrderStatus');
         $this->layout="admin_dashboard";
         if (!empty($this->request->params['requested'])) {
         $data = $this->OrderStatus->find('first',array('conditions'=>array('OrderStatus.id'=>$id)));
         echo $data['OrderStatus']['name'];
        }

      }



}

?>
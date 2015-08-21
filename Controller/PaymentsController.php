<?php
App::uses('AppController', 'Controller');
class PaymentsController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common','AuthorizeNet');
   public $helper=array('Encryption');
   public $uses='User';
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
             parent::beforeFilter();
            
   }
   

   
   /*------------------------------------------------
   Function name:payments()
   Description:Registration  Form for the  End customer
   created:22/7/2015
  -----------------------------------------------------*/
   
   public function paymentSection(){
      $this->autoRender=false;
      $useId="";
      if(AuthComponent::User()){
         $userId=AuthComponent::User('id');
      }else{
         $userId=0;
      }
   
      $comment=$this->request->data['User']['comment'];
      if($this->request->data['payment']==1){  //Credit card
         //echo "<pre>";print_r($_SESSION);die;
         $this->loadModel('Store');
          $store_id=$this->Session->read('store_id');
         $payment_info=$this->Store->fetchStoreDetail($store_id,'');
         //print_r($payment_info);die;
         $LoginId = $payment_info['Store']['api_username'];
         $TransactionKey =$payment_info['Store']['api_key'];
         $amount=$this->Session->read('Cart.grand_total_final');
         $card_num=$this->request->data['Payment']['cardnumber'];
         $exp_date=$this->request->data['Payment']['expiryDate'];
         $response = $this->AuthorizeNet->validate_card($LoginId, $TransactionKey, $amount, $card_num, $exp_date);
            //echo "<pre>";print_r($response);die;
         if ($response->approved==1){
            //echo "Hi";die;
            $store_id=$this->Session->read('store_id');
            $merchant_id=$this->Session->read('merchant_id');
            $transaction_id=$response->transaction_id;
            $amount=$response->amount;
            $responsetext=$response->response_reason_text;
            $payment_gateway="Authorize";
            $payment_status="Paid";
            $this->request->data['OrderPayment']['user_id']=$userId;
            $this->request->data['OrderPayment']['store_id']=$store_id;
            $this->request->data['OrderPayment']['merchant_id']=$merchant_id;
            $this->request->data['OrderPayment']['transection_id']=$transaction_id;
            $this->request->data['OrderPayment']['amount']=$amount;
            $this->request->data['OrderPayment']['payment_status']=$payment_status;
            $this->request->data['OrderPayment']['payment_gateway']=$payment_gateway;
            $this->loadModel('OrderPayment');
            $sucess=$this->OrderPayment->savePayment($this->request->data['OrderPayment']);
            if($sucess){
                 $payment_id=$this->OrderPayment->getLastInsertId();
                 $this->Session->write('Cart.payment_id',$payment_id);
                  $paymemt_type=1;
                  $flag=$this->orderSave($comment,$paymemt_type);
                  if($flag){
                 
                   
                     
                     
                     $this->redirect(array('controller'=>'Payments','action'=>'success'));
                  }else{
                     $this->redirect(array('controller'=>'Payments','action'=>'status'));
                  }
            }else{
                 $this->redirect(array('controller'=>'Payments','action'=>'status'));
            }
         
         
         }else{
           $responsetext=$response->response_reason_text;
           $this->Session->setFlash(__("$responsetext"));
           $this->redirect(array('controller'=>'Products','action'=>'orderDetails'));
   
           // echo "not valid";die;
       
         }
      }else{// Cash On Delivery
            $amount=$this->Session->read('Cart.grand_total_final');
            $store_id=$this->Session->read('store_id');
            $merchant_id=$this->Session->read('merchant_id');
            $transaction_id=0;
            $payment_gateway="NA";
            $payment_status="Cash on Delivery";
            $this->request->data['OrderPayment']['user_id']=$userId;
            $this->request->data['OrderPayment']['store_id']=$store_id;
            $this->request->data['OrderPayment']['merchant_id']=$merchant_id;
            $this->request->data['OrderPayment']['transection_id']=$transaction_id;
            $this->request->data['OrderPayment']['amount']=$amount;
            $this->request->data['OrderPayment']['payment_status']=$payment_status;
            $this->request->data['OrderPayment']['payment_gateway']=$payment_gateway;
            $this->loadModel('OrderPayment');
            $sucess=$this->OrderPayment->savePayment($this->request->data['OrderPayment']);
            if($sucess){
                 $payment_id=$this->OrderPayment->getLastInsertId();
                 $this->Session->write('Cart.payment_id',$payment_id);
                  $paymemt_type=2;//COD
                  $flag=$this->orderSave($comment,$paymemt_type);
                  if($flag){
                     $this->redirect(array('controller'=>'Payments','action'=>'success'));
                  }else{
                       $this->redirect(array('controller'=>'Payments','action'=>'status'));
                  }
            }else{
                $this->redirect(array('controller'=>'Payments','action'=>'status'));
            }
         
      }
   }
   
   /*------------------------------------------------
   Function name:orderSave()
   Description:It will save the order after payment confimation 
   created:22/7/2015
  -----------------------------------------------------*/
   
   
   
   public function orderSave($data=null,$paymemt_type=null){
     
      //if($data){
            $useId="";
          if(AuthComponent::User()){
             $userId=AuthComponent::User('id');
          }else{
             $userId=0;
          }

            $order_number= $this->Common->RandomString();
            $segment_type=$this->Session->read('Cart.segment_type'); // Read the type of Delivery
            $preOrder=$this->Session->read('Order.is_preorder');// Read the PreOrder Type
            if(($segment_type==2 || $segment_type==3)){  // SegmentType:2(Pickup) ,3(Home Deli)
             // echo "hi";die;
              $orderTime=$this->Session->read('Cart.order_time');
              $this->request->data['Order']['pickup_time']=$orderTime;
            }
            if($this->request->data['User']['comment']){
               $this->request->data['Order']['order_comments']=$this->request->data['User']['comment'];
            }
            $this->request->data['Order']['order_number']=$order_number;
            $this->request->data['Order']['seqment_id']=$this->Session->read('Cart.segment_type');
            $this->request->data['Order']['order_status_id']=1;
            $this->request->data['Order']['user_id']=$userId;
            $this->request->data['Order']['amount']=$this->Session->read('Cart.grand_total_final');
            $this->request->data['Order']['payment_id']=$this->Session->read('Cart.payment_id');
            if($this->Session->read('Coupon')){
                $this->request->data['Order']['coupon_code'] = $this->Session->read('Coupon.Coupon.coupon_code');
            } else {
                $this->request->data['Order']['coupon_code'] = "";
            }
            $this->request->data['Order']['coupon_discount'] = $this->Session->read('Discount');
            $this->request->data['Order']['order_comments']=$data;
            $this->request->data['Order']['store_id']=$this->Session->read('store_id');
            $this->request->data['Order']['merchant_id']=$this->Session->read('merchant_id');
            $this->request->data['Order']['delivery_address_id']=$this->Session->read('Order.delivery_address_id');
            $this->request->data['Order']['is_pre_order']=$preOrder;
           
            
            $this->loadModel('Order');
           
           
            $sucess=$this->Order->saveOrder($this->request->data['Order']);   //Save the data in order table 
            $last_id=$this->Order->getLastInsertId();
                
            
            if($sucess){
                $cartItems=$this->Session->read('cart');
                $this->loadModel('OrderItem');
                foreach($cartItems as $result){
                    $this->request->data['OrderItem']['order_id']=$last_id;
                    $this->request->data['OrderItem']['quantity']=$result['Item']['quantity'];
                    $this->request->data['OrderItem']['item_id']=$result['Item']['id'];
                    if(isset($result['Item']['size_id']) && $result['Item']['size_id']){
                        $this->request->data['OrderItem']['size_id']=$result['Item']['size_id'];
                    }else{
                        $this->request->data['OrderItem']['size_id']=0;
  
                    }
                    if(isset($result['Item']['type_id']) && $result['Item']['type_id']){
                        $this->request->data['OrderItem']['type_id']=$result['Item']['type_id'];
                    }else{
                            $this->request->data['OrderItem']['type_id']=0;

                    }
                    $this->request->data['OrderItem']['total_item_price']=$result['Item']['final_price'];
                    $this->request->data['OrderItem']['discount']=0; // Flow is not known for now for this particual field 
                    $this->request->data['OrderItem']['user_id']=$userId;
                    $this->request->data['OrderItem']['store_id']=$this->Session->read('store_id');
                    $this->request->data['OrderItem']['merchant_id']=$this->Session->read('merchant_id');
                    $this->OrderItem->create();
                    $sucessOrderItem=$this->OrderItem->saveOrderItem($this->request->data['OrderItem']);  //Save the data in orderitem table 
                    $order_item_id = $this->OrderItem->getLastInsertId();
                    
                    if(isset($result['Item']['StoreOffer'])){
                        foreach($result['Item']['StoreOffer'] as $storeOffer){
                            $this->request->data="";
                            $this->request->data['OrderOffer']['order_id']= $last_id;
                            $this->request->data['OrderOffer']['order_item_id'] = $order_item_id;
                            $this->request->data['OrderOffer']['offer_id']=$storeOffer['offer_id'];
                            $this->request->data['OrderOffer']['offered_item_id']=$storeOffer['offered_item_id'];
                            $this->request->data['OrderOffer']['offered_size_id']=$storeOffer['offered_size_id'];
                            $this->request->data['OrderOffer']['quantity']= $storeOffer['quantity'];
                            $this->request->data['OrderOffer']['store_id']=$this->Session->read('store_id');
                            $this->request->data['OrderOffer']['merchant_id']=$this->Session->read('merchant_id');
                            $this->loadModel('OrderOffer');
                            $this->OrderOffer->create();
                            
                            $this->OrderOffer->saveOfferOrder($this->request->data);  //Save the data in OrderOffer table 
                            
                        }
                    }
                    
                    
                    
                    
                    if(isset($result['Item']['default_topping'])){
                        //echo "hi";die;
                        foreach($result['Item']['default_topping'] as $notpaidTopping){
                            //echo "hi";die;
                            $this->request->data="";
                            $this->request->data['OrderTopping']['order_id']=$last_id;
                            $this->request->data['OrderTopping']['order_item_id'] = $order_item_id;
                            $this->request->data['OrderTopping']['topping_id']=$notpaidTopping;
                            $this->request->data['OrderTopping']['topType']="defaultTop";
                            $this->request->data['OrderTopping']['store_id']=$this->Session->read('store_id');
                            $this->request->data['OrderTopping']['merchant_id']=$this->Session->read('merchant_id');
                             //print_r($this->request->data);die;
                            $this->loadModel('OrderTopping');
                            $this->OrderTopping->create();
                            
                            $this->OrderTopping->saveTopping($this->request->data);  //Save the data in ordertopping table 
                            
                        }
                    }
                    
                    if(isset($result['Item']['paid_topping'])){
                        unset($this->request->data['OrderTopping']);
                         $this->request->data="";
                        
                        foreach($result['Item']['paid_topping'] as $paidTopping){
                           
                            $this->request->data['OrderTopping']['order_id']=$last_id;
                            $this->request->data['OrderTopping']['order_item_id'] = $order_item_id;
                            $this->request->data['OrderTopping']['topping_id']=$paidTopping;
                            $this->request->data['OrderTopping']['store_id']=$this->Session->read('store_id');
                            $this->request->data['OrderTopping']['merchant_id']=$this->Session->read('merchant_id');
                            $this->loadModel('OrderTopping');
                            $this->OrderTopping->create();
                            $sucessDefaultTopping=$this->OrderTopping->saveTopping($this->request->data['OrderTopping']);
                            
                        
                        }
                    }
                    
                }
       
          //
       
         $this->Order->bindModel(
                  array('belongsTo' => array(
                          'OrderPayment' => array(
                              'className' => 'OrderPayment',
                              'foreignKey' => 'payment_id',
                              'fields'=>array('id','transection_id','amount'),
                              'conditions'=>array('Order.is_active'=>1,'Order.is_deleted'=>0)
                          )
                      )
               ));
         $encrypted_storeId=$this->Encryption->encode($this->Session->read('store_id'));
                  $encrypted_merchantId=$this->Encryption->encode($this->Session->read('merchant_id'));
                  $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
                  $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
                  $orderId=$last_id;
         $result_order=$this->Order->getfirstOrder($decrypt_merchantId,$decrypt_storeId,$orderId);
          
          
         
         if($result_order && $paymemt_type==1){
                  $this->loadModel('Store');
                  $storeEmail=$this->Store->fetchStoreDetail($decrypt_storeId);
                  $encrypted_storeId=$this->Encryption->encode($this->Session->read('store_id'));
                  $encrypted_merchantId=$this->Encryption->encode($this->Session->read('merchant_id'));
                  $template_type=ORDER_RECEIPT;
                  $this->loadModel('EmailTemplate');
                    if(AuthComponent::User()){
                        $user_email=AuthComponent::User('email');
                        $fullName=AuthComponent::User('fname');
                    }else{
                        $userid = '';
                        $this->loadModel('DeliveryAddress');
                        $delivery_address_id = $this->Session->read('Order.delivery_address_id');
                        $delivery_address=$this->DeliveryAddress->fetchAddress($delivery_address_id,$userid,$decrypt_storeId);
                        
                        $user_email= $delivery_address['DeliveryAddress']['email'];
                        $fullName = $delivery_address['DeliveryAddress']['name_on_bell'];
                    }
                  
                  
                  $emailSuccess=$this->EmailTemplate->storePaymentTemplates($decrypt_storeId,$decrypt_merchantId,$template_type);
                  if($emailSuccess){
                     
                  
                  
                   $emailData = $emailSuccess['EmailTemplate']['template_message'];
                  $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);
                  $emailData = str_replace('{ORDER_ID}',$result_order['Order']['order_number'], $emailData);
                  $emailData = str_replace('{TOTAL}',"$".$result_order['OrderPayment']['amount'], $emailData);
                  $emailData = str_replace('{TRANSACTION_ID}',$result_order['OrderPayment']['transection_id'], $emailData);
                  $emailData = str_replace('{STORE_NAME}',$storeEmail['Store']['store_name'], $emailData);
                  $subject = ucwords(str_replace('_', ' ', $emailSuccess['EmailTemplate']['template_subject']));
                  $this->Email->to = $user_email;
                  $this->Email->subject =$subject;
                  $this->Email->from =$storeEmail['Store']['email_id'];
                  $this->set('data', $emailData);
                  $this->Email->template ='template';
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
          

            
            
         }

                          
                 
                 
          
          //$this->redirect(array('controller'=>'users','action'=>'customerDashboard',$encrypted_storeId,$encrypted_merchantId));
            return 1;
           
           }else{
            return 0;
           }
         
          
      }/*else{
        
      }*/
      
   /*------------------------------------------------
   Function name:sucess()
   Description:It will provide the success of order 
   created:22/7/2015
  -----------------------------------------------------*/
   
   
   
   public function success($data=null){
        $this->Session->delete('Order.item');
        $this->Session->delete('Order.choice');
        $this->Session->delete('Cart');
        $this->Session->delete('cart');
        if($this->Session->read('Coupon')){
            $this->loadModel('Coupon');
            $data['Coupon']['id'] = $this->Session->read('Coupon.Coupon.id');
            $data['Coupon']['used_count'] = $this->Session->read('Coupon.Coupon.used_count')+1;
            $this->Coupon->saveCoupon($data);
        }
        $this->Session->delete('Coupon');
        $this->Session->delete('Discount');
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Session->read('store_id');
        $decrypt_merchantId=$this->Session->read('merchant_id');
        $encrypted_storeId=$this->Encryption->encode($decrypt_storeId); // Encrypted Store Id
        $encrypted_merchantId=$this->Encryption->encode($decrypt_merchantId);// Encrypted Merchant Id
        $this->set(compact('encrypted_storeId','encrypted_merchantId')); 
   }
   
   
    /*------------------------------------------------
   Function name:status()
   Description:It will provide the unsuccess of the error
   created:22/7/2015
  -----------------------------------------------------*/
   
   
   
   public function status($data=null){
      $this->layout="customer_dashboard";
      $decrypt_storeId=$this->Session->read('store_id');
      $decrypt_merchantId=$this->Session->read('merchant_id');
      $encrypted_storeId=$this->Encryption->encode($decrypt_storeId); // Encrypted Store Id
      $encrypted_merchantId=$this->Encryption->encode($decrypt_merchantId);// Encrypted Merchant Id
      $this->set(compact('encrypted_storeId','encrypted_merchantId'));
      
      
      
      
      
   }
     
    /*------------------------------------------------
   Function name:paymentList()
   Description:Display the list of transaction
   created:20/08/2015
  -----------------------------------------------------*/
  
    public function paymentList($clearAction=null){
      $this->layout="admin_dashboard";
      $storeID=$this->Session->read('store_id');
      $merchantId= $this->Session->read('merchant_id');
      $this->loadModel('OrderPayment');
      $criteria = "OrderPayment.store_id =$storeID AND OrderPayment.is_deleted=0 AND OrderPayment.merchant_id=$merchantId";
        if($this->Session->read('TransactionSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('TransactionSearchData'),true);
      }else{
            $this->Session->delete('TransactionSearchData');
      }
      if (!empty($this->request->data)) {
             $this->Session->write('TransactionSearchData',json_encode($this->request->data));
         if($this->request->data['Payment']['is_active']!=''){
              $active = trim($this->request->data['Payment']['is_active']);
              $criteria .= " AND (OrderPayment.payment_status =$active)";
              echo $criteria;die;
          }
          
          
      }    
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('OrderPayment.created'=>'DESC'));
      $transactionDetail=$this->paginate('OrderPayment');
      $this->set('list',$transactionDetail);

      
   }
   
   
}
?>
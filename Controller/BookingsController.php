<?php
App::uses('AppController', 'Controller');
class BookingsController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','Dateform');
   public $uses=array('Order','Item','ItemPrice','ItemType','Size','OrderItem','StoreReview','Favorite','Topping','OrderTopping','Booking');
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
             parent::beforeFilter();   
            
   }
   
   /*------------------------------------------------
      Function name:index()
      Description:List of Dine In booking order
      created:17/8/2015
     -----------------------------------------------------*/  
   public function index($clearAction=null){
      $this->layout="admin_dashboard";       
      $storeID=$this->Session->read('store_id');
      $value = "";      
      $criteria="Booking.store_id =$storeID AND Booking.is_deleted=0 AND User.role_id=4";
              
      if($this->Session->read('BookingSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('BookingSearchData'),true);
      }else{
            $this->Session->delete('BookingSearchData');
      }
      if (!empty($this->request->data)) {
         $this->Session->write('BookingSearchData',json_encode($this->request->data));
         
          if (!empty($this->request->data['Order']['keyword'])) {
             $value = trim($this->request->data['Order']['keyword']);
          $criteria .= " AND (User.fname LIKE '%" . $value . "%' OR User.lname LIKE '%" . $value . "%' OR Booking.id LIKE '%" . $value . "%')";
         
         }
        
        if(!empty($this->request->data['Booking']['is_replied'])){
         if($this->request->data['Booking']['is_replied']==1){
            $repliedID = trim($this->request->data['Booking']['is_replied']);
         }
            if($this->request->data['Booking']['is_replied']==2){
            $repliedID = 0;
         }
          $criteria .= " AND (Booking.is_replied =$repliedID)"; 
        }     
         if(!empty($this->request->data['OrderStatus']['id'])){
            $status = trim($this->request->data['OrderStatus']['id']);
          $criteria .= " AND (Booking.booking_status_id =$status)";
        
          }
          
         }

      $this->Booking->bindModel(
               array(
                 'belongsTo'=>array(
                    'User' =>array(
                     'className' => 'User',
                      'foreignKey' => 'user_id'
                   ),'BookingStatuse' =>array(
                     'className' => 'BookingStatuse',
                      'foreignKey' => 'booking_status_id'
                   )
                    
                  ),
                 )
               ,false
             ); 
         $this->paginate= array('recursive'=>2,'conditions'=>array($criteria),'order'=>array('Booking.created'=> 'DESC'));
         $orderdetail=$this->paginate('Booking');
         $this->set('list',$orderdetail);
         $this->loadModel('BookingStatus');
         $status=$this->BookingStatus->statusList($storeID);
         $this->set('statusList',$status);
         $this->set('keyword', $value);
     
   }
   
   /*------------------------------------------------
      Function name:manageBooking()
      Description:Send the notification to customer of Dine In booking status
      created:17/8/2015
     -----------------------------------------------------*/  
   
   public function manageBooking($EncryptOrderID=null){
      $this->layout="admin_dashboard";       
      $storeId=$this->Session->read('store_id');
      $merchantId=$this->Session->read('merchant_id');        
      $roleId = 4;	
      $orderId=$this->Encryption->decode($EncryptOrderID);    
       $this->loadModel('Store');
      $storeEmail=$this->Store->fetchStoreDetail($storeId);
      $criteria="Booking.store_id =$storeId AND Booking.is_deleted=0 AND Booking.id= $orderId";     
       $this->Booking->bindModel(
               array(
                 'belongsTo'=>array(
                    'User' =>array(
                     'className' => 'User',
                      'foreignKey' => 'user_id'
                   ),'BookingStatus' =>array(
                     'className' => 'BookingStatus',
                      'foreignKey' => 'booking_status_id'
                   )                   
                  ),
                 )
               ,false
             );
     
       if($this->request->data){            
         $fullName=$this->request->data['Data']['name'];
         $order=$this->request->data['Data']['ordercode'];
         $st = $this->request->data['Data']['status'];
            switch ($st) {
                 case "1":
                     $status = 'Pending';
                     break;
                 case "2":
                     $status = 'Available';
                     break;
                 case "3":
                     $status = 'Not Available';
                     break;
                  case "4":
                     $status = 'Cancel';
                     break;
                 default:
                    $status = 'Booked';
            }
                    $template_type= 'booking_status';
                  $this->loadModel('EmailTemplate');
                  $emailSuccess=$this->EmailTemplate->storeTemplates($roleId,$storeId,$merchantId,$template_type);
                 if($emailSuccess){
                 $emailData = $emailSuccess['EmailTemplate']['template_message'];
                 $smsData = $emailSuccess['EmailTemplate']['sms_template'];
                 $subject=$emailSuccess['EmailTemplate']['template_subject'];
                             }
                  if($this->request->data['Data']['emailnotify'] == 1){
                 
                  $comment = $this->request->data['Data']['comment'];
		  $emailData = str_replace('{FULL_NAME}',$fullName, $emailData);		 
		  $emailData = str_replace('{STATUS}',$status, $emailData);
                  $emailData = str_replace('{COMMENT}',$comment, $emailData);
		  $subject = ucwords(str_replace('_', ' ', $subject));
		  $this->Email->to = $this->request->data['Data']['to'];
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
                     
                   
                 if($this->request->data['Data']['smsnotify'] == 1){
                     /**************sms gateway data**************/
                  $smsData = str_replace('{username}',$fullName, $smsData);
                  $smsData = str_replace('{Ordernumber}',$order, $smsData);
		  $smsData = str_replace('{OrderStatus}',$status, $smsData);
                        /*************end sms gateway data***********/
                  $message=$smsData;
                  $tonumber="+91".$this->request->data['Data']['phone'];
                  $this->Common->sendSmsNotification($tonumber,$message);
                 }
                 
                  $this->Booking->id=$this->request->data['Data']['id'];
                  $bookingStatus = $this->request->data['Data']['status'];
                  $this->Booking->saveField("booking_status_id",$bookingStatus);          
                  $this->Booking->id=$this->request->data['Data']['id'];
                  $replied = 1;
                  $this->Booking->saveField("is_replied",$replied);                
                  $this->Session->setFlash(_("Message send successfully."));
                  $this->redirect(array('action'=>'index','controller'=>'bookings'));
       }
       $this->paginate= array('recursive'=>2,'conditions'=>array($criteria),'order'=>array('Booking.created'=> 'DESC'));
      $orderdetail=$this->paginate('Booking');
      $this->set('list',$orderdetail);
      $this->loadModel('BookingStatus');
      $status=$this->BookingStatus->statusList($storeId);
      $this->set('statusList',$status);
   }
   
   function PrintForKitchen(){
      //$handle = printer_open("HP Deskjet 930c");
      //var_dump($handle);
      //die;
      //$this->Common->PrintReceipt();
      $this->Common->printdemo();
      
   }
   
   function checkPrinterConnection(){
      
   }
     
   function testPrinting(){
      
   }
             
}

?>
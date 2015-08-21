<?php
App::uses('AppController', 'Controller');
class CustomersController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','DateformHelper');
   public $uses='User';
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
      parent::beforeFilter();
      $adminfunctions=array('index','activateCustomer','deleteCustomer','editCustomer','orderHistory','ajaxRequest');
      if(in_array($this->params['action'],$adminfunctions)){
         if(!$this->Common->checkPermissionByaction($this->params['controller'])){
           $this->Session->setFlash(__("Permission Denied"));
           $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
      }
            
   }
   
   
   /*------------------------------------------------
      Function name:index()
      Description:Display the list of Customer
      created:10/8/2015
     -----------------------------------------------------*/  
   public function index($clearAction=null){
      $this->layout="admin_dashboard";       
      $storeID=$this->Session->read('store_id');
      $value = "";      
      $criteria = "User.store_id =$storeID AND User.role_id=4 AND User.is_deleted=0";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
      if($this->Session->read('CustomerSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('CustomerSearchData'),true);
      }else{
            $this->Session->delete('CustomerSearchData');
      }         
      
      if (!empty($this->request->data)) {
          $this->Session->write('CustomerSearchData',json_encode($this->request->data));
          if (!empty($this->request->data['User']['keyword'])) {
              $value = trim($this->request->data['User']['keyword']);
              $criteria .= " AND (User.fname LIKE '%" . $value . "%' OR User.email LIKE '%" . $value . "%' OR User.lname LIKE '%" . $value . "%' OR User.phone LIKE '%" . $value . "%')";
          }
          
          
          if($this->request->data['User']['is_active']!=''){
              $active = trim($this->request->data['User']['is_active']);
              $criteria .= " AND (User.is_active =$active)";
          }
          
          if($this->request->data['User']['from']!='' && $this->request->data['User']['to']!=''){
              $stratdate = $this->Dateform->formatDate($this->request->data['User']['from']);
              $enddate = $this->Dateform->formatDate($this->request->data['User']['to']);
              // $criteria .= " AND (User.created BETWEEN ? AND ?) =" array($stratdate,$enddate);
               
               $criteria.= " AND (User.created BETWEEN '".$stratdate."' AND '".$enddate."')";
          }
      }          
      
      
      
      $this->paginate= array('limit'=>10,'conditions'=>array($criteria),'order'=>array('User.created'=>'DESC'),'recursive'=>-1);
      $customerdetail=$this->paginate('User');
      //pr($customerdetail);die;
      $this->set('list',$customerdetail);
      $this->set('keyword', $value);
   }
   
   /*------------------------------------------------
      Function name:activateCustomer()
      Description:Active/deactive Customer
      created:10/8/2015
     -----------------------------------------------------*/  
   public function activateCustomer($EncryptCustomerID=null,$status=0){
      
      $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['User']['store_id']=$this->Session->read('store_id');
            $data['User']['id']=$this->Encryption->decode($EncryptCustomerID);
            $data['User']['is_active']=$status;
            if($this->User->saveUserInfo($data)){
               if($status){
                  $SuccessMsg="User Activated";
               }else{
                  $SuccessMsg="User Deactivated and User will not get Display in the List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'customers', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'customers', 'action' => 'index'));
            }
   }
   
   /*------------------------------------------------
      Function name:deleteCustomer()
      Description:Delete Customer
      created:10/8/2015
     -----------------------------------------------------*/  
      public function deleteCustomer($EncryptCustomerID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['User']['store_id']=$this->Session->read('store_id');
            $data['User']['id']=$this->Encryption->decode($EncryptCustomerID);
            $data['User']['is_deleted']=1;
            if($this->User->saveUserInfo($data)){
               $this->Session->setFlash(__("User deleted"));
               $this->redirect(array('controller' => 'customers', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'customers', 'action' => 'index'));
            }
      }
      
      /*------------------------------------------------
   Function name:editCustomer()
   Description:Registration  Form for the  End customer
   created:10/8/2015
  -----------------------------------------------------*/
   public function editCustomer($EncryptCustomerID=null){
      
      $this->layout="admin_dashboard";
            $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $data['User']['id']=$this->Encryption->decode($EncryptCustomerID);
             $this->loadModel('User');
               $customerDetail=$this->User->getUserDetail($data['User']['id'], $storeId);
          if($this->request->is('post')){
             
               $email=trim($this->data['User']['email']);
             $isUniqueEmail=$this->User->checkUserUniqueEmail($email,$storeId,$data['User']['id']);
             if($isUniqueEmail){
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
                  //echo $actualDbDate=date("Y-m-d",strtotime($this->request->data['User']['dateOfBirth']));die;  //Not working
                  $actualDbDate=$this->Dateform->formatDate($this->request->data['User']['dateOfBirth']);// calling formatDate function in Appcontroller to format the date (Y-m-d) format 
                  $this->request->data['User']['dateOfBirth']=$actualDbDate;
                  $result=$this->User->saveUserInfo($this->request->data);   // We are calling function written on Model to save data 
                  
                  $this->Session->setFlash(__('Customer details updated successfully'));

                  $this->redirect(array('controller'=>'customers','action'=>'index'));
      }else{
          $this->Session->setFlash(__("Email  Already exists"));
      }}
               $this->request->data=$customerDetail;

  
   }
     
     /*------------------------------------------------
   Function name:orderHistory()
   Description:Display the customer all orders
   created:18/8/2015
  -----------------------------------------------------*/
   public function orderHistory($EncryptCustomerID=null){
            $this->layout="admin_dashboard";
            $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $userId=$this->Encryption->decode($EncryptCustomerID);
             $this->loadModel('Order');
             $this->loadModel('OrderTopping');
             $this->loadModel('OrderItem');
    $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('className' => 'Topping','foreignKey'=>'topping_id','fields'=>array('name')))), false);
        $this->OrderItem->bindModel(array('hasOne'=>array('StoreReview'=>array('fields'=>array('review_rating','is_approved'))),'hasMany'=>array('OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('name')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('size')))), false);
        $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('DeliveryAddress'=>array('fields'=>array('name_on_bell','city','address')),'User'=>array('fields'=>array('fname','lname')),'OrderStatus'=>array('fields'=>array('name')))), false);
            
            $orderDetails = $this->Order->getUserOrderDetail($merchantId,$storeId,$userId);
            if(!empty($orderDetails)){
                $this->set('orderDetail',$orderDetails); 
            }else{
               $this->Session->setFlash(__('Record not Found.'));

                  $this->redirect($this->referer());
            }
          
            
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
<?php
App::uses('AppController', 'Controller');
class KitchensController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','Dateform');
   public $uses=array('Order','Item','ItemPrice','ItemType','Size','OrderItem','StoreReview','Favorite','Topping','OrderTopping');
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
       parent::beforeFilter();
       $adminfunctions=array('index','UpdateOrderStatus','orderDetail');
      if(in_array($this->params['action'],$adminfunctions)){
         if(!$this->Common->checkPermissionByaction($this->params['controller'])){
           $this->Session->setFlash(__("Permission Denied"));
           $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
      }            
   }
   
   /*------------------------------------------------
      Function name:index()
      Description:List order item that is not served to customer
      created:17/8/2015
     -----------------------------------------------------*/  
   public function index($clearAction=null){
      $this->layout="admin_dashboard";       
      $storeID=$this->Session->read('store_id');
      $value = "";      
      $criteria="Order.store_id =$storeID AND Order.is_deleted=0 AND User.role_id=4";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
      //if($this->Session->read('OrderSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
      //      $this->request->data = json_decode($this->Session->read('OrderSearchData'),true);
      //}else{
      //      $this->Session->delete('OrderSearchData');
      //}         
      if($this->Session->read('KitchenSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('KitchenSearchData'),true);
      }else{
            $this->Session->delete('KitchenSearchData');
      }
      if (!empty($this->request->data)) {
        
         $this->Session->write('KitchenSearchData',json_encode($this->request->data));
          
          if (!empty($this->request->data['Order']['keyword'])) {
             $value = trim($this->request->data['Order']['keyword']);
            $criteria .= " AND (Order.order_number LIKE '%" . $value . "%' OR User.fname LIKE '%" . $value . "%' OR User.lname LIKE '%" . $value . "%' OR User.email LIKE '%" . $value . "%' OR DeliveryAddress.phone LIKE '%" . $value . "%')";
          }
         
        if(!empty($this->request->data['OrderStatus']['id'])){
             //echo $this->request->data['OrderStatus']['id'];die;
           $orderStatusID = trim($this->request->data['OrderStatus']['id']);
           $criteria .= " AND (Order.order_status_id =$orderStatusID)"; 
        }
         
          if(!empty($this->request->data['Segment']['id'])){
            $type = trim($this->request->data['Segment']['id']);
            $criteria .= " AND (Order.seqment_id =$type)";
           // echo $criteria;die;
          }
          
          }else{
            $criteria .= " AND (Order.order_status_id=1 OR Order.order_status_id=2 OR Order.order_status_id=3 OR Order.order_status_id=4 OR Order.order_status_id=6)";
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
      Function name: UpdateOrderStatus()
      Description: Update the order status
      created:17/8/2015
     -----------------------------------------------------*/  
      public function UpdateOrderStatus(){
           $this->autoRender=false;
            $this->layout="admin_dashboard";
            if(!empty($this->request->data['Order']['id'])){            
           // pr($this->request->data);die;
               $filter_array = array_filter($this->request->data['Order']['id']);
           foreach($filter_array as $k=>$orderId){
           $this->Order->id=$orderId;
           $this->Order->saveField("order_status_id",$this->request->data['Order']['order_status_id']);
           
           
                    }
               
         }
          if(!empty($this->request->data['Orders']['id'])){ 
           $this->Order->id=$this->request->data['Orders']['id'];
           $this->Order->saveField("order_status_id",$this->request->data['Order']['order_status_id']);
           
           
                  
         }
          $this->Session->setFlash(__("Order status updated successfully."));
           $this->redirect(array('action'=>'index','controller'=>'kitchens'));
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
        $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id'))),'belongsTo'=>array('DeliveryAddress'=>array('fields'=>array('name_on_bell','city','address')),'OrderStatus'=>array('fields'=>array('name')))), false);
            
            $orderDetails = $this->Order->getSingleOrderDetail($merchantId,$storeID,$orderId);
         //pr($orderDetails);die;
            $this->set('orderDetail',$orderDetails);
            $this->loadModel('OrderStatus');
            $statusList=$this->OrderStatus->OrderStatusList($storeID);
            $this->set('statusList',$statusList);
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
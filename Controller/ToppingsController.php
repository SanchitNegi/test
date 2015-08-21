<?php
App::uses('AppController', 'Controller');
class ToppingsController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses=array('Topping','Item','ItemPrice','ItemType','Size','Category','ItemDefaultTopping');
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
      parent::beforeFilter();
      $adminfunctions=array('index','addTopping','editTopping','activateTopping','deleteTopping');
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
      $merchant_id= $this->Session->read('merchant_id');
      $value = "";      
      $criteria = "Topping.store_id =$storeID AND Topping.is_deleted=0";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
      if($this->Session->read('ToppingSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('ToppingSearchData'),true);
      }else{
            $this->Session->delete('ToppingSearchData');
      }         
      
      if (!empty($this->request->data)) {
          $this->Session->write('ToppingSearchData',json_encode($this->request->data));
          if (!empty($this->request->data['Topping']['keyword'])) {
              $value = trim($this->request->data['Topping']['keyword']);
              $criteria .= " AND (Topping.name LIKE '%" . $value . "%' OR Item.name LIKE '%" . $value . "%')";
          }
          if(!empty($this->request->data['Topping']['item_id'])){
              $ItemID = trim($this->request->data['Topping']['item_id']);
              $criteria .= " AND (Topping.item_id =$ItemID)";
          }
          if($this->request->data['Topping']['is_active']!=''){
              $active = trim($this->request->data['Topping']['is_active']);
              $criteria .= " AND (Topping.is_active =$active)";
          }
          
          //Check if set or unset topping ids are in request
          if(isset($this->request->data['Topping']['id']) && $this->request->data['Topping']['id'] && $this->request->data['Topping']['item_id'] && isset($this->request->data['Topping']['item_id'])){
            $ToppingId=$this->request->data['Topping']['id'];
            
            if($ItemID){
               if(isset($this->request->data['set'])){            // Set Default Toppings
                  if($this->ItemDefaultTopping->deleteallDefaultTopping($ItemID,null)){ 
                       foreach($ToppingId as $key => $topid){
                           if($topid){
                              $deafulttoppingId=$this->ItemDefaultTopping->defaultToppingExits($topid);
                              if($deafulttoppingId){
                                 $defaulttoppingdata['id']=$deafulttoppingId['ItemDefaultTopping']['id'];
                              }else{
                                  $defaulttoppingdata['id']='';
                              }
                              $defaulttoppingdata['topping_id']=$topid;
                              $defaulttoppingdata['store_id']=$storeID;
                              $defaulttoppingdata['merchant_id']=$merchant_id;
                              $defaulttoppingdata['item_id']=$ItemID;
                              $defaulttoppingdata['is_deleted']=0;                           
                              $this->ItemDefaultTopping->saveDefaultTopping($defaulttoppingdata);
                           }
                       }
                       $this->Session->setFlash(__("Add-ons are successfully assigned as default to Item"));
                  }
               }
                  
               if(isset($this->request->data['unset'])){         // unset Toppings
                    foreach($ToppingId as $key => $topid){
                        if($topid){
                           //$this->ItemDefaultTopping->deleteallDefaultTopping($ItemID,$topid);
                           $deafulttoppingId=$this->ItemDefaultTopping->defaultToppingExits($topid);
                           if($deafulttoppingId){
                              $defaulttoppingdata['id']=$deafulttoppingId['ItemDefaultTopping']['id'];
                              $defaulttoppingdata['topping_id']=$topid;
                              $defaulttoppingdata['store_id']=$storeID;
                              $defaulttoppingdata['merchant_id']=$merchant_id;
                              $defaulttoppingdata['item_id']=$ItemID;
                              $defaulttoppingdata['is_deleted']=1;                           
                              $this->ItemDefaultTopping->saveDefaultTopping($defaulttoppingdata);
                           }
                        }
                    }
                   $this->Session->setFlash(__("Default Add-ons has been removed from Item"));
               }               
            }else{
               $this->Session->setFlash(__("Please select Item"));
            }
          }
      }          
      
      
      $this->Topping->bindModel(
         array(
           'belongsTo'=>array(
               'Item' =>array(
                'className' => 'Item',
                'foreignKey' => 'item_id',
                'conditions' => array('Item.is_deleted' =>0,'Item.is_active' =>1),
                'fields'=>array('id','name')
              )         
           ),
           'hasMany'=>array(
              'ItemDefaultTopping'=>array(
                'className' => 'ItemDefaultTopping',
                'foreignKey' => 'topping_id',
                'conditions' => array('ItemDefaultTopping.is_deleted' =>0,'ItemDefaultTopping.is_active' =>1),
                'fields'=>array('id','topping_id','item_id')
              )
           )
         ),false
       );
      //pr($this->request->data);
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('Topping.created'=>'DESC'));
      $toppingdetail=$this->paginate('Topping');
      //pr($toppingdetail);die;
      $this->set('list',$toppingdetail);
      $this->loadModel('Category');
      $itemList=$this->Item->getallItemsByStore($storeID);
      $this->set('itemList',$itemList);
      //$categoryList=$this->Category->getCategoryList($storeID);
      //$this->set('categoryList',$categoryList);
      $this->set('keyword', $value);
   }
   
   /*------------------------------------------------
      Function name:addTopping()
      Description:Add Item Toppings
      created:5/8/2015
     -----------------------------------------------------*/  
   public function addTopping(){
      $this->layout="admin_dashboard";  
      $storeID=$this->Session->read('store_id');
      $merchant_id= $this->Session->read('merchant_id');
      
      if($this->data){
         $itemIds=$this->data['Topping']['item_id'];
         $itemCount=count($itemIds);
         if($itemCount){
            $toppingdata=array();
            $priceArray=explode(',',$this->data['Topping']['price']);
            if(!$priceArray[0]){
               $priceArray[0]=0;
            }
            $toppingName=trim($this->data['Topping']['name']);
            $topping=0;
            $successToppingName='';
            $failedToppingName='';
            foreach($itemIds as $key => $itemId){
               if($this->Topping->checkToppingUniqueName($toppingName,$storeID,$itemId)){               
                  if(!isset($priceArray[$key])){
                     $priceArray[$key]=$priceArray[0];
                  }
                  $toppingdata['name']=$toppingName;
                  $toppingdata['item_id']=$itemId;
                  $toppingdata['is_active']=$this->data['Topping']['is_active'];
                  $toppingdata['price']=$priceArray[$key];
                  $toppingdata['store_id']=$storeID;
                  $toppingdata['merchant_id']=$merchant_id;
                  $this->Topping->create();
                  $topping=$this->Topping->saveTopping($toppingdata);
                  $itemNamesuccess=$this->Item->getItemName($itemId,$storeID);
                  if($successToppingName==''){
                     $successToppingName.=$itemNamesuccess['Item']['name'];
                  }else{
                     $successToppingName.=','.$itemNamesuccess['Item']['name'];
                  }                  
               }else{
                  $itemNamefailed=$this->Item->getItemName($itemId,$storeID);
                  if($failedToppingName==''){
                     $failedToppingName.=$itemNamefailed['Item']['name'];
                  }else{
                     $failedToppingName.=','.$itemNamefailed['Item']['name'];
                  }  
               }
            }
            $message='';
            if($successToppingName){
               $message.="Add-on for Item ".$successToppingName." has been successfully created";
            }
            
            if($failedToppingName){
               $message.="<br> Add-on for Item ".$failedToppingName." already exists";
            }
            
            if($message){
               $this->Session->setFlash(__($message));
               $this->redirect(array('controller' => 'toppings', 'action' => 'index','clear'));
            }else{               
               $this->Session->setFlash(__("Some Problem occured"));
            }
         }
      }    
      
      $itempost=0;
      $itemList='';
      if(isset($this->data['Topping']['item_id'])){         
         $itempost=1;
      }
      if(isset($this->data['Topping']['item_id'])){  
         $itemList=$this->Item->getItemsByCategory($this->data['Category']['id'],$storeID);
      }
      $this->set('itemList',$itemList);
      $categoryList=$this->Category->getCategoryListHasTopping($storeID);
      $this->set('categoryList',$categoryList);
      $this->set('itempost',$itempost);
   }

   
   
   /*------------------------------------------------
      Function name:editTopping()
      Description:Edit Item Toppings
      created:5/8/2015
     -----------------------------------------------------*/  
   public function editTopping($EncryptedToppingID=null){
      $this->layout="admin_dashboard";
      $merchant_id= $this->Session->read('merchant_id');
      $storeId=$this->Session->read('store_id');
      $toppingId=$this->Encryption->decode($EncryptedToppingID);
      
      if($this->data){
         $toppingName=trim($this->data['Topping']['name']);
         $itemId=trim($this->data['Topping']['item_id']);
         $toppingId=trim($this->data['Topping']['id']);         
         if($this->Topping->checkToppingUniqueName($toppingName,$storeId,$itemId,$toppingId)){
            $toppingdata['name']=$toppingName;
            $toppingdata['item_id']=$itemId;
            //$toppingdata['is_active']=$this->data['Topping']['is_active'];
            if($this->data['Topping']['is_active']){
               $toppingdata['is_active']=1;
            }else{
               $toppingdata['is_active']=0;
            }
            $priceArray=explode(',',trim($this->data['Topping']['price']));
            if(!$priceArray[0]){
               $priceArray[0]=0;
            }
            $toppingdata['price']=$priceArray[0];
            $toppingdata['store_id']=$storeId;
            $toppingdata['merchant_id']=$merchant_id;
            $toppingdata['id']=$toppingId;
            $topping=$this->Topping->saveTopping($toppingdata);            
            $this->Session->setFlash(__("Add-on Details Updated"));
            $this->redirect(array('controller' => 'Toppings', 'action' => 'index'));
         }else{
            $this->Session->setFlash(__("Add-on Name Already Exists for Item"));
         }        
      }
      //echo $toppingId.','.$storeId;
      $toppingsDetails=$this->Topping->fetchToppingDetails($toppingId,$storeId);//pr($toppingsDetails);die;
      $categoryDetails=$this->Item->getcategoryByitemID($toppingsDetails['Topping']['item_id'],$storeId);
      $this->request->data['Category']['id']=$categoryDetails['Item']['category_id'];
      if(isset($this->data['Topping']['name']) && $this->data['Topping']['name']){
          $toppingsDetails['Topping']['name']=$this->data['Topping']['name'];
      }
      $this->request->data['Topping']=$toppingsDetails['Topping'];
      $itempost=0;
      if(isset($this->data['Topping']['item_id'])){         
         $itempost=1;
      }
      if(isset($this->data['Topping']['item_id'])){  
         $itemList=$this->Item->getItemsByCategory($this->data['Category']['id'],$storeId);
      }
      $categoryList=$this->Category->getCategoryListHasTopping($storeId);
      $this->set('categoryList',$categoryList);
      $this->set('itemList',$itemList);
      $this->set('itempost',$itempost);
      //pr($this->request->data);//die;
   }
   
   
   /*------------------------------------------------
      Function name:activateTopping()
      Description:Active/deactive Topping
      created:5/8/2015
     -----------------------------------------------------*/  
      public function activateTopping($EncryptedtoppingID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";
            $toppingid=$this->Encryption->decode($EncryptedtoppingID);
            $data['Topping']['store_id']=$this->Session->read('store_id');
            $data['Topping']['id']=$toppingid;
            $data['Topping']['is_active']=$status;
            if($this->Topping->saveTopping($data)){
               if($status){
                  $SuccessMsg="Add-on Activated";
               }else{
                  $SuccessMsg="Add-on Deactivated and Add-on will not available at Add-on List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'Toppings', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Toppings', 'action' => 'index'));
            }
      }
      
   /*------------------------------------------------
      Function name:deleteTopping()
      Description:Delete Topping
      created:5/8/2015
     -----------------------------------------------------*/  
      public function deleteTopping($EncryptedtoppingID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";
            $toppingid=$this->Encryption->decode($EncryptedtoppingID);
            //pr($toppingid);die;
            $data['Topping']['store_id']=$this->Session->read('store_id');
            $data['Topping']['id']=$toppingid;
            $data['Topping']['is_deleted']=1;
            if($this->Topping->saveTopping($data)){
               $this->Session->setFlash(__("Add-on deleted"));
               $this->redirect(array('controller' => 'Toppings', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Toppings', 'action' => 'index'));
            }
      }
   
}

?>
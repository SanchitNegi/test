<?php
App::uses('AppController', 'Controller');
class ItemsController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses=array('Item','ItemPrice','ItemType','Size');
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
      parent::beforeFilter();
      $adminfunctions=array('addMenuItem','editMenuItem','index','activateItem','deleteItemPhoto');
      if(in_array($this->params['action'],$adminfunctions)){
         if(!$this->Common->checkPermissionByaction($this->params['controller'])){
           $this->Session->setFlash(__("Permission Denied"));
           $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
         }
      }
            
   }
   
   
   /*------------------------------------------------
      Function name:addMenuItem()
      Description:Add Menu Item
      created:22/7/2015
     -----------------------------------------------------*/  
   public function addMenuItem(){
      $this->layout="admin_dashboard";              
      $storeId=$this->Session->read('store_id');
      $roleId=AuthComponent::User('role_id');
      //$merchant_id=AuthComponent::User('merchant_id');
      $merchant_id= $this->Session->read('merchant_id');
      $this->set(compact('roleId'));
      $this->set(compact('storeId'));      
      if($this->data){
         
         $itemName=trim($this->data['Item']['name']);
         $isUniqueName=$this->Item->checkItemUniqueName($itemName,$storeId);         
         if($isUniqueName){
            $itemPrice=array();
            $itemType=array();
            $itemdata=array();                        
            $itemdata['name']=$this->data['Item']['name'];
            $itemdata['category_id']=$this->data['Item']['category_id'];
            $itemdata['store_id']=$storeId;
            $itemdata['merchant_id']=$this->Item->bindModel(
                array(
                  'belongsTo'=>array(
                      'Category' =>array(
                       'className' => 'Category',
                       'foreignKey' => 'category_id',
                       'conditions' => array('Category.is_deleted' =>0,'Category.is_active' =>1),
                       'fields'=>array('id','name')
                   )         
                  )
                ),false
              );$merchant_id;
            if($this->data['Item']['is_deliverable']){
               $itemdata['is_deliverable']=1;
            }else{
               $itemdata['is_deliverable']=0;                        
            }
            $itemdata['description']=$this->data['Item']['description'];
            if($this->data['Item']['is_seasonal_item']){
               $itemdata['is_seasonal_item']=$this->data['Item']['is_seasonal_item'];
               $startDate=$this->Dateform->formatDate($this->request->data['Item']['start_date']);
               $endDate=$this->Dateform->formatDate($this->request->data['Item']['end_date']);
               $itemdata['start_date']=$startDate;
               $itemdata['end_date']=$endDate;
            }
            $response=$this->Common->uploadMenuItemImages($this->data['Item']['imgcat'],'/MenuItem-Image/',$storeId);
            //pr($response);die;
            if(!$response['status']){
               $this->Session->setFlash(__($response['errmsg']));               
            }else{
               //Item Data
               $itemdata['image']=$response['imagename'];
               $this->Item->create();
               $this->Item->saveItem($itemdata);
               $itemID=$this->Item->getLastInsertId();               
               if($itemID){
                  //PriceData
                  if($this->data['Size']['id']){
                     $priceArray=explode(',',$this->data['ItemPrice']['price']);
                     if(!$priceArray[0]){
                        $priceArray[0]=0;
                     }
                     foreach($this->data['Size']['id'] as $key => $sizeid){                        
                        if(!isset($priceArray[$key])){
                           $priceArray[$key]=$priceArray[0];
                        }
                        $itemPrice['store_id']=$storeId;
                        $itemPrice['size_id']=$sizeid;
                        $itemPrice['item_id']=$itemID;
                        $itemPrice['price']=$priceArray[$key];
                        $itemPrice['merchant_id']=$merchant_id;                       
                        $this->ItemPrice->create();
                        $this->ItemPrice->saveItemPrice($itemPrice);
                     }
                  }else{
                     $priceArray=explode(',',$this->data['ItemPrice']['price']);
                     if(!$priceArray[0]){
                        $priceArray[0]=0;
                     }
                     $itemPrice['store_id']=$storeId;
                     $itemPrice['item_id']=$itemID;
                     $itemPrice['merchant_id']=$merchant_id;
                     $itemPrice['price']=$priceArray[0];
                     $this->ItemPrice->create();
                     $this->ItemPrice->saveItemPrice($itemPrice);
                  }
                                      
                  //TypeData
                  if($this->data['Type']['id']){                  
                     foreach($this->data['Type']['id'] as $key => $typeID){                        
                        $itemType['item_id']=$itemID;
                        $itemType['store_id']=$storeId;
                        $itemType['type_id']=$typeID;
                        $itemType['merchant_id']=$merchant_id;
                        $this->ItemType->create();
                        $this->ItemType->saveItemType($itemType);                    
                     }
                  }
                  $this->Session->setFlash(__("Item Successfully Created"));
                  $this->redirect(array('controller' => 'Items', 'action' => 'index'));
               }else{
                  $this->Session->setFlash(__("Item Not created"));
                  //$this->redirect(array('controller' => 'Items', 'action' => 'addMenuItem'));
               }
            }            
         }else{
            $this->Session->setFlash(__("Item name Already exists"));
            //$this->redirect(array('controller' => 'Items', 'action' => 'addMenuItem'));
         }         
      }
      $sizeList='';
      $sizepost=0;
      $typepost=0;
      $seasonalpost=0;
      $this->loadModel('Category');
      if(isset($this->data['Item']['category_id'])){
         $sizeList=$this->Size->getCategorySizes($this->data['Item']['category_id'],$storeId);
         $sizepost=1;
      }
      $this->set('sizeList',$sizeList);
      if(isset($this->data['Type']['id'])){
        $typeInfo= $this->Category->getCategorySizeType($this->data['Item']['category_id'],$storeId); 
        if($typeInfo['Category']['is_sizeonly']==2 || $typeInfo['Category']['is_sizeonly']==3){
            $typepost=1;
        }
      }
      if(isset($this->data['Item']['is_seasonal_item']) && $this->data['Item']['is_seasonal_item']){
         $seasonalpost=1;
      }
       $this->set('typepost',$typepost);
       $this->set('sizepost',$sizepost);
       $this->set('seasonalpost',$seasonalpost);
      
      
      $categoryList=$this->Category->getCategoryList($storeId);
      $this->set('categoryList',$categoryList);
      $this->loadModel('Type');
      $typeList=$this->Type->getTypes($storeId);
      $this->set('typeList',$typeList);
   }
   
    /*------------------------------------------------
      Function name:editMenuItem()
      Description:Update Menu Item
      created:5/8/2015
     -----------------------------------------------------*/  
   public function editMenuItem($EncrypteditemID=null){       
       $this->layout="admin_dashboard";
       $merchant_id= $this->Session->read('merchant_id');
       $storeId=$this->Session->read('store_id');
       $itemId=$this->Encryption->decode($EncrypteditemID);
       if($this->data){
         
         $itemName=trim($this->data['Item']['name']);
         $isUniqueName=$this->Item->checkItemUniqueName($itemName,$storeId,$itemId);
         
         if($isUniqueName){
            $itemPrice=array();
            $itemType=array();
            $itemdata=array();
            $itemdata['id']=$this->data['Item']['id'];
            $itemdata['name']=$this->data['Item']['name'];
            $itemdata['category_id']=$this->data['Item']['category_id'];
            $itemdata['store_id']=$storeId;
            $itemdata['merchant_id']=$merchant_id;
            if($this->data['Item']['is_deliverable']){
               $itemdata['is_deliverable']=1;
            }else{
               $itemdata['is_deliverable']=0;                        
            }
            $itemdata['description']=$this->data['Item']['description'];
            if($this->data['Item']['is_seasonal_item']){
               $itemdata['is_seasonal_item']=$this->data['Item']['is_seasonal_item'];
               $startDate=$this->Dateform->formatDate($this->request->data['Item']['start_date']);
               $endDate=$this->Dateform->formatDate($this->request->data['Item']['end_date']);
               $itemdata['start_date']=$startDate;
               $itemdata['end_date']=$endDate;
            }else{
               $itemdata['is_seasonal_item']=0;
            }
            if($this->data['Item']['imgcat']['error']==0){
                 $response=$this->Common->uploadMenuItemImages($this->data['Item']['imgcat'],'/MenuItem-Image/',$storeId);
            }elseif($this->data['Item']['imgcat']['error']==4){
                 $response['status']=true;
                 $response['imagename']='';
            }
            if(!$response['status']){
               $this->Session->setFlash(__($response['errmsg']));               
            }else{
               //Item Data
               if($response['imagename']){
                 $itemdata['image']=$response['imagename'];
               }              
               $this->Item->saveItem($itemdata);
               //Delete all Item id Realed rows               
               $this->ItemType->deleteallItemType($itemdata['id']);
               $this->ItemPrice->deleteallItemPrice($itemdata['id']);
               //pr($this->data);die;
               $priceArray=explode(',',$this->data['ItemPrice']['price']);
               if(isset($this->data['Size']['id']) && $this->data['Size']['id']){                  
                  foreach($this->data['Size']['id'] as $key => $sizeid){
                     $itemPriceID=$this->ItemPrice->ItemPriceExits($itemdata['id'],$sizeid,$storeId);
                     if(!$priceArray[0]){
                        $priceArray[0]=0;
                     }
                     if(!isset($priceArray[$key])){
                        $priceArray[$key]=$priceArray[0];
                     }
                     if($itemPriceID){
                        $itemPrice['id']=$itemPriceID['ItemPrice']['id'];                        
                     }else{
                        $itemPrice['id']=''; 
                     }  
                        $itemPrice['store_id']=$storeId;
                        $itemPrice['size_id']=$sizeid;
                        $itemPrice['item_id']=$itemdata['id'];
                        $itemPrice['price']=$priceArray[$key];
                        $itemPrice['is_deleted']=0;
                        $itemPrice['merchant_id']=$merchant_id;
                        $this->ItemPrice->saveItemPrice($itemPrice);                     
                  }                  
               }else{               
                  $itemPriceID=$this->ItemPrice->ItemPriceExits($itemdata['id'],'',$storeId);
                  if(!$priceArray[0]){
                        $priceArray[0]=0;
                     }
                  if($itemPriceID){
                     $itemPrice['id']=$itemPriceID['ItemPrice']['id'];                        
                  }else{
                        $itemPrice['id']=''; 
                  }
                  $itemPrice['store_id']=$storeId;
                  $itemPrice['size_id']=0;
                  $itemPrice['item_id']=$itemdata['id'];
                  $itemPrice['is_deleted']=0;
                  $itemPrice['price']=$priceArray[0];
                  $itemPrice['merchant_id']=$merchant_id;
                  $this->ItemPrice->saveItemPrice($itemPrice);
               }
               //TYpe
               if(isset($this->data['Type']['id']) && $this->data['Type']['id']){
                  foreach($this->data['Type']['id'] as $key => $typeid){
                     $itemtypeID=$this->ItemType->ItemTypeExits($itemdata['id'],$typeid,$storeId);
                     if($itemtypeID){
                        $itemType['id']=$itemtypeID['ItemType']['id'];                        
                     }else{
                        $itemType['id']=''; 
                     }
                     $itemType['item_id']=$itemdata['id'];
                     $itemType['store_id']=$storeId;
                     $itemType['type_id']=$typeid;
                     $itemType['is_deleted']=0;
                     $itemType['merchant_id']=$merchant_id;                     
                     $this->ItemType->saveItemType($itemType);                    
                  }
               }
               $this->Session->setFlash(__('Item details updated successfully'));
               $this->redirect(array('controller'=>'items','action'=>'index'));
            }
         } 
         
         
      }
                  
       
       
       $this->Item->bindModel(
            array(
                  'hasMany'=>array(
                      'ItemPrice' =>array(
                        'className' => 'ItemPrice',
                        'foreignKey' => 'item_id',
                        'conditions' => array('ItemPrice.is_deleted' =>0,'ItemPrice.is_active' =>1),
                        'fields'=>array('id','price','size_id'),
                        'order'=>array('id ASC')
                      ),
                      'ItemType'=>array(
                        'className' => 'ItemType',
                        'foreignKey' => 'item_id',
                        'conditions' => array('ItemType.is_deleted' =>0,'ItemType.is_active' =>1),
                        'fields'=>array('id','type_id')
                      )
                  ),
                  'belongsTo'=>array(
                      'Category' =>array(
                       'className' => 'Category',
                       'foreignKey' => 'category_id',
                       'conditions' => array('Category.is_deleted' =>0,'Category.is_active' =>1),
                       'fields'=>array('id','name','is_sizeonly')
                     )         
                  )
            ),false
       );
       $editItemArray=array();
       $itemDetails=$this->Item->fetchItemDetail($itemId,$storeId); //pr($itemDetails);die;
       foreach($itemDetails as $key => $Data){
        
         if($key=='Item'){
            $editItemArray['Item']['id']=$Data['id'];
            $editItemArray['Item']['name']=$Data['name'];
            $editItemArray['Item']['description']=$Data['description'];
            $editItemArray['Item']['category_id']=$Data['category_id'];
            $editItemArray['Item']['is_seasonal_item']=$Data['is_seasonal_item'];
            $editItemArray['Item']['start_date']=$Data['start_date'];
            $editItemArray['Item']['end_date']=$Data['end_date'];
            $editItemArray['Item']['image']=$Data['image'];
            $editItemArray['Item']['is_deliverable']=$Data['is_deliverable'];
         }
         if($key=='ItemPrice'){
            $priceString=0;
            $i=1; //echo $key;pr($Data);die;
            foreach($Data as $vkey => $Pricearray){
               if($i==1){
                  $priceString=$Pricearray['price'];
               }else{
                  $priceString.=','.$Pricearray['price'];
               }
               if(isset($Pricearray['Size']['id'])){
                  $editItemArray['Size']['id'][]=$Pricearray['Size']['id'];
               }
               //$editItemArray['ItemPrice']['id'][]=$Pricearray['id'];
               $i++;
            }
            $editItemArray['ItemPrice']['price']=$priceString;
         }
         
         if($key=='Category'){     //echo $key;pr($Data);//die;        
            $editItemArray['Size']['issizeonly']=$Data['is_sizeonly'];            
         }
         
         if($key=='ItemType'){ //echo $key;pr($Data);die;
            foreach($Data as $vkey => $typeArray){
               $editItemArray['Type']['id'][]=$typeArray['type_id'];
            }
         }
         
           
       }
      $sizepost=0;
      $typepost=0;
      $seasonalpost=0;
      if(isset($editItemArray['Item']['category_id'])){
         $sizeList=$this->Size->getCategorySizes($editItemArray['Item']['category_id'],$storeId);
         if($editItemArray['Size']['issizeonly']==1 || $editItemArray['Size']['issizeonly']==3){ // [1,3 Size applicable]
            $sizepost=1;
         }
      }
      $this->set('sizeList',$sizeList);
      if($editItemArray['Size']['issizeonly']==2 || $editItemArray['Size']['issizeonly']==3){  // [2,3 Size applicable]
         $typepost=1;
      }
      if($editItemArray['Item']['is_seasonal_item']>0){
         $seasonalpost=1;
      }
      $this->set('typepost',$typepost);
      $this->set('sizepost',$sizepost);
      $this->set('seasonalpost',$seasonalpost);      
      $this->loadModel('Category');
      $categoryList=$this->Category->getCategoryList($storeId);
      $this->set('categoryList',$categoryList);
      $this->loadModel('Type');
      $typeList=$this->Type->getTypes($storeId);
      $this->set('typeList',$typeList);       
      $this->request->data=$editItemArray;
      //pr($this->request->data);die;
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
      $criteria = "Item.store_id =$storeID AND Item.is_deleted=0";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
      if($this->Session->read('ItemSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('ItemSearchData'),true);
      }else{
            $this->Session->delete('ItemSearchData');
      }         
      
      if (!empty($this->request->data)) {
          $this->Session->write('ItemSearchData',json_encode($this->request->data));
          if (!empty($this->request->data['Item']['keyword'])) {
              $value = trim($this->request->data['Item']['keyword']);
              $criteria .= " AND (Item.name LIKE '%" . $value . "%' OR Item.description LIKE '%" . $value . "%' OR Category.name LIKE '%" . $value . "%')";
          }
          if(!empty($this->request->data['Item']['category_id'])){
              $categoryID = trim($this->request->data['Item']['category_id']);
              $criteria .= " AND (Category.id =$categoryID)";
          }
          if($this->request->data['Item']['is_active']!=''){
              $active = trim($this->request->data['Item']['is_active']);
              $criteria .= " AND (Item.is_active =$active)";
          }
      }          
      
      
      $this->Item->bindModel(
                array(
                  'belongsTo'=>array(
                      'Category' =>array(
                       'className' => 'Category',
                       'foreignKey' => 'category_id',
                       'conditions' => array('Category.is_deleted' =>0,'Category.is_active' =>1),
                       'fields'=>array('id','name')
                   )         
                  )
                ),false
              ); 
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('Item.created'=>'DESC'));
      $itemdetail=$this->paginate('Item');
      $this->set('list',$itemdetail);
      $this->loadModel('Category');
      $categoryList=$this->Category->getCategoryList($storeID);
      $this->set('categoryList',$categoryList);
      $this->set('keyword', $value);
   }
   
   
   /*------------------------------------------------
      Function name:activateItem()
      Description:Active/deactive items
      created:5/8/2015
     -----------------------------------------------------*/  
      public function activateItem($EncrypteditemID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Item']['store_id']=$this->Session->read('store_id');
            $data['Item']['id']=$this->Encryption->decode($EncrypteditemID);
            $data['Item']['is_active']=$status;
            if($this->Item->saveItem($data)){
               if($status){
                  $SuccessMsg="Item Activated";
               }else{
                  $SuccessMsg="Item Deactivated and Item will not get Display in Menu List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'Items', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Items', 'action' => 'index'));
            }
      }
      
   /*------------------------------------------------
      Function name:deleteItem()
      Description:Delete item
      created:5/8/2015
     -----------------------------------------------------*/  
      public function deleteItem($EncrypteditemID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Item']['store_id']=$this->Session->read('store_id');
            $data['Item']['id']=$this->Encryption->decode($EncrypteditemID);
            $data['Item']['is_deleted']=1;
            if($this->Item->saveItem($data)){
               $this->Session->setFlash(__("Item deleted"));
               $this->redirect(array('controller' => 'Items', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Items', 'action' => 'index'));
            }
      }
      
      /*------------------------------------------------
      Function name:deleteItemPhoto()
      Description:Delete item Photo
      created:5/8/2015
     -----------------------------------------------------*/  
      public function deleteItemPhoto($EncryptItemID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Item']['store_id']=$this->Session->read('store_id');
            $data['Item']['id']=$this->Encryption->decode($EncryptItemID);
            $data['Item']['image']='';
            if($this->Item->saveItem($data)){
               $this->Session->setFlash(__("Item Photo deleted"));
               $this->redirect(array('controller' => 'Items', 'action' => 'editMenuItem',$EncryptItemID));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Items', 'action' => 'editMenuItem',$EncryptItemID));
            }
      }
      
      
      /*------------------------------------------------
      Function name:itemsBycategory()
      Description:get items by category
      created:6/8/2015
     -----------------------------------------------------*/
      
      public function itemsBycategory($categoryId=null){
         $itemList='';
         $storeID=$this->Session->read('store_id');
         if($categoryId){
            $itemList=$this->Item->getItemsByCategory($categoryId,$storeID);
         }
         $this->set('itemList',$itemList);
      }
   
   
}
<?php
App::uses('AppController', 'Controller');
class SizesController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','Common');
   public $uses=array('Size','Item');
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
             parent::beforeFilter();
             $adminfunctions=array('addSize','index','deleteSize','activateSize','editSize');
            if(in_array($this->params['action'],$adminfunctions)){
               if(!$this->Common->checkPermissionByaction($this->params['controller'])){
                 $this->Session->setFlash(__("Permission Denied"));
                 $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
               }
            }
            
   }
   
    /*------------------------------------------------
    Function name:getCategorySizes()
    Description:To find list of the categories from category table 
    created:3/8/2015
    -----------------------------------------------------*/
    public function getCategorySizes($categoryId=null){        
         $storeId=$this->Session->read('store_id');        
        $this->loadModel('Size');
        $this->loadModel('Category');
        if($categoryId){
            $sizeList='';
            if($this->Category->checkCategorySizeExists($categoryId,$storeId)){           
               $sizeList=$this->Size->getCategorySizes($categoryId,$storeId);
            }
            $sizeInfo=$this->Category->getCategorySizeType($categoryId,$storeId);           
            $this->set('sizeList',$sizeList);
            $this->set('sizeInfo',$sizeInfo);     
        }else{
            exit;
        }
    }
    
    /*------------------------------------------------
    Function name:getItemSizes()
    Description:To find list of the Sizes
    created:3/8/2015
    -----------------------------------------------------*/
    public function getItemSizes($itemId=null){        
         $storeId=$this->Session->read('store_id');        
        $this->loadModel('Size');
        $this->loadModel('Category');
        if($itemId){
            $sizeList='';
            $category=$this->Item->getcategoryByitemID($itemId,$storeId);
            if($category){
               $categoryId=$category['Item']['category_id'];
               if($this->Category->checkCategorySizeExists($categoryId,$storeId)){              
                  $sizeList=$this->Size->getCategorySizes($categoryId,$storeId);
               }              
            }
            $this->set('sizeList',$sizeList);
        }else{
            exit;
        }
    }
    
    
    
    /*------------------------------------------------
    Function name:getItemSizes()
    Description:To find list of the Sizes
    created:3/8/2015
    -----------------------------------------------------*/
    public function getItemSize($itemId=null){        
         $storeId=$this->Session->read('store_id');        
        $this->loadModel('Size');
        $this->loadModel('Category');
        if($itemId){
            $sizeList='';
            $category=$this->Item->getcategoryByitemID($itemId,$storeId);
            if($category){
               $categoryId=$category['Item']['category_id'];
               if($this->Category->checkCategorySizeExists($categoryId,$storeId)){              
                  $sizeList=$this->Size->getCategorySizes($categoryId,$storeId);
               }              
            }
            $this->set('sizeList',$sizeList);
        }else{
            exit;
        }
    }

    
    /*------------------------------------------------
    Function name:getItemSizes()
    Description:To find list of the Sizes
    created:3/8/2015
    -----------------------------------------------------*/
    public function getMultipleItemSizes(){    //pr($this->data); 
        $storeId=$this->Session->read('store_id');        
        $this->loadModel('Size');
        $this->loadModel('Category');
        if($this->data){
            $sizeList=array();
            foreach($this->data['Offered']['id'] as $key =>$itemId){
               
               $category=$this->Item->getcategoryByitemID($itemId,$storeId);
               if($category){
                  $categoryId=$category['Item']['category_id'];
                  if($this->Category->checkCategorySizeExists($categoryId,$storeId)){              
                     $sizeList[$itemId]=$this->Size->getCategorySizes($categoryId,$storeId);
                  }else{
                     $sizeList[$itemId]='';
                  }
               }
            }            
            $this->set('sizeList',$sizeList);
        }else{
            exit;
        }
    }    
    
    
    
    /*------------------------------------------------
    Function name:addSize()
    Description:To add thesize in size table 
    created:7/8/2015
    -----------------------------------------------------*/
    public function addSize(){        
          $this->layout="admin_dashboard";
          $storeID=$this->Session->read('store_id');
          $merchant_id= $this->Session->read('merchant_id');
          if($this->request->data){
             $size=trim($this->data['Size']['size']);
             $categoryId=trim($this->data['Size']['category_id']);
             $isUniqueName=$this->Size->checkSizeUniqueName($size,$storeID,$categoryId);
             if($isUniqueName){
              $size = explode(',',$this->data['Size']['size']);
               foreach($size as  $key => $Data){
                $sizedata['store_id']=$storeID;
               $sizedata['merchant_id']=$merchant_id;
               $sizedata['size'] = $Data;
               $sizedata['category_id']=$this->data['Size']['category_id'];
               $sizedata['is_active']=$this->data['Size']['is_active'];
               $this->Size->create();
              $this->Size->saveSize($sizedata);
            }
                  
            $this->Session->setFlash(__("Size Successfully Added"));
            $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
          }
          else{
            $this->Session->setFlash(__("Size Already exists"));
          }
          }
          $this->loadModel('Category');
          $categoryList=$this->Category->getCategoryListIsSize($storeID);
          $this->set('categoryList',$categoryList);
    }
    
     /*------------------------------------------------
    Function name:index()
    Description:To display the list of category size 
    created:7/8/2015
    -----------------------------------------------------*/
    public function index($clearAction=null){        
          $this->layout="admin_dashboard";
          $storeID=$this->Session->read('store_id');
          $merchant_id= $this->Session->read('merchant_id');
          
          /******start********/
                $value = "";      
      $criteria = "Size.store_id =$storeID AND Size.is_deleted=0";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
           
            
      if($this->Session->read('SizeSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('SizeSearchData'),true);
      }else{
            $this->Session->delete('SizeSearchData');
      }
      
      if (!empty($this->request->data)) {
          $this->Session->write('SizeSearchData',json_encode($this->request->data));
      
          if(!empty($this->request->data['Size']['category_id'])){
              $categoryID = trim($this->request->data['Size']['category_id']);
              $criteria .= " AND (Category.id =$categoryID)";
          }
          if($this->request->data['Size']['is_active']!=''){
              $active = trim($this->request->data['Size']['is_active']);
              $criteria .= " AND (Size.is_active =$active)";
          }
      }          
      
      
      $this->Size->bindModel(
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
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('Size.created'=>'DESC'));
      $itemdetail=$this->paginate('Size');
      //pr($itemdetail);die;
      $this->set('list',$itemdetail);
      $this->loadModel('Category');
      $categoryList=$this->Category->getCategoryList($storeID);
      $this->set('categoryList',$categoryList);
      $this->set('keyword', $value);
          /******end*********/
          $this->loadModel('Category');
          $categoryList=$this->Category->getCategoryListIsSize($storeID);
          $this->set('categoryList',$categoryList);
    }
    
    /*------------------------------------------------
      Function name:deleteSize()
      Description:Delete Size
      created:7/8/2015
     -----------------------------------------------------*/  
      public function deleteSize($EncryptSizeID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Size']['store_id']=$this->Session->read('store_id');
            $data['Size']['id']=$this->Encryption->decode($EncryptSizeID);
            $data['Size']['is_deleted']=1;
            if($this->Size->saveSize($data)){
               $this->Session->setFlash(__("Size deleted"));
               $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
            }
      }
      
      /*------------------------------------------------
      Function name:activateSize()
      Description:Active/deactive category sizes
      created:7/8/2015
     -----------------------------------------------------*/  
      public function activateSize($EncryptedSizeID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Size']['store_id']=$this->Session->read('store_id');
            $data['Size']['id']=$this->Encryption->decode($EncryptedSizeID);
            $data['Size']['is_active']=$status;
            if($this->Size->saveSize($data)){
               if($status){
                  $SuccessMsg="Size Activated";
               }else{
                  $SuccessMsg="Size Deactivated and Size will not get Display in Menu List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
            }
      }
      
       /*------------------------------------------------
      Function name:editSize()
      Description:Edit Category Size
      created:6/8/2015
     -----------------------------------------------------*/  
      public function editSize($EncryptSizeID=null){
        $this->layout="admin_dashboard";
           
             $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $data['Size']['id']=$this->Encryption->decode($EncryptSizeID);
             $this->loadModel('Size');
            $sizeDetail=$this->Size->getSizeDetail($data['Size']['id'], $storeId);
             
              if($this->request->data){
            $sizedata=array();
            $size=trim($this->data['Size']['size']);
            $categoryId=trim($this->data['Size']['category_id']);
             $isUniqueName=$this->Size->checkSizeUniqueName($size,$storeId,$categoryId,$data['Size']['id']);
             if($isUniqueName){
            $sizedata['id']=$data['Size']['id'];
            $sizedata['size']=trim($this->data['Size']['size']);
            $sizedata['category_id']=$this->data['Size']['category_id'];
            $sizedata['is_active']=$this->data['Size']['is_active']; 
            $sizedata['store_id']=$storeId;
            $sizedata['merchant_id']=$merchantId;
            $this->Size->create();
            $this->Size->saveSize($sizedata); 
            $this->Session->setFlash(__("Category Size Updated Successfully ."));
            $this->redirect(array('controller' => 'sizes', 'action' => 'index'));
         }
         else{
           $this->Session->setFlash(__("Size Already exists"));
              }
              }
            $this->loadModel('Category');
            $categoryList=$this->Category->getCategoryListIsSize($storeId);
            $this->set('categoryList',$categoryList);
            $this->request->data=$sizeDetail;
      }
     
   
}
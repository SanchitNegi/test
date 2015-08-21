<?php
App::uses('AppController', 'Controller');
class CategoriesController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses='Category';
   
   public function beforeFilter() {
      // echo Router::url( $this->here, true );die;
      parent::beforeFilter();
      $adminfunctions=array('index','categoryList','activateCategory','deleteCategory','deleteCategoryPhoto','editCategory');
      if(in_array($this->params['action'],$adminfunctions)){
	 if(!$this->Common->checkPermissionByaction($this->params['controller'])){
	   $this->Session->setFlash(__("Permission Denied"));
	   $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
	 }
      }
   }
   
   /*------------------------------------------------
      Function name:index()
      Description:Add New Category
      created:5/8/2015
     -----------------------------------------------------*/ 
   
   public function index() {
       $this->layout="admin_dashboard";
       $storeId=$this->Session->read('store_id');
       $merchantId= $this->Session->read('merchant_id');
           $start="00:30";
            $end="24:00";
            $timeRange=$this->Common->getStoreTime($start,$end);            
            $this->set('timeOptions',$timeRange);
         if($this->request->data){
            //pr($this->request->data);die;
             $categoryName=trim($this->data['category']['name']);
             $isUniqueName=$this->Category->checkCategoryUniqueName($categoryName,$storeId);
             if($isUniqueName){
            $categorydata=array();
            if (empty($this->request->data['category']['imgcat']['name'])) {
                $categorydata['imgcat'] = "";
            }else{
      
               $response=$this->Common->uploadMenuItemImages($this->data['category']['imgcat'],'/Category-Image/',$storeId);
           // pr($response);die;
            if(!$response['status']){
               $this->Session->setFlash(__($response['errmsg']));
               $this->redirect($this->request->referer());
            }else{
            $categorydata['imgcat']=$response['imagename'];
            }
            }
            $categorydata['name']=trim($this->data['category']['name']);
            $categorydata['is_sizeonly']=$this->data['category']['is_sizeonly'];
            $categorydata['has_topping']=$this->data['category']['has_topping'];
            $categorydata['is_active']=$this->data['category']['is_active'];
            $categorydata['is_meal']=$this->data['category']['is_meal'];
            $categorydata['start_time']=$this->data['category']['start_time'];
             $categorydata['end_time']=$this->data['category']['end_time'];
            $categorydata['store_id']=$storeId;
            $categorydata['merchant_id']=$merchantId;
            $this->Category->create();
           $this->Category->saveCategory($categorydata);
           
            $this->Session->setFlash(__("Category Successfully Created"));
            $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
         }
         else{
            
            $this->Session->setFlash(__("Category name Already exists"));
         }
         }
         
         
	}
        
        /*------------------------------------------------
      Function name:categoryList()
      Description:Display the list of Category
      created:5/8/2015
     -----------------------------------------------------*/ 
      
   public function categoryList($clearAction=null) {
      error_reporting(0);
       $this->layout="admin_dashboard";
       $storeID=$this->Session->read('store_id');    
       $criteria = "Category.store_id =$storeID AND Category.is_deleted=0";
        if($this->Session->read('CategorySearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('CategorySearchData'),true);
      }else{
            $this->Session->delete('CategorySearchData');
      }
       if (!empty($this->request->data)) {
             $this->Session->write('CategorySearchData',json_encode($this->request->data));
         if($this->request->data['category']['is_active']!=''){
              $active = trim($this->request->data['category']['is_active']);
              $criteria .= " AND (Category.is_active =$active)";
          }
          

      }    
       $this->paginate= array('conditions'=>array($criteria),'order'=>array('Category.created'=>'DESC'));
       $categorydetail=$this->paginate('Category');
       
      $this->set('list',$categorydetail);
      
	}
      
      /*------------------------------------------------
      Function name:activateCategory()
      Description:Active/deactive Category
      created:6/8/2015
     -----------------------------------------------------*/  
      public function activateCategory($EncryptCategoryID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Category']['store_id']=$this->Session->read('store_id');
            $data['Category']['id']=$this->Encryption->decode($EncryptCategoryID);
            $data['Category']['is_active']=$status;
            if($this->Category->saveCategory($data)){
               if($status){
                  $SuccessMsg="Category Activated";
               }else{
                  $SuccessMsg="Category Deactivated and Category will not get Display in Menu List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
            }
      }
   
    /*------------------------------------------------
      Function name:deleteCategory()
      Description:Delete Category
      created:6/8/2015
     -----------------------------------------------------*/  
      public function deleteCategory($EncryptCategoryID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Category']['store_id']=$this->Session->read('store_id');
            $data['Category']['id']=$this->Encryption->decode($EncryptCategoryID);
            $data['Category']['is_deleted']=1;
            if($this->Category->saveCategory($data)){
               $this->Session->setFlash(__("Category deleted"));
               $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
            }
      }
      
      /*------------------------------------------------
      Function name:editCategory()
      Description:Edit Category
      created:6/8/2015
     -----------------------------------------------------*/  
      public function editCategory($EncryptCategoryID=null){
        $this->layout="admin_dashboard";
            $seasonalpost=0;
            $start="00:30";
            $end="24:00";
            $timeRange=$this->Common->getStoreTime($start,$end);            
            $this->set('timeOptions',$timeRange);
             $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $data['Category']['id']=$this->Encryption->decode($EncryptCategoryID);
             $this->loadModel('Category');
               $categoryDetail=$this->Category->getCategoryDetail($data['Category']['id'], $storeId);
             //echo '<pre>';print_r($categoryDetail['Category']['imgcat']);die;
             if($categoryDetail['Category']['is_meal'] == 1){
         $seasonalpost=1;
      }
              $this->set('seasonalpost',$seasonalpost);
              $this->set('imgpath',$categoryDetail['Category']['imgcat']);
              if($this->request->data){
              //pr($this->request->data);die;
            $categorydata=array();
            $categoryName=trim($this->data['Category']['name']);
             $isUniqueName=$this->Category->checkCategoryUniqueName($categoryName,$storeId,$data['Category']['id']);
             if($isUniqueName){
            if (empty($this->request->data['Category']['imgcat']['name'])) {
                $categorydata['imgcat'] = $categoryDetail['Category']['imgcat'];
            }else{
               $response=$this->Common->uploadMenuItemImages($this->data['Category']['imgcat'],'/Category-Image/',$storeId);
            if(!$response['status']){
               $this->Session->setFlash(__($response['errmsg']));
               $this->redirect($this->request->referer());
            }else{
            $categorydata['imgcat']=$response['imagename'];
            }
            }
            $categorydata['id']=$data['Category']['id'];
            $categorydata['name']=trim($this->data['Category']['name']);
            $categorydata['is_sizeonly']=$this->data['Category']['is_sizeonly'];
            $categorydata['has_topping']=$this->data['Category']['has_topping'];
            $categorydata['is_active']=$this->data['Category']['is_active'];
            $categorydata['is_meal']=$this->data['Category']['is_meal'];
            $categorydata['start_time']=$this->data['Category']['start_time'];
            $categorydata['end_time']=$this->data['Category']['end_time'];
            $categorydata['store_id']=$storeId;
            $categorydata['merchant_id']=$merchantId;
            $this->Category->create();
            $this->Category->saveCategory($categorydata); 
            $this->Session->setFlash(__("Category Updated Successfully."));
            $this->redirect(array('controller' => 'categories', 'action' => 'categoryList'));
         }
         else{
           $this->Session->setFlash(__("Category name Already exists"));
              }
              }
           
               $this->request->data=$categoryDetail;
      }
      
       /*------------------------------------------------
      Function name:deleteCategoryPhoto()
      Description:Delete category Photo
      created:7/8/2015
     -----------------------------------------------------*/  
      public function deleteCategoryPhoto($EncryptCategoryID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Category']['store_id']=$this->Session->read('store_id');
            $data['Category']['id']=$this->Encryption->decode($EncryptCategoryID);
            $data['Category']['imgcat']='';
            if($this->Category->saveCategory($data)){
               $this->Session->setFlash(__("Category Photo deleted"));
               $this->redirect(array('controller' => 'categories', 'action' => 'editCategory',$EncryptCategoryID));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'categories', 'action' => 'editCategory',$EncryptCategoryID));
            }
      }
      
}
?>
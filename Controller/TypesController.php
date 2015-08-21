<?php
App::uses('AppController', 'Controller');
class TypesController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption');
   public $uses='Type';
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
            parent::beforeFilter();
            $adminfunctions=array('addType','index','deleteType','editType');
            if(in_array($this->params['action'],$adminfunctions)){
               if(!$this->Common->checkPermissionByaction($this->params['controller'])){
                 $this->Session->setFlash(__("Permission Denied"));
                 $this->redirect(array('controller' => 'Stores', 'action' => 'dashboard'));
               }
            }
   }
   
    
    /*------------------------------------------------
    Function name:addType()
    Description:To add Type in type table 
    created:7/8/2015
    -----------------------------------------------------*/
    public function addType(){        
          $this->layout="admin_dashboard";
          $storeID=$this->Session->read('store_id');
          $merchant_id= $this->Session->read('merchant_id');
          if($this->request->data){
             $type=trim($this->data['Type']['name']);
             $isUniqueName=$this->Type->checkTypeUniqueName($type,$storeID);
             if($isUniqueName){
                $typedata['store_id']=$storeID;
                $typedata['merchant_id']=$merchant_id;
                $typedata['name'] = $this->data['Type']['name'];
                $typedata['is_active']=$this->data['Type']['is_active'];
                $this->Type->create();
              $this->Type->saveType($typedata);     
            $this->Session->setFlash(__("Type Successfully Added"));
            $this->redirect(array('controller' => 'types', 'action' => 'index'));
          }
          else{
            $this->Session->setFlash(__("Type Already exists"));
          }
          }

    }
    
     /*------------------------------------------------
    Function name:index()
    Description:To display the list of type 
    created:7/8/2015
    -----------------------------------------------------*/
    public function index($clearAction=null){        
          $this->layout="admin_dashboard";
          $storeID=$this->Session->read('store_id');
          $merchant_id= $this->Session->read('merchant_id');
          /******start********/
                $value = "";      
      $criteria = "Type.store_id =$storeID AND Type.is_deleted=0";
       if($this->Session->read('TypeSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('TypeSearchData'),true);
      }else{
            $this->Session->delete('TypeSearchData');
      }
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
            
      
      if (!empty($this->request->data)) {
         
            $this->Session->write('TypeSearchData',json_encode($this->request->data));
          if($this->request->data['Type']['is_active']!=''){
              $active = trim($this->request->data['Type']['is_active']);
              $criteria .= " AND (Type.is_active =$active)";
             
          }
      }          
      
      
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('Type.created'=>'DESC'));
      $typedetail=$this->paginate('Type');
      $this->set('list',$typedetail);
      
    }
    
    /*------------------------------------------------
      Function name:deleteType()
      Description:Delete Type
      created:7/8/2015
     -----------------------------------------------------*/  
      public function deleteType($EncryptTypeID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Type']['store_id']=$this->Session->read('store_id');
            $data['Type']['id']=$this->Encryption->decode($EncryptTypeID);
            $data['Type']['is_deleted']=1;
            if($this->Type->saveType($data)){
               $this->Session->setFlash(__("Type deleted"));
               $this->redirect(array('controller' => 'types', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'types', 'action' => 'index'));
            }
      }
      
      /*------------------------------------------------
      Function name:activateType()
      Description:Active/deactive type
      created:7/8/2015
     -----------------------------------------------------*/  
      public function activateType($EncryptedTypeID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Type']['store_id']=$this->Session->read('store_id');
            $data['Type']['id']=$this->Encryption->decode($EncryptedTypeID);
            $data['Type']['is_active']=$status;
            if($this->Type->saveType($data)){
               if($status){
                  $SuccessMsg="Type Activated";
               }else{
                  $SuccessMsg="Type Deactivated and Type will not get Display in Menu List";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'types', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'types', 'action' => 'index'));
            }
      }
      
       /*------------------------------------------------
      Function name:editType()
      Description:Edit Type
      created:7/8/2015
     -----------------------------------------------------*/  
      public function editType($EncryptTypeID=null){
        $this->layout="admin_dashboard";
           
             $storeId=$this->Session->read('store_id');
            $merchantId= $this->Session->read('merchant_id');
            $data['Type']['id']=$this->Encryption->decode($EncryptTypeID);
             $this->loadModel('Type');
            $typeDetail=$this->Type->getTypeDetail($data['Type']['id'], $storeId);
             
              if($this->request->data){
            $typedata=array();
            $type=trim($this->data['Type']['name']);
             $isUniqueName=$this->Type->checkTypeUniqueName($type,$storeId,$data['Type']['id']);
             if($isUniqueName){
            $typedata['id']=$data['Type']['id'];
            $typedata['name']=trim($this->data['Type']['name']);
            $typedata['is_active']=$this->data['Type']['is_active']; 
            $typedata['store_id']=$storeId;
            $typedata['merchant_id']=$merchantId;
            $this->Type->create();
            $this->Type->saveType($typedata); 
            $this->Session->setFlash(__("Type Updated Successfully ."));
            $this->redirect(array('controller' => 'types', 'action' => 'index'));
         }
         else{
           $this->Session->setFlash(__("Type Already exists"));
              }
              }
            $this->request->data=$typeDetail;
      }
     
   
}
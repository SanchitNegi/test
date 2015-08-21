<?php
App::uses('AppController', 'Controller');
class NewslettersController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','Dateform');
   public $uses=array('Order');
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
             parent::beforeFilter();   
            
   }
   
   /*------------------------------------------------
      Function name:index()
      Description:Add the newsletter in table
      created:20/8/2015
     -----------------------------------------------------*/  
   public function index(){
       $this->layout="admin_dashboard";       
      $storeID=$this->Session->read('store_id');
      if($this->request->data){
         
         pr($this->request->data);die;
      }
     
   }
   
   /*------------------------------------------------
      Function name:newsletterList()
      Description:Display the list of created newsletters
      created:20/8/2015
     -----------------------------------------------------*/  
   public function newsletterList(){
       $this->layout="admin_dashboard";       
      $storeID=$this->Session->read('store_id');
     
   }
  
             
}

?>
<?php
App::uses('AppController', 'Controller');
class OffersController extends AppController {

   public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Dateform','Common');
   public $helper=array('Encryption','DateformHelper');
   public $uses=array('Offer','Item','ItemPrice','ItemType','Size','Category','OfferDetail');
   
   public function beforeFilter() {
            // echo Router::url( $this->here, true );die;
            parent::beforeFilter();
            $adminfunctions=array('index','addOffer','editOffer','activateOffer','deleteOffer','deleteOfferPhoto');
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
      $value = "";      
      $criteria = "Offer.store_id =$storeID AND Offer.is_deleted=0";
      
      //if(isset($this->params['named']['sort']) || isset($this->params['named']['page'])){
      if($this->Session->read('OfferSearchData') && $clearAction!='clear' && !$this->request->is('post')){            
            $this->request->data = json_decode($this->Session->read('OfferSearchData'),true);
      }else{
            $this->Session->delete('OfferSearchData');
      }         
      
      if (!empty($this->request->data)) {
          $this->Session->write('OfferSearchData',json_encode($this->request->data));
          if (!empty($this->request->data['Offer']['keyword'])) {
              $value = trim($this->request->data['Offer']['keyword']);
              $criteria .= " AND (Offer.description LIKE '%" . $value . "%' OR Item.name LIKE '%" . $value . "%')";
          }
          //if(!empty($this->request->data['Item']['category_id'])){
          //    $categoryID = trim($this->request->data['Item']['category_id']);
          //    $criteria .= " AND (Category.id =$categoryID)";
          //}
          if($this->request->data['Offer']['is_active']!=''){
              $active = trim($this->request->data['Offer']['is_active']);
              $criteria .= " AND (Offer.is_active =$active)";
          }
      }          
      
      
      $this->Offer->bindModel(
                array(
                  'belongsTo'=>array(
                      'Item' =>array(
                       'className' => 'Item',
                       'foreignKey' => 'item_id',
                       'conditions' => array('Item.is_deleted' =>0,'Item.is_active' =>1),
                       'fields'=>array('id','name')
                   )         
                  )
                ),false
              ); 
      $this->paginate= array('conditions'=>array($criteria),'order'=>array('Offer.created'=>'DESC'));
      $itemdetail=$this->paginate('Offer');
      $this->set('list',$itemdetail);
      $this->loadModel('Category');
      $categoryList=$this->Category->getCategoryList($storeID);
      $this->set('categoryList',$categoryList);
      $this->set('keyword', $value);
   }
   
   
    /*------------------------------------------------
      Function name:addOffer()
      Description:add Offer
      created:5/8/2015
     -----------------------------------------------------*/  
      public function addOffer(){
        $this->layout="admin_dashboard";              
        $storeId=$this->Session->read('store_id');            
        $merchant_id= $this->Session->read('merchant_id');
        if($this->data){ //pr($this->data);die;        
            $response=$this->Common->uploadMenuItemImages($this->data['Offer']['imgcat'],'/Offer-Image/',$storeId);
            if(!$response['status']){
               $this->Session->setFlash(__($response['errmsg']));               
            }else{
               
               $offerData['offer_start_date']='';
               $offerData['offer_end_date']='';
               if($this->data['Offer']['offer_start_date'] && $this->data['Offer']['offer_end_date']){                  
                  $offerData['offer_start_date']=$this->Dateform->formatDate($this->data['Offer']['offer_start_date']);
                  $offerData['offer_end_date']=$this->Dateform->formatDate($this->data['Offer']['offer_end_date']);
               }              
               if(!$this->Offer->offerExistsOnItem($this->data['Item']['id'],$offerData['offer_start_date'],$offerData['offer_end_date'])){
               //Offer Data
                  $offerData['offerImage']=$response['imagename'];
                  $offerData['store_id']=$storeId;
                  $offerData['merchant_id']=$merchant_id; 
                  $offerData['item_id']=$this->data['Item']['id'];
                  $offerData['unit']=$this->data['Offer']['unit'];
                  $offerData['description']=$this->data['Offer']['description']; 
                  $offerData['size_id']=$this->data['Size']['id'];
                  $offerData['is_fixed_price']=$this->data['Offer']['is_fixed_price'];
                  
                  $offerData['offerprice']=($this->data['Offer']['offerprice'])?$this->data['Offer']['offerprice']:0;           
                  $offerData['is_active']=($this->data['Offer']['is_active']==1)?1:0;
                  $this->Offer->saveOffer($offerData);
                  $offerID=$this->Offer->getLastInsertId();
                  if($offerID){
                     if(isset($this->data['OfferDetails']) && $this->data['OfferDetails']){
                        foreach($this->data['OfferDetails'] as $key => $offerdetails){                     
                           $offerdetailsData['offerItemID']=$offerdetails['item_id'];
                           $offerdetailsData['offer_id']=$offerID;
                           $offerdetailsData['store_id']=$storeId;
                           $offerdetailsData['merchant_id']=$merchant_id; 
                           $priceArray=explode(',',$offerdetails['discountAmt']);
                           if(!$priceArray[0]){
                              $priceArray[0]=0;
                           }
                           
                           if(isset($offerdetails['offerSize']) && $offerdetails['offerSize']){
                              //$i=0;
                              $offerdetailsData['offerSize']=$offerdetails['offerSize'];
                              $offerdetailsData['discountAmt']=$priceArray[0];
                              $this->OfferDetail->create();
                              $this->OfferDetail->saveOfferDetail($offerdetailsData);
                              //foreach($offerdetails['offerSize'] as $vkey =>$size){
                              //   if(!isset($priceArray[$i])){
                              //      $priceArray[$i]=$priceArray[0];
                              //   }
                              //   $offerdetailsData['offerSize']=$size;
                              //   $offerdetailsData['discountAmt']=$priceArray[$i];
                              //   //pr($offerdetailsData);
                              //   $this->OfferDetail->create();
                              //   $this->OfferDetail->saveOfferDetail($offerdetailsData);
                              //   $i++;
                              //}
                              
                           }else{
                              $offerdetailsData['discountAmt']=$priceArray[0];
                              $this->OfferDetail->saveOfferDetail($offerdetailsData);
                           }
                           
                        }
                     }
                     $this->Session->setFlash(__("Offer Successfully Created"));
                     $this->redirect(array('controller' => 'Offers', 'action' => 'index'));   
                  }else{
                     $this->Session->setFlash(__("Offer Not Created"));
                  }
               }else{
                  $this->Session->setFlash(__("Offer on Item already exists from given time range"));
               }
            }
         }   
         $sizepost=0;
         $sizeList="";
         $isfixed=0;
         if(isset($this->data['Item']['id'])){
            $category=$this->Item->getcategoryByitemID($this->data['Item']['id'],$storeId);
            $sizeList=$this->Size->getCategorySizes($category['Item']['category_id'],$storeId);
            $sizepost=1;
         }
         if(isset($this->data['Offer']['is_fixed_price']) && $this->data['Offer']['is_fixed_price']){
            $isfixed=1;
         }
         
         $itemlist=$this->Item->getAllItems($storeId);
         $this->set('itemList',$itemlist);
         $this->set('isfixed',$isfixed);
         $this->set('sizepost',$sizepost);
         $this->set('sizeList',$sizeList);
      }
   
    /*------------------------------------------------
      Function name:editMenuItem()
      Description:Update Menu Item
      created:5/8/2015
     -----------------------------------------------------*/  
      public function editOffer($EncryptedofferID=null){
         $this->layout="admin_dashboard"; 
         $storeId=$this->Session->read('store_id');
         $merchant_id= $this->Session->read('merchant_id');
         $data['Offer']['id']=$this->Encryption->decode($EncryptedofferID);   
         
         if($this->data){
            $offerData['offer_start_date']='';
            $offerData['offer_end_date']='';
            if($this->data['Offer']['offer_start_date'] && $this->data['Offer']['offer_end_date']){                  
               $offerData['offer_start_date']=$this->Dateform->formatDate($this->data['Offer']['offer_start_date']);
               $offerData['offer_end_date']=$this->Dateform->formatDate($this->data['Offer']['offer_end_date']);
            }
            if(!$this->Offer->offerExistsOnItem($this->data['Item']['id'],$offerData['offer_start_date'],$offerData['offer_end_date'])){
               $offerData['id']=$this->data['Offer']['id'];
               $offerData['store_id']=$storeId;
               $offerData['merchant_id']=$merchant_id; 
               $offerData['item_id']=$this->data['Item']['id'];
               $offerData['description']=$this->data['Offer']['description']; 
               $offerData['size_id']=$this->data['Size']['id'];
               $offerData['unit']=$this->data['Offer']['unit'];
               $offerData['is_fixed_price']=$this->data['Offer']['is_fixed_price'];               
               $offerData['offerprice']=($this->data['Offer']['offerprice'])?$this->data['Offer']['offerprice']:0;
               $offerData['is_active']=($this->data['Offer']['is_active']==1)?1:0;
               if($this->data['Offer']['imgcat']['error']==0){
                    $response=$this->Common->uploadMenuItemImages($this->data['Offer']['imgcat'],'/Offer-Image/',$storeId);
               }elseif($this->data['Offer']['imgcat']['error']==4){
                    $response['status']=true;
                    $response['imagename']='';
               }
               if(!$response['status']){
                  $this->Session->setFlash(__($response['errmsg']));               
               }else{
                  //Item Data
                  if($response['imagename']){
                    $offerData['offerImage']=$response['imagename'];
                  }
                  $this->Offer->saveOffer($offerData);
                  $this->Session->setFlash(__("Offer Updated"));
                  if($this->OfferDetail->deleteallOfferItems($this->data['Offer']['id'])){
                     if(isset($this->data['OfferDetails']) && $this->data['OfferDetails']){
                        foreach($this->data['OfferDetails'] as $key => $details){
                           if(isset($details['id'])){
                              $offerdetails['id']=$details['id'];
                           }else{
                              $offerdetails['id']='';
                           }
                           $offerdetails['offer_id']=$this->data['Offer']['id'];
                           $offerdetails['offerItemID']=$details['item_id'];
                           $offerdetails['is_deleted']=0;
                           if(isset($details['offerSize']) && $details['offerSize']){
                              $offerdetails['offerSize']=$details['offerSize'];
                           }else{
                              $offerdetails['offerSize']=0;
                           }
                           if($details['discountAmt']){
                              $offerdetails['discountAmt']=$details['discountAmt'];
                           }else{
                              $offerdetails['discountAmt']=0;
                           }
                           
                           $this->OfferDetail->saveOfferDetail($offerdetails);                        
                        }
                     }
                     $this->Session->setFlash(__("Offer Updated"));
                     $this->redirect(array('controller' => 'Offers', 'action' => 'index'));
                  }else{
                     $this->Session->setFlash(__("Some Problem occured"));
                  } 
               }
            }else{
               $this->Session->setFlash(__("Offer on Item already exists from given time range"));
            }
         }
         
         $this->Offer->bindModel(
                array(
                  'hasMany'=>array(
                      'OfferDetail' =>array(
                       'className' => 'OfferDetail',
                       'foreignKey' => 'offer_id',
                       'conditions' => array('OfferDetail.is_deleted' =>0),
                       'fields'=>array('OfferDetail.id','OfferDetail.offer_id','OfferDetail.offerItemID','OfferDetail.offerSize','OfferDetail.discountAmt')
                   )         
                  )
                ),false
              ); 
         $offerDetails=$this->Offer->getOfferDetails($data['Offer']['id']);
         //pr($offerDetails);
         $FinalOfferDetails['Offered']=array();
         foreach($offerDetails as $key => $Offer){
            if($key=="Offer"){
               $FinalOfferDetails['Item']['id']=$Offer['item_id'];
               $FinalOfferDetails['Size']['id']=$Offer['size_id'];
               $FinalOfferDetails['Offer']['description']=$Offer['description'];
               $FinalOfferDetails['Offer']['is_fixed_price']=$Offer['is_fixed_price'];
               $FinalOfferDetails['Offer']['offerprice']=$Offer['offerprice'];
               $FinalOfferDetails['Offer']['offer_start_date']=$Offer['offer_start_date'];
               $FinalOfferDetails['Offer']['offer_end_date']=$Offer['offer_end_date'];
               $FinalOfferDetails['Offer']['is_active']=$Offer['is_active'];
               $FinalOfferDetails['Offer']['imgcat']=$Offer['offerImage'];
               $FinalOfferDetails['Offer']['id']=$Offer['id'];
               $FinalOfferDetails['Offer']['unit']=$Offer['unit'];
            }elseif($key=="OfferDetail"){
               if($Offer){
                  $i=0;
                  $price=0;
                  //$keyforprevious=0;
                  foreach($Offer as $vkey => $offerdetails){                     
                     
                     //if(!in_array($offerdetails['offerItemID'],$FinalOfferDetails['Offered'])){
                     //   $FinalOfferDetails['Offered'][$vkey]=$offerdetails['offerItemID'];
                     //   $FinalOfferDetails['OfferDetails'][$vkey]['item_id']=$offerdetails['offerItemID'];
                     //   $FinalOfferDetails['OfferDetails'][$vkey]['offer_id']=$offerdetails['offer_id'];
                     //   $keyforprevious=$vkey;
                     //   $i=0;
                     //}                     
                     //$FinalOfferDetails['OfferDetails'][$keyforprevious]['offerSize'][$vkey]=$offerdetails['offerSize'];
                     //if($i==0){
                     //   $price=$offerdetails['discountAmt'];
                     //}else{
                     //   $price.=','.$offerdetails['discountAmt'];
                     //}
                     //$FinalOfferDetails['OfferDetails'][$keyforprevious]['discountAmt']=$price;
                     //$i++;
                     
                     
                     
                     $FinalOfferDetails['Offered']['id'][$vkey]=$offerdetails['offerItemID'];
                      $FinalOfferDetails['OfferDetails'][$vkey]['id']=$offerdetails['id'];
                     $FinalOfferDetails['OfferDetails'][$vkey]['item_id']=$offerdetails['offerItemID'];
                     $FinalOfferDetails['OfferDetails'][$vkey]['offer_id']=$offerdetails['offer_id'];
                     $FinalOfferDetails['OfferDetails'][$vkey]['offerSize']=$offerdetails['offerSize'];
                     $FinalOfferDetails['OfferDetails'][$vkey]['discountAmt']=$offerdetails['discountAmt'];
                  }
                  
               }
            }
         }
         $this->request->data=$FinalOfferDetails;//pr($this->request->data);die;
         $sizepost=0;
         $sizeList="";
         $isfixed=0;         
         if(isset($this->request->data['Item']['id'])){ 
            $category=$this->Item->getcategoryByitemID($this->request->data['Item']['id'],$storeId);            
            $sizeList=$this->Size->getCategorySizes($category['Item']['category_id'],$storeId);
            $sizepost=1;
         }         
         if(isset($this->request->data['Offer']['is_fixed_price']) && $this->request->data['Offer']['is_fixed_price']){
            $isfixed=1;
         }         
         $itemlist=$this->Item->getAllItems($storeId);
         $this->set('itemList',$itemlist);
         $this->set('isfixed',$isfixed);
         $this->set('sizepost',$sizepost);
         $this->set('sizeList',$sizeList);
      }
   
   
   
   /*------------------------------------------------
      Function name:activateOffer()
      Description:Active/deactive Offer
      created:5/8/2015
     -----------------------------------------------------*/  
      public function activateOffer($EncryptOfferID=null,$status=0){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Offer']['store_id']=$this->Session->read('store_id');
            $data['Offer']['id']=$this->Encryption->decode($EncryptOfferID);
            $data['Offer']['is_active']=$status;
            if($this->Offer->saveOffer($data)){
               if($status){
                  $SuccessMsg="Offer Activated";
               }else{
                  $SuccessMsg="Offer Deactivated and Offer will not get Display";
               }
               $this->Session->setFlash(__($SuccessMsg));
               $this->redirect(array('controller' => 'Offers', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Offers', 'action' => 'index'));
            }
      }
      
   /*------------------------------------------------
      Function name:deleteOffer()
      Description:Delete Offer
      created:5/8/2015
     -----------------------------------------------------*/  
      public function deleteOffer($EncryptOfferID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Offer']['store_id']=$this->Session->read('store_id');
            $data['Offer']['id']=$this->Encryption->decode($EncryptOfferID);
            $data['Offer']['is_deleted']=1;
            if($this->Offer->saveOffer($data)){
               $this->Session->setFlash(__("Offer deleted"));
               $this->redirect(array('controller' => 'Offers', 'action' => 'index'));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Offers', 'action' => 'index'));
            }
      }
      
      /*------------------------------------------------
      Function name:deleteOfferPhoto()
      Description:Delete Offer Photo
      created:5/8/2015
     -----------------------------------------------------*/  
      public function deleteOfferPhoto($EncryptOfferID=null){
            $this->autoRender=false;
            $this->layout="admin_dashboard";       
            $data['Offer']['store_id']=$this->Session->read('store_id');
            $data['Offer']['id']=$this->Encryption->decode($EncryptOfferID);
            $data['Offer']['offerImage']='';
            if($this->Offer->saveOffer($data)){
               $this->Session->setFlash(__("Offer Photo deleted"));
               $this->redirect(array('controller' => 'Offers', 'action' => 'editOffer',$EncryptOfferID));
            }else{
               $this->Session->setFlash(__("Some problem occured"));
               $this->redirect(array('controller' => 'Offers', 'action' => 'editOffer',$EncryptOfferID));
            }
      }
   
   
   
}   
?>
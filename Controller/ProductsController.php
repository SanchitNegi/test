<?php
App::uses('AppController', 'Controller');
class ProductsController extends AppController {
    public $components=array('Session','Cookie','Email','RequestHandler','Encryption','Paginator','Common','Dateform');
    public $helper = array('Encryption','Paginator','Form','DateformHelper','Common');
    
    public function beforeFilter() {
        parent::beforeFilter();       
    }
   
    /*------------------------------------------------
        Function name:items()
        Description:This will fetch the category of the menus from the table category 
        created:22/7/2015
    -----------------------------------------------------*/
      
    public function items($encrypted_storeId=null,$encrypted_merchantId=null,$orderId = null){
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Encryption->decode($encrypted_storeId);
        $decrypt_merchantId=$this->Encryption->decode($encrypted_merchantId);
        $this->loadModel('Category');
        $this->Category->bindModel(
            array('hasMany' => array(
                'Item' => array(
                    'className' => 'Item',
                    'foreignKey' => 'category_id',
                    'fields'=>array('id','name','category_id','start_date','end_date','image','description','is_seasonal_item'),
                    'conditions'=>array('Item.is_active'=>1,'Item.is_deleted'=>0),
                     'order' => array('name' => 'asc')

                )
            )
        ));
        $categoryList=$this->Category->findCategotyList($decrypt_storeId,$decrypt_merchantId); // It will find the list of categories of the menus
        if($categoryList){
           $this->set(compact('orderId','categoryList','encrypted_storeId','encrypted_merchantId','decrypt_storeId')); 
        }else{
            $this->set(compact('orderId','encrypted_storeId','encrypted_merchantId','decrypt_storeId')); 
        }
        $this->Session->delete('CartOffer');
        if($this->Session->check('cart')){
            $final_cart=$this->Session->read('cart');
            $this->set(compact('final_cart'));
        }
    }
      
    /*------------------------------------------------
        Function name:fetchProduct()
        Description:It will fetch the item inofrmation under the particular caltegory
        created:22/7/2015
    -----------------------------------------------------*/
    public function fetchProduct(){
        $this->layout="ajax";
        if($this->request->is('ajax')){
            $itemId=$_POST['item_id'];
            $categoryId=$_POST['categoryId'];
            $storeId=$_POST['storeId'];
            $decrypt_storeId=$this->Session->read('store_id');
            $decrypt_merchantId=$this->Session->read('merchant_id');
            $this->Session->delete('Order.Item');
            $this->Session->delete('CartOffer');
            $this->Session->delete('Offer');
            $this->loadModel('Item');
            $this->loadModel('ItemPrice');
            $this->loadModel('ItemType');
            $this->loadModel('Category');
            $date = date('Y-m-d');
            $this->ItemType->bindModel(
                array('belongsTo' => array(
                    'Type' => array(
                    'className' => 'Type',
                    'foreignKey' => 'type_id',
                    'conditions'=>array('Type.is_active'=>1,'Type.is_deleted'=>0,'Type.store_id'=>$decrypt_storeId)
                )
            )));
            $this->ItemPrice->bindModel(
                array('belongsTo' => array(
                'Size' => array(
                    'className' => 'Size',
                    'foreignKey' => 'size_id',
                    'conditions'=>array('Size.is_active'=>1,'Size.is_deleted'=>0,'Size.store_id'=>$decrypt_storeId)
                )
            )));
            $this->Item->bindModel(
                array('hasMany' => array(
                    'ItemType' => array(
                        'className' => 'ItemType',
                        'foreignKey' => 'item_id',
                        'conditions'=>array('ItemType.is_active'=>1,'ItemType.is_deleted'=>0,'ItemType.store_id'=>$decrypt_storeId)
                    ),'ItemPrice'=>array(
                        'className' => 'ItemPrice',
                        'foreignKey' => 'item_id',
                        'conditions'=>array('ItemPrice.is_active'=>1,'ItemPrice.is_deleted'=>0,'ItemPrice.store_id'=>$decrypt_storeId)
                    ),'Topping'=>array(
                        'className' => 'Topping',
                        'foreignKey' => 'item_id',
                        'conditions'=>array('Topping.is_active'=>1,'Topping.is_deleted'=>0,'Topping.store_id'=>$decrypt_storeId)
                    ),'Offer'=>array(
                        'className' => 'Offer',
                        'foreignKey' => 'item_id',
                        'conditions'=>array('Offer.is_active'=>1,'Offer.is_deleted'=>0, 'Offer.store_id'=>$decrypt_storeId,'Offer.offer_start_date <=' => $date,'Offer.offer_end_date >=' => $date)
                    )
                )
            ));

            $productInfo=$this->Item->fetchItemDetail($itemId,$storeId);
            if($productInfo){
                if($productInfo['ItemPrice']){
                    $price_array=array();
                    $price_not_size=array();
                    foreach($productInfo['ItemPrice'] as $product){
                        if($product['Size']){
                            $price_array[]=$product['price'];
                        }else{
                            $price_not_size[]=$product['price'];
                        }
                    }
                    if($price_array){
                        $default_price=$price_array['0'];
                    }else{
                        $default_price=$price_not_size['0'];
                    }
                }

                $itemId=$productInfo['Item']['id'];
                $itemName=$productInfo['Item']['name'];
                $categoryId=$productInfo['Item']['category_id'];
                $itemName=$productInfo['Item']['name'];
                $deliver_check=$productInfo['Item']['is_deliverable'];
                $default_quantity=1;
                $this->Session->write('Order.Item.quantity',$default_quantity);
                $this->Session->write('Order.Item.actual_price',$default_price);
                $this->Session->write('Order.Item.is_deliverable',$deliver_check);
                $this->Session->write('Order.Item.id',$itemId);
                $this->Session->write('Order.Item.name',$itemName);
                $this->Session->write('Order.Item.categoryid',$categoryId);
                $this->Session->write('Order.Item.price',$default_price);
                $this->Session->write('Order.Item.final_price',$default_price);
                if($productInfo['Offer']){
                    $itemQuantity = $this->Session->read('Order.Item.quantity');
                    if($itemQuantity == $productInfo['Offer'][0]['unit']){
                        $this->Session->write('Offer',$productInfo['Offer'][0]);
                    }
                }
                $productInfo['Item']['sizeOnly']=$_POST['sizeType'];
                $this->set(compact('productInfo','default_price'));
            }
        }
    }

    /*------------------------------------------------
        Function name:sizePrice()
        Description:To Fetch price based on the size
        created:22/7/2015
    -----------------------------------------------------*/
    
    public function sizePrice(){
        $this->autoRender=false;
        $this->layout="ajax";
        if($this->request->is('ajax')){
            $itemId=$_POST['itemId'];
            $sizeId=$_POST['sizeId'];
            $this->loadModel('ItemPrice');
            $storeId=$this->Session->read('store_id');
            $price=$this->ItemPrice->fetchItemPrice($itemId,$sizeId,$storeId);
            if($price){
                $default_price=$price['ItemPrice']['price'];
                if($this->Session->check('Order.Item.topping_total')){
                    $topping=$this->Session->read('Order.Item.topping_total');
                    if($topping==0.00){
                        $this->Session->delete('Order.Item.topping_total');
                    }else{
                        $default_price=($default_price)+($topping);
                        $default_price=number_format($default_price,2);
                    }
                }
                $this->Session->write('Order.Item.price',$default_price);
                $this->Session->write('Order.Item.final_price',$default_price);
            }
            return $default_price; 
        }
    }
          
    /*------------------------------------------------
        Function name:fetchToppingPrice()
        Description:To Fetch price based on the size
        created:22/7/2015
    -----------------------------------------------------*/
    
    public function fetchToppingPrice(){
        $this->autoRender=false;
        $this->layout="ajax";
        if($this->request->is('ajax')){
            $toppingId=$_POST['toppingId'];
            $itemId=$_POST['itemId'];
            $checked=$_POST['checked'];
            $this->loadModel('Topping');
            $storeId=$this->Session->read('store_id');
            $price=$this->Topping->fetchToppingPrice($itemId,$toppingId,$storeId);
            $topping=array();
            if($price){
                if($checked==1){
                    $item_price=$this->Session->read('Order.Item.price');
                    //--------Here we are making lsit of toppings------------------//
                    $plus_topping=$price['Topping']['price'];
                    $topping_name=$price['Topping']['name'];
                    $topping[$topping_name]=$plus_topping;
                    if($this->Session->check('Order.Item.PaidTopping')){
                        $old_topping=$this->Session->read('Order.Item.PaidTopping');
                        $old_topping[$topping_name]=$plus_topping;
                        $this->Session->write('Order.Item.PaidTopping',$old_topping);//Topping Session(ADD Topping)
                    }else{
                        $this->Session->write('Order.Item.PaidTopping',$topping);//Topping Session
                    }
                   //--------Here we are making calcualtion for topping------------------//
                    if($this->Session->check('Order.Item.topping_total')){
                        $previous=$this->Session->read('Order.Item.topping_total');
                        $topping_total=($previous)+($plus_topping);
                        $topping_total=number_format($topping_total, 2);
                        $price['Topping']['price']=$topping_total;
                        $this->Session->write('Order.Item.topping_total',$topping_total);
                    }
                    $new_price=($item_price)+($plus_topping);
                    $new_price= number_format($new_price, 2);
                }else{
                    $item_price=$this->Session->read('Order.Item.price');
                    $plus_topping=$price['Topping']['price'];
                    $topping_remove=$price['Topping']['name'];
                    if($this->Session->check('Order.Item.PaidTopping')){
                       $this->Session->delete('Order.Item.PaidTopping.'.$topping_remove);
                    }
                    if($this->Session->check('Order.Item.topping_total')){
                        $previous=$this->Session->read('Order.Item.topping_total');
                        $topping_total=($previous)-($plus_topping);
                        $topping_total=number_format($topping_total, 2);
                        $price['Topping']['price']=$topping_total;
                        $this->Session->write('Order.Item.topping_total',$topping_total);
                    }
                    $new_price=($item_price)-($plus_topping);
                    $new_price= number_format($new_price, 2);
                }
                $this->Session->write('Order.Item.price',$new_price);
                $this->Session->write('Order.Item.final_price',$new_price);
                if($this->Session->check('Order.Item.topping_total')){
                    $this->Session->write('Order.Item.topping_total',$topping_total);
                }else{
                    $this->Session->write('Order.Item.topping_total',$plus_topping);
                }
            }else{
                return false;
            }
            return $new_price;
        }
    }
        
        
    /*------------------------------------------------
        Function name:fetchCategoryInfo()
        Description:To Fetch price based on the size
        created:22/7/2015
    -----------------------------------------------------*/
    
    public function fetchCategoryInfo(){
        $this->layout="ajax";
        if($this->request->is('ajax')){
            if($this->Session->check('Order.Item')){
               $this->Session->delete('Order.Item');
            }
            $categoryId=$_POST['categoryId'];
            $storeId=$_POST['storeId'];
            $this->Session->write('Order.Item.category_id',$categoryId);// It will write the session of item 
            $this->loadModel('Category');
            $storeId=$this->Session->read('store_id');
            $category_result=$this->Category->getCategorySizeType($categoryId,$storeId);
            if(isset($category_result['Category']['imgcat'])){
                $image_name=$category_result['Category']['imgcat'];
                $this->set(compact('image_name','category_result'));
            }
        }
    }
        
    /*------------------------------------------------
        Function name:cart()
        Description:This function will add the items into the cart
        created:5/8/2015
    -----------------------------------------------------*/
    
    public function cart(){          
        $this->layout="ajax";
        if($this->request->is('ajax')){
            $cart_array=array();
            if(@$_POST['data']['Item']){
                if(isset($_POST['data']['Item']) || $_POST['data']['Item']) {
                    $this->Session->write('Order.choice',$_POST['data']['Item']);
               }
            }
            if($_POST){
                if(isset($_POST['data']['Item']['type'])){
                    $this->loadModel('Type');
                    $store_id=$this->Session->read('store_id');
                    $type_id=$this->Type->findTypeName($_POST['data']['Item']['type'],$store_id);
                    $type_name=$type_id['Type']['name'];
                    $this->Session->write('Order.Item.type',$type_name);
                    $this->Session->write('Order.Item.type_id',$_POST['data']['Item']['type']);
                }
                $default_check=array(); //Array for defualt topping 
                $paid_check=array();//Array for paid topping 
                if(isset($_POST['data']['Item']['price'])){
                    $this->loadModel('Size');
                    $store_id=$this->Session->read('store_id');
                    $sizeName=$this->Size->getSizeName($_POST['data']['Item']['price']);
                    $type_name=$sizeName['Size']['size'];
                    $size_id=$_POST['data']['Item']['price'];
                    $this->Session->write('Order.Item.size',$type_name);
                    $this->Session->write('Order.Item.size_id',$size_id);
                }    
                if(isset($_POST['data']['Item']['defaulttoppings']) && $_POST['data']['Item']['defaulttoppings']){  //Default Toppinng 
                   $default_topping=$_POST['data']['Item']['defaulttoppings'];
                   $default_check=array();
                   foreach($default_topping as $default_key=>$default_val){
                        if($default_val){
                            $default_check[]=$default_key;
                        }
                    }
                    $this->Session->write('Order.Item.default_topping',$default_check);
                }
                if(isset($_POST['data']['Item']['toppings'])){//Paid Toppinng 
                    $paid_topping=$_POST['data']['Item']['toppings'];
                    $paid_check=array();
                    foreach($paid_topping as $paid_key=>$paid_value){
                        if($paid_value){
                           $paid_check[]=$paid_key;
                        }
                    }
                    $this->Session->write('Order.Item.paid_topping',$paid_check);
                }
            }
            $order_segment="";
            $preOrderCheck="";
            if($this->Session->read('Order')){ //Here we are checking the Order type 
                $order_segment=$this->Session->read('Order.order_type');
                $preOrderCheck =$this->Session->read('Order.is_preorder');
            }
            if($order_segment==2 || $order_segment==3){
                if($preOrderCheck==0){
                    $orderTime=date('Y-m-d')." ".$this->Session->read('Order.store_pickup_time');
                }else{
                    $order_date=$this->Session->read('Order.store_pickup_date');
                    $order_time=$this->Session->read('Order.store_pickup_time');
                    $orderDate=$this->Dateform->formatDate($order_date);
                    $orderpassedTime=$order_time;
                    $orderTime=$orderDate." ".$orderpassedTime;
                }
                $this->Session->write('Cart.order_time',$orderTime);
            }
            $itemId=$this->Session->read('Order.Item.id');
            $current_order=$this->Session->read('Order');
          
            if($this->Session->read('cart')){
                $old_array=$this->Session->read('cart');
                if($this->Session->read('CartOffer')){
                    $storeOfferArray = array();
                    $offer_array = $this->Session->read('CartOffer');
                    $offerPrice = 0;
                    $offerItemName ='';
                    $prefix = '';
                    $i = 0;
                    foreach($offer_array['OfferDetail'] as $off) {
                        if($offer_array['Offer']['is_fixed_price'] == 1){
                            if($offer_array['Offer']['offerprice'] == 0){
                                $offerPrice = $offerPrice + 0;
                                $rate = 0;
                            } else {
                               $offerPrice = $offerPrice +$offer_array['Offer']['offerprice'];
                               $rate = $offer_array['Offer']['offerprice'];
                            }
                        } elseif ($offer_array['Offer']['is_fixed_price'] == 0){
                            if($off['discountAmt'] == 0){
                                $offerPrice = $offerPrice + 0;
                                $rate = 0;
                            } else {
                                $offerPrice = $offerPrice + $off['discountAmt'];
                                $rate = $off['discountAmt'];
                            }
                        } 
                        if($rate == 0){
                           $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name']; 
                        } else {
                            $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name'].' @ $'.$rate;
                        }
                        $offerItemUnit =  $offer_array['Offer']['unit'];
                        $prefix = '<br/> ';
                        $storeOfferArray[$i]['offer_id'] =  $offer_array['Offer']['id'];
                        $storeOfferArray[$i]['offered_item_id'] =  $off['offerItemID'];
                        $storeOfferArray[$i]['offered_size_id'] =  $off['offerSize'];
                        $storeOfferArray[$i]['quantity'] =  $off['quantity'];
                        $storeOfferArray[$i]['Item_name'] =  $off['Item']['name'];
                        $storeOfferArray[$i]['offer_price'] =  $rate;
                        $i++;
                    }
                    foreach($old_array as $key=>$old){
                        if(($offer_array['Offer']['item_id'] == $old['Item']['id'])){
                            $old_array[$key]['Item']['OfferItemName'] = $offerItemName;
                            $old_array[$key]['Item']['OfferItemPrice'] = $offerPrice;
                            $old_array[$key]['Item']['OfferItemUnit'] = $offerItemUnit;
                            $old_array[$key]['Item']['StoreOffer'] = $storeOfferArray;
                            $old_array[$key]['Item']['final_price'] = $old['Item']['final_price']+$offerPrice;
                        }
                    }
                    $this->Session->delete('CartOffer');
                    $this->Session->write('cart',$old_array);
                } else {
                    $exist_id=array();
                    foreach($old_array as $itemcheck){
                       $exist_id[]=@$itemcheck['Item']['id'];
                    }
                    $old_array[]=$current_order;
                    $this->Session->write('cart',$old_array);
                } 
            }else{
                if($current_order){
                   $cart_array[]=$current_order;
                }
                $this->Session->write('cart',$cart_array);
            }
               
            /*************************Offers********************/

            if($this->Session->check('Offer')){
                $is_offer = $this->Session->read('Offer');
                $this->loadModel('Offer');
                $this->loadModel('OfferDetail');

                $this->OfferDetail->bindModel(
                    array('belongsTo' => array(
                        'Item' => array(
                            'className' => 'Item',
                             'foreignKey' => 'offerItemID',
                         ),
                        'Size' => array(
                            'className' => 'Size',
                            'foreignKey' => 'offerSize',
                        ),
                        'Type' => array(
                            'className' => 'Type',
                            'foreignKey' => 'offerItemType',
                        )
                )));
                $this->Offer->bindModel(
                    array('hasMany' => array(
                        'OfferDetail' => array(
                            'className' => 'OfferDetail',
                             'foreignKey' => 'offer_id',
                         )
                )));
                $getOffer = $this->Offer->getOfferDetails($is_offer['id']);
                $this->Session->delete('Offer');
                $cart_offer = $this->Session->write('CartOffer',$getOffer);
                $this->set(compact('getOffer'));  
            } else { 
                $getOffer = array();
                $this->set(compact('getOffer'));    
            } 
            /*********************************************/
        }   
        $this->loadModel('Store');
        $store_result=$this->Store->fetchStoreDetail($this->Session->read('store_id'));
        $this->Session->write('minprice',$store_result['Store']['minimum_order_price']);
        $final_cart=$this->Session->read('cart');
        $this->set(compact('final_cart'));
    } 
        
    /*------------------------------------------------
        Function name:removeItem()
        Description:It will remove the item from the cart
        created:5/8/2015
    -----------------------------------------------------*/
      
    public function removeItem(){
        if($this->request->is('ajax')){
            $this->Session->delete('cart.'.$_POST['index_id']);
            $final_cart=$this->Session->read('cart');
            if($this->Session->read('CartOffer')){
                $this->Session->delete('CartOffer');
            }
            if(empty($final_cart)){
                $this->Session->delete('Coupon');
            }
            $this->set(compact('final_cart'));
        }
    }
            
    /*------------------------------------------------
        Function name:removeItem()
        Description:It will remove the item from the cart
        created:5/8/2015
    -----------------------------------------------------*/
      
    public function addQuantity(){
        $this->layout=false;
        if($this->request->is('ajax')){
            $item=$_POST['value'];
            $offer_flag = 1;
            $present_item=$this->Session->read('cart.'.$_POST['index_id']);
            if(@$present_item['Item']['OfferItemPrice']){
                if(((($item) % ($present_item['Item']['OfferItemUnit'])) == 0) &&  ($present_item['Item']['OfferItemUnit'] < $item)){
                    $offer_multiply = ($item) / ($present_item['Item']['OfferItemUnit']);
                    $offer_price =  $offer_multiply*$present_item['Item']['OfferItemPrice'];
                    $total=$item * $present_item['Item']['price'];
                    $total = $total + $offer_price;
                    $prefix = '';
                    $offerItemName ='';
                    foreach($present_item['Item']['StoreOffer'] as $key=>$name){
                        if($name['offer_price'] == 0){
                           $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name']; 
                        } else {
                            $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name'].' @ $'.$offer_multiply*$name['offer_price'];
                        }
                        $prefix = '<br/> ';
                        $this->Session->write('cart.'.$_POST['index_id'].'.Item.StoreOffer.'.$key.'.quantity',$offer_multiply);
                    }
                    $this->Session->write('cart.'.$_POST['index_id'].'.Item.OfferItemName',$offerItemName);
                    
                } else if($present_item['Item']['OfferItemUnit'] > $item){
                    $item_price = $present_item['Item']['price'];
                    $total= $item * $item_price;
                    $offer_flag = 0;
                    $this->Session->delete('cart.'.$_POST['index_id'].'.Item.OfferItemUnit');
                    $this->Session->delete('cart.'.$_POST['index_id'].'.Item.OfferItemName');
                    $this->Session->delete('cart.'.$_POST['index_id'].'.Item.OfferItemPrice');
                    $this->Session->delete('cart.'.$_POST['index_id'].'.Item.StoreOffer');
                } else {
                    $offer_flag = 0;
                    $item_price=$present_item['Item']['price'];
                    $total= $item * $item_price;
                    $total = $total + $present_item['Item']['OfferItemPrice'];
                    $offer_multiply = floor(($item) / ($present_item['Item']['OfferItemUnit']));
                    $prefix = '';
                    $offerItemName ='';
                    foreach($present_item['Item']['StoreOffer'] as $key=>$name){
                        if($name['offer_price'] == 0){
                           $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name']; 
                        } else {
                            $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name'].' @ $'.$offer_multiply*$name['offer_price'];
                        }
                        $this->Session->write('cart.'.$_POST['index_id'].'.Item.StoreOffer.'.$key.'.quantity',$offer_multiply);
                        $prefix = '<br/> ';
                    }
                    $this->Session->write('cart.'.$_POST['index_id'].'.Item.OfferItemName',$offerItemName);
                
                }
            } else {
                $item_price=$present_item['Item']['price'];
                $total=$item*$item_price;
            }
            $total=number_format($total,2);
            
            
            $this->Session->write('cart.'.$_POST['index_id'].'.Item.final_price',$total);
            $this->Session->write('cart.'.$_POST['index_id'].'.Item.quantity',$item);

            /*************************Offers********************/
            if($offer_flag == 1){
                $this->loadModel('Offer');
                $this->loadModel('OfferDetail');
                $offer_result = $this->Offer->offerOnItem($present_item['Item']['id']);
                if($offer_result){
                    if($item == $offer_result['Offer']['unit']){
                        $this->Session->write('Offer',$offer_result['Offer']);
                    }
                }
                if($this->Session->check('Offer')){
                    $is_offer = $this->Session->read('Offer');
                    $this->OfferDetail->bindModel(
                        array('belongsTo' => array(
                            'Item' => array(
                                'className' => 'Item',
                                 'foreignKey' => 'offerItemID',
                             ),
                            'Size' => array(
                                'className' => 'Size',
                                'foreignKey' => 'offerSize',
                            ),
                            'Type' => array(
                                'className' => 'Type',
                                'foreignKey' => 'offerItemType',
                            )
                    )));
                    $this->Offer->bindModel(
                          array('hasMany' => array(
                              'OfferDetail' => array(
                                  'className' => 'OfferDetail',
                                   'foreignKey' => 'offer_id',
                               )
                     )));
                    $getOffer = $this->Offer->getOfferDetails($is_offer['id']);
                    $this->Session->delete('Offer');
                    $cart_offer = $this->Session->write('CartOffer',$getOffer);
                    $this->set(compact('getOffer'));  
                } else { 
                    $getOffer = array();
                    $this->set(compact('getOffer'));    
                } 
            }
            /*********************************************/
            $final_cart=$this->Session->read('cart');
            $this->set(compact('final_cart'));
        }
    }
   
    /*------------------------------------------------
        Function name:orderDetails()
        Description:It will show the whole details of the order to be made
        created:5/8/2015
    ----------------------------------------------------*/
      
    public function orderDetails(){
        $this->layout="customer_dashboard";
        $decrypt_storeId=$this->Session->read('store_id');
        $decrypt_merchantId=$this->Session->read('merchant_id');
        $encrypted_storeId=$this->Encryption->encode($decrypt_storeId); // Encrypted Store Id
        $encrypted_merchantId=$this->Encryption->encode($decrypt_merchantId);// Encrypted Merchant Id
        if($this->Session->check('cart') && $_SESSION['cart']){
            $this->loadModel('Store');
            $store_result=$this->Store->fetchStoreDetail($this->Session->read('store_id'));
            if(isset($store_result['Store']['service_fee'])){
                 $this->Session->write('service_fee',$store_result['Store']['service_fee']);
            }
            if(isset($store_result['Store']['delivery_fee'])){
               $this->Session->write('delivery_fee',$store_result['Store']['delivery_fee']);
            }
            $finalItem=$this->Session->read('cart');
            $encrypted_storeId=$this->Encryption->encode($decrypt_storeId); // Encrypted Store Id
            $encrypted_merchantId=$this->Encryption->encode($decrypt_merchantId);// Encrypted Merchant Id              
            $this->set(compact('finalItem'));
            $this->loadModel('Category');
            $this->Category->bindModel(
                array('hasMany' => array(
                        'Item' => array(
                            'className' => 'Item',
                            'foreignKey' => 'category_id',
                            'fields'=>array('id','name','category_id','start_date','end_date','image','description','is_seasonal_item'),
                            'conditions'=>array('Item.is_active'=>1,'Item.is_deleted'=>0)
                        )
                    )
            ));
            $total_price=0;
            $delivery_address_id="";
            foreach($finalItem as $total){
                $segment_type=$total['order_type'];
                if(@$total['delivery_address_id']){
                   $delivery_address_id=$total['delivery_address_id'];
                }
                $total_price=$total_price+$total['Item']['final_price'];
            }
            $userid="";
            $delivery_address="";
            if($delivery_address_id){
                $this->loadModel('DeliveryAddress');
                $delivery_address=$this->DeliveryAddress->fetchAddress($delivery_address_id,$userid,$decrypt_storeId);
               }
            $service_fee="";
            $service_fee=$this->Session->read('service_fee');
            if($service_fee){
               $total_price=$total_price+$service_fee;
            }
            $delivery_fee="";
            $delivery_fee=$this->Session->read('delivery_fee');
            if($delivery_fee){
               $total_price=$total_price+$delivery_fee;
            }
            $total_price=number_format($total_price,2);
            $this->Session->write('Cart.grand_total_final',$total_price); // It will give the final totoal with all taxes
            $this->Session->write('Cart.segment_type',$segment_type);
            $categoryList=$this->Category->findCategotyList($decrypt_storeId,$decrypt_merchantId); // It will find the list of categories of the menus
            if($categoryList){
               $this->set(compact('categoryList','finalItem','decrypt_storeId','decrypt_merchantId','encrypted_storeId','encrypted_merchantId','delivery_address')); 
            }
        }else{
            $this->redirect(array('contoller'=>'products','action'=>'items',$encrypted_storeId,$encrypted_merchantId));
        }   
    }
      
    /*------------------------------------------------
        Function name:cancelOffer()
        Description:It will remove the offer cycle
        created:14/8/2015
    -----------------------------------------------------*/
      
    public function cancelOffer(){
        if($this->request->is('ajax')){
            if($this->Session->read('CartOffer')){
                $this->Session->delete('CartOffer');
            }
        }
    }
    
    /*------------------------------------------------
        Function name:reorder()
        Description: Used for re-order cycle
        created:17/8/2015
    -----------------------------------------------------*/
      
    public function reorder(){
        $this->layout= false;
        $this->autoRender = false;
        $decrypted_orderId=$this->Encryption->decode($_POST['orderId']);
        $this->loadModel('Order');
        $this->loadModel('OrderTopping');
        $this->loadModel('OrderItem');
        $this->loadModel('Item');
        $this->loadModel('ItemPrice');
        $this->loadModel('ItemType');
        $this->loadModel('Topping');
        $this->loadModel('OrderOffer');
        $this->OrderTopping->bindModel(array('belongsTo'=>array('Topping'=>array('fields'=>array('id')))), false);
        $this->OrderItem->bindModel(array('hasMany'=>array('OrderOffer'=>array('fields'=>array('id')),'OrderTopping'=>array('fields'=>array('id','topping_id'))),'belongsTo' => array('Item'=>array('foreignKey'=>'item_id','fields'=>array('id')),'Type'=>array('foreignKey'=>'type_id','fields'=>array('id','name')),'Size'=>array('foreignKey'=>'size_id','fields'=>array('id','size')))), false);
        $this->Order->bindModel(array('hasMany' => array('OrderItem'=>array('fields'=>array('id','quantity','order_id','user_id','type_id','item_id','size_id')))), false);
        $myOrders = $this->Order->getOrderById($decrypted_orderId);
        $count = 0;
        $activeItem = 0;
           foreach($myOrders['OrderItem'] as $order){ 
                $this->Item->bindModel(
                    array('hasMany' => array(
                            'ItemPrice' => array()
                )));
               $ordItem = $this->Item->getItemById($order['Item']['id']);
               if(empty($ordItem)){
                   $activeItem = 1;
               } else {
                   if($ordItem['Item']['is_seasonal_item'] == 1){
                       $date = date('Y-m-d');
                       if(($ordItem['Item']['start_date'] <= $date) && ($ordItem['Item']['end_date'] >= $date)){
                           $activeItem = 1;
                       }
                   } else {
                        if(!empty($order['OrderOffer'])){
                           $SessionItem[$count]['isOffer'] =  1;
                        } else {
                            $SessionItem[$count]['isOffer'] =  0;
                        }
                        $SessionItem[$count]['itemId'] =  $ordItem['Item']['id'];
                        $SessionItem[$count]['categoryId'] =  $ordItem['Item']['category_id'];
                        $SessionItem[$count]['itemName'] =  $ordItem['Item']['name'];
                        $SessionItem[$count]['isDeliverable'] =  $ordItem['Item']['is_deliverable'];
                        $SessionItem[$count]['price'] =  $ordItem['ItemPrice'][0]['price'];
                        $SessionItem[$count]['quantity'] =  $order['quantity'];
                        if(!empty($order['Size'])){
                            $ordSize = $this->ItemPrice->getSizeById($order['Size']['id'],$order['Item']['id']);
                            if(empty($ordSize)){
                                $activeItem = 1;
                                $SessionItem[$count]['sizeId'] =  0;
                            } else {
                                $SessionItem[$count]['sizeId'] =  $ordSize['ItemPrice']['id'];
                                $SessionItem[$count]['price'] =  $ordSize['ItemPrice']['price'];
                            }
                            $SessionItem[$count]['sizeeId'] =  $order['Size']['id'];
                            $SessionItem[$count]['sizeeName'] = $order['Size']['size'];
                        } else {
                            $SessionItem[$count]['sizeId'] =  0;
                            $SessionItem[$count]['sizeeId'] =  0;
                            $SessionItem[$count]['sizeeName'] = 0;
                        }
                        if(!empty($order['Type'])){
                            $ordType = $this->ItemType->getTypeById($order['Type']['id'],$order['Item']['id']);
                            if(empty($ordType)){
                                $activeItem = 1;
                                $SessionItem[$count]['typeId'] =  0;
                                $SessionItem[$count]['typeName'] =  0;
                            } else {
                                $SessionItem[$count]['typeId'] =  $order['Type']['id'];
                                $SessionItem[$count]['typeName'] =  $order['Type']['name'];
                            }
                        } else {
                            $SessionItem[$count]['typeId'] =  0;
                            $SessionItem[$count]['typeName'] =  0;
                        }
                        $top_count = 0;
                        if(!empty($order['OrderTopping'])){
                            foreach($order['OrderTopping'] as $topping){
                                $topType = $this->Topping->getToppingById($topping['Topping']['id'],$order['Item']['id']);
                                if(empty($topType)){
                                   $activeItem = 1;
                                   $SessionItem[$count]['Topping'][$top_count]['topId'] = 0;
                                   $SessionItem[$count]['Topping'][$top_count]['topPrice'] = 0;
                                   $SessionItem[$count]['Topping'][$top_count]['topName'] = 0;
                                } else {
                                   $SessionItem[$count]['Topping'][$top_count]['topId'] =  $topType['Topping']['id'];
                                   $SessionItem[$count]['Topping'][$top_count]['topPrice'] =  $topType['Topping']['price'];
                                   $SessionItem[$count]['Topping'][$top_count]['topName'] =  $topType['Topping']['name'];
                                }
                                $top_count++;
                            }
                        } else {
                            $SessionItem[$count]['Topping'][$top_count]['topId'] = 0;
                            $SessionItem[$count]['Topping'][$top_count]['topPrice'] = 0;
                            $SessionItem[$count]['Topping'][$top_count]['topName'] = 0;
                        }
                        $count++;
                   }
               }
               
               
           }
        $this->Session->write('reOrder',$SessionItem);
        $data['item'] = $activeItem;
        $data['count'] = $count;
        return json_encode($data);
          
    }
    
    /*------------------------------------------------
        Function name:fetchReorderProduct()
        Description: Used for re-order cycle
        created:17/8/2015
    -----------------------------------------------------*/
      
    public function fetchReorderProduct(){
        $this->layout = false;
        $this->autoRender = false;
        if($this->request->is('ajax')){
            if($this->Session->read('reOrder')){ 
                $data = $this->Session->read('reOrder');
                $count = 0;
               
                foreach($data as $redata){
                    $this->Session->delete('Order.Item');
                    $itemId = $redata['itemId'];
                    $categoryId = $redata['categoryId'];
                    $itemName = $redata['itemName'];
                    $deliver_check = $redata['isDeliverable'];
                    $default_price = $redata['price'];
                    $default_quantity= 1;
                    $this->Session->write('Order.Item.quantity',$default_quantity);
                    $this->Session->write('Order.Item.actual_price',$default_price);
                    $this->Session->write('Order.Item.is_deliverable',$deliver_check);
                    $this->Session->write('Order.Item.id',$itemId);
                    $this->Session->write('Order.Item.name',$itemName);
                    $this->Session->write('Order.Item.categoryid',$categoryId);
                    $this->Session->write('Order.Item.price',$default_price);
                    $this->Session->write('Order.Item.final_price',$default_price);

                    $topping=array();
                    $top_array = array();
                    foreach($redata['Topping'] as $top){
                        $top_array[$top['topId']] = $top['topId'];
                        $item_price = $this->Session->read('Order.Item.price');

                        $plus_topping = $top['topPrice'];
                        $topping_name = $top['topName'];
                        $topping[$topping_name] = $plus_topping;
                        if($this->Session->check('Order.Item.PaidTopping')){
                            $old_topping = $this->Session->read('Order.Item.PaidTopping');
                            $old_topping[$topping_name] = $plus_topping;
                            $this->Session->write('Order.Item.PaidTopping',$old_topping);//Topping Session(ADD Topping)
                        }else{
                            $this->Session->write('Order.Item.PaidTopping',$topping);//Topping Session
                        }
                        if($this->Session->check('Order.Item.topping_total')){
                            $previous = $this->Session->read('Order.Item.topping_total');
                            $topping_total=($previous)+($plus_topping);
                            $topping_total = number_format($topping_total, 2);
                            $this->Session->write('Order.Item.topping_total',$topping_total);

                        }
                        $new_price = ($item_price)+($plus_topping);
                        $new_price = number_format($new_price, 2);


                        $this->Session->write('Order.Item.price',$new_price);
                        $this->Session->write('Order.Item.final_price',$new_price);
                        if($this->Session->check('Order.Item.topping_total')){
                            $this->Session->write('Order.Item.topping_total',$topping_total);
                        }else{
                            $this->Session->write('Order.Item.topping_total',$plus_topping);
                        }
                    }

                    if($this->Session->check('Order.Item.topping_total')){
                         $topping=$this->Session->read('Order.Item.topping_total');
                         if($topping==0.00){
                            $this->Session->delete('Order.Item.topping_total');
                         }else{
                            $default_price=($default_price)+($topping);
                            $default_price=number_format($default_price,2);
                         }
                    }

                    $this->Session->write('Order.Item.price',$default_price);
                    $this->Session->write('Order.Item.final_price',$default_price);
                    
                    if($redata['isOffer'] == 1){
                        $this->loadModel('Offer');
                        $this->loadModel('OfferDetail');
                        $offer_result = $this->Offer->offerOnItem($itemId);
                        if($offer_result){
                            if($redata['quantity'] >= $offer_result['Offer']['unit']){
                                $this->Session->write('Offer',$offer_result['Offer']);
                            }
                        }
                        if($this->Session->check('Offer')){
                            $is_offer = $this->Session->read('Offer');
                            $this->OfferDetail->bindModel(
                                array('belongsTo' => array(
                                    'Item' => array(
                                        'className' => 'Item',
                                         'foreignKey' => 'offerItemID',
                                     ),
                                    'Size' => array(
                                        'className' => 'Size',
                                        'foreignKey' => 'offerSize',
                                    ),
                                    'Type' => array(
                                        'className' => 'Type',
                                        'foreignKey' => 'offerItemType',
                                    )
                            )));
                            $this->Offer->bindModel(
                                  array('hasMany' => array(
                                      'OfferDetail' => array(
                                          'className' => 'OfferDetail',
                                           'foreignKey' => 'offer_id',
                                       )
                             )));
                            $getOffer = $this->Offer->getOfferDetails($is_offer['id']);
                            $this->Session->delete('Offer');
                            $cart_offer = $this->Session->write('CartOffer',$getOffer);  
                        }
                    }
                            


                    $cart_array=array();
                    if($itemId){
                        if(isset($itemId) || $itemId) {
                            $this->Session->write('Order.choice',$itemId);
                        } 
                    }
                    if($redata['typeId'] != 0){
                        $this->Session->write('Order.Item.type',$redata['typeName']);
                        $this->Session->write('Order.Item.type_id',$redata['typeId']);
                    }
                    $default_check=array(); //Array for defualt topping 
                    $paid_check=array();//Array for paid topping 
                    if($redata['sizeeId'] != 0){
                        $this->Session->write('Order.Item.size',$redata['sizeeName']);
                        $this->Session->write('Order.Item.size_id',$redata['sizeeId']);
                    }
                    if(!empty($top_array)){//Paid Toppinng 
                       $paid_topping = $top_array;
                       $paid_check=array();
                       foreach($paid_topping as $paid_key=>$paid_value){
                            if($paid_value){
                               $paid_check[]=$paid_key;
                            }
                        }
                        $this->Session->write('Order.Item.paid_topping',$paid_check);
                    }
                    $order_segment="";
                    $preOrderCheck="";

                    if($this->Session->read('Order')){ //Here we are checking the Order type 
                        $order_segment=$this->Session->read('Order.order_type');
                        $preOrderCheck =$this->Session->read('Order.is_preorder');
                    }

                    if($order_segment==2 || $order_segment==3){
                        if($preOrderCheck==0){
                            $orderTime=date('Y-m-d')." ".$this->Session->read('Order.store_pickup_time');
                        }else{
                            $order_date=$this->Session->read('Order.store_pickup_date');
                            $order_time=$this->Session->read('Order.store_pickup_time');
                            $orderDate=$this->Dateform->formatDate($order_date);
                            $orderpassedTime=$order_time;
                            $orderTime=$orderDate." ".$orderpassedTime;
                        }
                        $this->Session->write('Cart.order_time',$orderTime);
                    }
                    $current_order=$this->Session->read('Order');

                    if($this->Session->read('cart')){
                        $old_array=$this->Session->read('cart');
                        $exist_id=array();
                        foreach($old_array as $itemcheck){
                           $exist_id[]=@$itemcheck['Item']['id'];
                        }
                        $old_array[]=$current_order;
                        $this->Session->write('cart',$old_array);
                        if($this->Session->read('CartOffer')){
                            $storeOfferArray = array();
                            $offer_array = $this->Session->read('CartOffer');
                            $offerPrice = 0;
                            $offerItemName ='';
                            $prefix = '';
                            $i = 0;
                            foreach($offer_array['OfferDetail'] as $off) {
                                if($offer_array['Offer']['is_fixed_price'] == 1){
                                    if($offer_array['Offer']['offerprice'] == 0){
                                        $offerPrice = $offerPrice + 0;
                                        $rate = 0;
                                    } else {
                                       $offerPrice = $offerPrice +$offer_array['Offer']['offerprice'];
                                       $rate = $offer_array['Offer']['offerprice'];
                                    }
                                } elseif ($offer_array['Offer']['is_fixed_price'] == 0){
                                    if($off['discountAmt'] == 0){
                                        $offerPrice = $offerPrice + 0;
                                        $rate = 0;
                                    } else {
                                        $offerPrice = $offerPrice + $off['discountAmt'];
                                        $rate = $off['discountAmt'];
                                    }
                                } 
                                if($rate == 0){
                                   $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name']; 
                                } else {
                                    $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name'].' @ $'.$rate;
                                }
                                $offerItemUnit =  $offer_array['Offer']['unit'];
                                $prefix = '<br/> ';
                                $storeOfferArray[$i]['offer_id'] =  $offer_array['Offer']['id'];
                                $storeOfferArray[$i]['offered_item_id'] =  $off['offerItemID'];
                                $storeOfferArray[$i]['offered_size_id'] =  $off['offerSize'];
                                $storeOfferArray[$i]['quantity'] =  $off['quantity'];
                                $storeOfferArray[$i]['Item_name'] =  $off['Item']['name'];
                                $storeOfferArray[$i]['offer_price'] =  $rate;
                                $i++;
                            }
                            foreach($old_array as $key=>$old){
                                if(($offer_array['Offer']['item_id'] == $old['Item']['id'])){
                                    $old_array[$key]['Item']['OfferItemName'] = $offerItemName;
                                    $old_array[$key]['Item']['OfferItemPrice'] = $offerPrice;
                                    $old_array[$key]['Item']['OfferItemUnit'] = $offerItemUnit;
                                    $old_array[$key]['Item']['StoreOffer'] = $storeOfferArray;
                                    $old_array[$key]['Item']['final_price'] = $old['Item']['final_price']+$offerPrice;
                                }
                            }
                            $this->Session->delete('CartOffer');
                            $this->Session->write('cart',$old_array);
                        } 
                    }else{
                        if($current_order){
                            $cart_array[]=$current_order;
                        }
                        $this->Session->write('cart',$cart_array);
                        $old_array = $this->Session->read('cart');
                        if($this->Session->read('CartOffer')){
                            $storeOfferArray = array();
                            $offer_array = $this->Session->read('CartOffer');
                            $offerPrice = 0;
                            $offerItemName ='';
                            $prefix = '';
                            $i = 0;
                            foreach($offer_array['OfferDetail'] as $off) {
                                if($offer_array['Offer']['is_fixed_price'] == 1){
                                    if($offer_array['Offer']['offerprice'] == 0){
                                        $offerPrice = $offerPrice + 0;
                                        $rate = 0;
                                    } else {
                                       $offerPrice = $offerPrice +$offer_array['Offer']['offerprice'];
                                       $rate = $offer_array['Offer']['offerprice'];
                                    }
                                } elseif ($offer_array['Offer']['is_fixed_price'] == 0){
                                    if($off['discountAmt'] == 0){
                                        $offerPrice = $offerPrice + 0;
                                        $rate = 0;
                                    } else {
                                        $offerPrice = $offerPrice + $off['discountAmt'];
                                        $rate = $off['discountAmt'];
                                    }
                                } 
                                if($rate == 0){
                                   $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name']; 
                                } else {
                                    $offerItemName .=  $prefix.$off['quantity'].' X '.$off['Item']['name'].' @ $'.$rate;
                                }
                                $offerItemUnit =  $offer_array['Offer']['unit'];
                                $prefix = '<br/> ';
                                $storeOfferArray[$i]['offer_id'] =  $offer_array['Offer']['id'];
                                $storeOfferArray[$i]['offered_item_id'] =  $off['offerItemID'];
                                $storeOfferArray[$i]['offered_size_id'] =  $off['offerSize'];
                                $storeOfferArray[$i]['quantity'] =  $off['quantity'];
                                $storeOfferArray[$i]['Item_name'] =  $off['Item']['name'];
                                $storeOfferArray[$i]['offer_price'] =  $rate;
                                $i++;
                            }
                            foreach($old_array as $key=>$old){
                                if(($offer_array['Offer']['item_id'] == $old['Item']['id'])){
                                    $old_array[$key]['Item']['OfferItemName'] = $offerItemName;
                                    $old_array[$key]['Item']['OfferItemPrice'] = $offerPrice;
                                    $old_array[$key]['Item']['OfferItemUnit'] = $offerItemUnit;
                                    $old_array[$key]['Item']['StoreOffer'] = $storeOfferArray;
                                    $old_array[$key]['Item']['final_price'] = $old['Item']['final_price']+$offerPrice;
                                }
                            }
                            $this->Session->delete('CartOffer');
                            $this->Session->write('cart',$old_array);
                        }
                    } 
                    $item = $redata['quantity'];
                    $present_item = $this->Session->read('cart.'.$count);
                    if(@$present_item['Item']['OfferItemPrice']){
                        if(((($item) % ($present_item['Item']['OfferItemUnit'])) == 0) &&  ($present_item['Item']['OfferItemUnit'] < $item)){
                            $offer_multiply = ($item) / ($present_item['Item']['OfferItemUnit']);
                            $offer_price =  $offer_multiply*$present_item['Item']['OfferItemPrice'];
                            $total=$item * $present_item['Item']['price'];
                            $total = $total + $offer_price;
                            $prefix = '';
                            $offerItemName ='';
                            foreach($present_item['Item']['StoreOffer'] as $key=>$name){
                                if($name['offer_price'] == 0){
                                   $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name']; 
                                } else {
                                    $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name'].' @ $'.$offer_multiply*$name['offer_price'];
                                }
                                $prefix = '<br/> ';
                                $this->Session->write('cart.'.$count.'.Item.StoreOffer.'.$key.'.quantity',$offer_multiply);
                            }
                            $this->Session->write('cart.'.$count.'.Item.OfferItemName',$offerItemName);

                        } else if($present_item['Item']['OfferItemUnit'] > $item){
                            $item_price = $present_item['Item']['price'];
                            $total= $item * $item_price;
                            $this->Session->delete('cart.'.$count.'.Item.OfferItemUnit');
                            $this->Session->delete('cart.'.$count.'.Item.OfferItemName');
                            $this->Session->delete('cart.'.$count.'.Item.OfferItemPrice');
                            $this->Session->delete('cart.'.$count.'.Item.StoreOffer');
                        } else {
                            $item_price=$present_item['Item']['price'];
                            $total= $item * $item_price;
                            $total = $total + $present_item['Item']['OfferItemPrice'];
                            $offer_multiply = floor(($item) / ($present_item['Item']['OfferItemUnit']));
                            $prefix = '';
                            $offerItemName ='';
                            foreach($present_item['Item']['StoreOffer'] as $key=>$name){
                                if($name['offer_price'] == 0){
                                   $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name']; 
                                } else {
                                    $offerItemName .=  $prefix.$offer_multiply.' X '.$name['Item_name'].' @ $'.$offer_multiply*$name['offer_price'];
                                }
                                $this->Session->write('cart.'.$count.'.Item.StoreOffer.'.$key.'.quantity',$offer_multiply);
                                $prefix = '<br/> ';
                            }
                                $this->Session->write('cart.'.$count.'.Item.OfferItemName',$offerItemName);
                
                            }
                        } else {
                            $item_price = $present_item['Item']['price'];
                            $total = $item*$item_price;
                        }
                        $total=number_format($total,2);


                        $this->Session->write('cart.'.$count.'.Item.final_price',$total);
                        $this->Session->write('cart.'.$count.'.Item.quantity',$item);

                    $count++;  
                    $final_cart = $this->Session->read('cart');
                }

                $this->loadModel('Store');
                $store_result=$this->Store->fetchStoreDetail($this->Session->read('store_id'));
                $this->Session->write('minprice',$store_result['Store']['minimum_order_price']);
                $final_cart = $this->Session->read('cart');
                $this->Session->delete('reOrder');
                echo 1;
            }
        }
    }  
    
    /*------------------------------------------------
        Function name:fetchCoupon()
        Description: Used for coupon cycle
        created:20/8/2015
    -----------------------------------------------------*/
    
    public function fetchCoupon(){
        $this->layout = false;
        $this->layout="ajax";
        if($this->request->is('ajax')){
            $this->Session->delete('Coupon');
            $couponCode = $_POST['coupon_code'];
            if(empty($couponCode)){
                $coupon_data = 1;
            } else {
                $this->loadModel('Coupon');
                $storeId = $this->Session->read('store_id');
                $coupon = $this->Coupon->getValidCoupon($couponCode,$storeId);
                if($coupon){
                    if($coupon['Coupon']['number_can_use'] > $coupon['Coupon']['used_count']){
                        $this->Session->write('Coupon',$coupon);
                        $coupon_data = 3;
                        $this->set(compact('final_cart'));
                    } else {
                        $coupon_data = 2;
                    }
                } else {
                    $coupon_data = 1;
                } 
            }
            $final_cart=$this->Session->read('cart');
            $this->set(compact('final_cart','coupon_data'));
        }
    }
    

}?>
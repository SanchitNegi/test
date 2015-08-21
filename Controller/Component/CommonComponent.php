<?php
/**
 * Custom component
 *
 * PHP 5
 *
 * Created By         :Navdeep kaur
 * Date Of Creation   : 23 Oct 2013
 * 
 */

App::uses('Component', 'Controller');

/**
 * Custom component
 */
class CommonComponent extends Component {
    
/**
 * This component uses the component
 *
 * @var array
 */    
    var $components = array('Cookie','Session','Email','Upload','Categories.Easyphpthumbnail');
    
/*
 * Function to generate the random password
 */
    public function getRandPass() {
        
        // Array Declaration
        $pass = array();
        
        // Variable declaration
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for($i = 0; $i < 8; $i++){
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
    
/**
 * Upload Original Image
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        image_upload
 * @param         $file, $path, $folder_name, $thumb, $multiple
 * @return        $filename or $err_type
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function upload_image($file, $path, $folder_name, $thumb = false, $multiple = array()){
       
        // Variable containing File type
        $extType = $file['type'];
         
        // Variable containing extension in lowercase 
        $ext = strtolower($extType);				
         
        // Condition checking File extension
        if($ext=='image/jpg' || $ext=='image/png' || $ext=='image/jpeg' || $ext=='image/gif'){					
         
            // Condition checking File size
            if($file['size'] <= 10485760){					
            
                // Filename 
                $filename = time().'_'.$file['name'];
                
                // Folder path
                $folder_url = APP.$path.'/'.$folder_name;
              //  echo $folder_url; die;
                // Condition checking File exist or not 
                if (!file_exists($folder_url.'/'.$filename)){
                   
                    // create full filename
                    $full_url = $folder_url.'/'.$filename;			
                  
                    // upload the file					
                     $success = move_uploaded_file($file['tmp_name'], $full_url);
                 
                    //
                    if($thumb){
                        // If multiple folder upload required then pass TRUE as last parameter
                        $this->upload_thumb_image($filename, $path, $folder_name, $multiple);
                    }
                     
                    return $filename;
                }else{
                    return 'exist_error';
                }
            }else{
                return 'size_mb_error';
            }
        }else{
            return 'type_error';
        }
    }
   
/**
 * Upload Thumb Image
 * @author        Anuj Kumar
 * @copyright     smartData Enterprise Inc.
 * @method        upload_thumb_image
 * @param         $filename, $path, $folder_name, $multiple
 * @return        void
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function upload_thumb_image($filename, $path, $folder_name, $multiple = array()){
        
        // image path from where pic taken
        $dircover = str_replace(chr(92),chr(47),APP).'/'.$path.'/'.$folder_name.'/'.$filename;
        if(!empty($multiple) && count($multiple)> 0){
        	foreach($multiple as $result){
        		$this->Easyphpthumbnail-> Thumblocation = str_replace(chr(92),chr(47),APP).'/'.$path.'/'.$result['folder_name'].'/'; 
        		$this->Easyphpthumbnail-> Thumbheight = $result['height'];
        		$this->Easyphpthumbnail-> Thumbwidth =  $result['width'];
        		$this->Easyphpthumbnail-> Createthumb($dircover,'file');
        	}
        }
    }    
    
    
/**
 * Handle image errors
 * @author        Anuj Kumar
 * @copyright     smartData Enterprise Inc.
 * @method        is_image_error
 * @param         $image_name
 * @return        error msg
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function is_image_error($image_name = null){
        $errmsg = '';
        switch($image_name){
            case 'exist_error':
                $errmsg = 'File already exist.';
                break;
            
            case 'size_mb_error':
                $errmsg = 'Only mb of file is allowed to upload.';
                break;
            
            case 'type_error':
                $errmsg = 'Only JPG, JPEG, PNG & GIF are allowed.';
                break;
        }
        return $errmsg;
    }
/**
 * Delete image
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        delete_image
 * @param         $image_name, $path, $thumb_path
 * @return        void
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function delete_image($imagename = null, $path = null, $folder_name = null, $thumb = false, $multiple = array()){
        
        if(!empty($path)){
            $full_path = WWW_ROOT.$path.'/'.$folder_name.'/'.$imagename;
            if(file_exists($full_path)){
                unlink($full_path);
            }
            
            if($thumb){
                if(!empty($multiple) && count($multiple)> 0){
                    foreach($multiple as $result){
                        $full_thumb_path = WWW_ROOT.$path.'/'.$result['folder_name'].'/'.$imagename;
                        if(file_exists($full_thumb_path)){
                            unlink($full_thumb_path);
                        }
                    }
                }
            }
            
        }
    }
    

    
/**
 * Upload Video
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        upload_video
 * @param         $file, $path
 * @return        $filename or $err_type
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function upload_video($file, $path){
        
        // Variable containing File type
        $extType = end(explode('.',$file['name']));
         
        // Variable containing extension in lowercase 
        $ext = strtolower($extType);
        
        // Condition checking File extension
        if($ext=='mov' || $ext=='avi' || $ext=='wmv' || $ext=='dat' || $ext=='mpeg' || $ext=='mpg' || $ext=='flv' || $ext=='mp4' || $ext=='mp2'){
            
            // Condition checking File size
            if($file['size'] <= 10485760){
                
                // Array Declaration
                $arrVideo = array();
                
                // Filename without extension
                $filename_without_ext = preg_replace('/\.[a-z0-9]+$/i','',$file['name']);
                
                // New filename
                $new_filename = time().'_'.$filename_without_ext;
                
                // Filename 
                $original_filename = $new_filename.'.'.$ext;
                $converted_filename = $new_filename.'.flv';
                $thumb_filename = $new_filename.'.jpg';
                
                // Folder path
                $path_original_video = WWW_ROOT.$path.'/'.$original_filename;
                $path_converted_video = WWW_ROOT.$path.'/'.$converted_filename;
                $path_converted_video_thumb = WWW_ROOT.$path.'/thumb/'.$thumb_filename;
                
                // Condition checking File exist or not 
                if (!file_exists($path_original_video)){
                   
                    // create full filename
                    $full_url = $path_original_video;			
                  
                    // upload the file					
                    if(move_uploaded_file($file['tmp_name'], $full_url)){
                 
                        // The first this we need to do is convert the video
                        $this->VideoEncoder->convert_video($path_original_video, $path_converted_video, 480, 360);
                        
                        // Then we need to set the buffer on the converted video
                        $this->VideoEncoder->set_buffering($path_converted_video);
                       
                        // We can also grab a screenshot from the video as a jpeg and store it for future use.
                        $this->VideoEncoder->grab_image($path_converted_video, $path_converted_video_thumb);
                        
                        if($ext != 'flv'){
                            // Finally we can delete the original video
                            $this->VideoEncoder->remove_uploaded_video($path_original_video);
                        }
                            
                        $arrVideo = array('0'=>$converted_filename,'1'=>$thumb_filename);
                        return $arrVideo;
                    
                    }else{
                        return 'some_error';   
                    }
                }else{
                    return 'exist_error';
                }
            }else{
                return 'size_mb_error';
            }
        }else{
            return 'type_error';
        }
    }
    
/**
 * Handle image errors
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        is_video_error
 * @param         array()
 * @return        error msg
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function is_video_error($arr = array()){
        $errmsg = '';
        if(!empty($arr) && count($arr) > 0){
            switch($arr[0]){
                case 'some_error':
                    $errmsg = 'Some error occured while uploading video. Please try again.';
                    break;
                
                case 'exist_error':
                    $errmsg = 'File already exist.';
                    break;
                
                case 'size_mb_error':
                    $errmsg = 'Only mb of file is allowed to upload.';
                    break;
                
                case 'type_error':
                    $errmsg = 'Only JPG, JPEG, PNG & GIF are allowed.';
                    break;
            }
        }
        return $errmsg;
    }
    
/**
 * Upload Document
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        upload_document
 * @param         $file, $path
 * @return        $filename or $err_type
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function upload_document($file, $path){
        
        // Variable containing File type
        $extType = end(explode('.',$file['name']));
        
        // Variable containing extension in lowercase 
        $ext = strtolower($extType);
        
        // Condition checking File extension
        if($ext=='xls' || $ext=='doc' || $ext=='docx' || $ext=='pdf' || $ext=='txt'){
            
            // Condition checking File size
            if($file['size'] <= 10485760){                
                // Filename 
                $filename = time().'_'.$file['name'];
                
                // Folder path
                $folder_url = WWW_ROOT.$path.'/'.$filename;
                
                // Condition checking File exist or not 
                if (!file_exists($folder_url)){
                   
                    // create full filename
                    $full_url = $folder_url;			
                  
                    // upload the file					
                    if(move_uploaded_file($file['tmp_name'], $full_url)){
                        return $filename;
                    }else{
                        return 'some_error';   
                    }
                }else{
                    return 'exist_error';
                }
            }else{
                return 'size_mb_error';
            }
        }else{
            return 'type_error';
        }
    }
    
/**
 * Handle image errors
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        is_document_error
 * @param         $document_name
 * @return        error msg
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function is_document_error($document_name = null){
        $errmsg = '';
        switch($document_name){
            case 'some_error':
                $errmsg = 'Some error occured while uploading document. Please try again.';
                break;
            
            case 'exist_error':
                $errmsg = 'File already exist.';
                break;
            
            case 'size_mb_error':
                $errmsg = 'Only 10 mb of file is allowed to upload.';
                break;
            
            case 'type_error':
                $errmsg = 'Only TXT, PDF, DOC, DOCX & XLS are allowed.';
                break;
        }
        return $errmsg;
    }
    
/**
 * Delete image
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        delete_image
 * @param         $image_name, $path, $thumb_path
 * @return        void
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function delete_document($filename, $path){
        if(!empty($filename) && !empty($path)){
            $full_path = WWW_ROOT.$path.'/'.$filename;
            if(file_exists($full_path)){
                unlink($full_path);
            }
        }
    }
    
/**
 * Download file
 * @author       Navdeep kaur
 * @copyright     smartData Enterprise Inc.
 * @method        download_file
 * @param         $filename, $path
 * @return        void
 * @since         version 0.0.1
 * @version       0.0.1 
 */
    public function download_file($filename, $path){    
        
        // Variable Declaration
        $fullPath = $path.'/'.$filename;
        if($fd = fopen($fullPath, 'r')) {
            $fsize = filesize($fullPath);
            $path_parts = pathinfo($fullPath);
            $ext = strtolower($path_parts["extension"]);
            switch ($ext) {
                case 'xls':
                case 'doc':
                case 'docx':
                    // add here more headers for diff. extensions
                    header("Content-type: application/doc");
                    // use 'attachment' to force a download
                    header("Content-Disposition: attachment; filename=\"".$path_parts["basename"]."\""); 
                    break;
                
                default;
                    header("Content-type: application/octet-stream");
                    header("Content-Disposition: filename=\"".$path_parts["basename"]."\"");
            }
            header("Content-length: $fsize");
            header("Cache-control: private"); //use this to open files directly
            while(!feof($fd)) {
                $buffer = fread($fd, 2048);
                echo $buffer;
            }
        }
        fclose ($fd);
        exit;
    }

    /* Change the space into underscore */
    function stringConvertUscoreToSpace($getName = null)
    {
        $getName = strtolower(str_replace('_',' ',$getName));
        return $getName;
    }
    
    /* Report Management */
    function getReport($getName = null)
    {
        $reportArray =  array('1' => 'Order Master','2'=>'Order Detail');
        return $reportArray;
    }
    
    /* Get State List */
    function getStateList()
	{
		return $statelist = array("AL" => "Alabama","AK" => "Alaska","AZ" => "Arizona","AR" => "Arkansas","AS" => "American Samoa","CA" => "California","CO" => "Colorado","CT" => "Connecticut","DE" => "Delaware","DC" => "District of Columbia","FL" => "Florida","GA" => "Georgia","GU" => "Guam","HI" => "Hawaii","ID" => "Idaho","IL" => "Illinois","IN" => "Indiana","IA" => "Iowa","KS" => "Kansas","KY" => "Kentucky","LA" => "Louisiana","ME" => "Maine","MD" => "Maryland","MA" => "Massachusetts","MI" => "Michigan","MN" => "Minnesota","MS" => "Mississippi","MO" => "Missouri","MT" => "Montana","NE" => "Nebraska","NV" => "Nevada","NH" => "New Hampshire","NJ" => "New Jersey","NM" => "New Mexico","NY" => "New York","NC" => "North Carolina","ND" => "North Dakota","MP" => "Northern Marianas Islands","OH" => "Ohio","OK" => "Oklahoma","OR" => "Oregon","PA" => "Pennsylvania","PR" => "Puerto Rico","RI" => "Rhode Island","SC" => "South Carolina","SD" => "South Dakota","TN" => "Tennessee","TX" => "Texas","UT" => "Utah","VT" => "Vermont","VA" => "Virginia","VI" => "Virgin Islands","WA" => "Washington","WV" => "West Virginia","WI" => "Wisconsin","WY" => "Wyoming");
	}
        
        
     function getStoreTime($startTime=null,$endTime=null){
            $tStart = strtotime($startTime);
            $tEnd = strtotime($endTime);
            $tNow = $tStart;
            $timeRange=array();
            while($tNow <= $tEnd){
              $intervalTime=date("H:i",$tNow);
              $intervalTimeforValue=date("H:i:s",$tNow);
              if($intervalTime=="00:00"){ $intervalTime = "23:59"; $intervalTimeforValue = "23:59:00"; }
              $timeRange[$intervalTimeforValue]= $intervalTime;
              $tNow = strtotime('+30 minutes',$tNow);
            }
            return $timeRange;
     }
     
     
     
     function getCurlData($url)
    {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16");
		$curlData = curl_exec($curl);
		curl_close($curl);
		return $curlData;
    }
    
    
    /*---------------------------------------------
    Function name:checcheckStoreAvalibilitykAddress
    Description:To check store timing 
    -----------------------------------------------*/
    
    function checkStoreAvalibility($strore_id=null,$selected_date=null,$booking_time=null){
    
    if(isset($strore_id)){
            $storeHoliday = ClassRegistry::init('StoreHoliday');
            $storeAvailability = ClassRegistry::init('StoreAvailability');
            $holiday_list=$storeHoliday->getStoreHolidaylist($strore_id);
            
            $storeAvailable=$storeAvailability->getStoreNotAvailableInfo($strore_id);
            if(!$selected_date){
                $current_date=date('Y-m-d');
            }else{
                $current_date=$selected_date;
            }
            $date = new DateTime($current_date);
            $current_day=$date->format('l');
            if(!$booking_time){
                $current_time=strtotime(date('H:i:s'));
            }else{
                $current_time=strtotime($booking_time);
            }
            $available_day=array();
            $start_time="";
            $end_time="";
            foreach($storeAvailable as $row){
                $available_day[]=$row['StoreAvailability']['day_name'];
                if($row['StoreAvailability']['day_name']==$current_day){
                    $start_time=$row['StoreAvailability']['start_time'];
                    $end_time=$row['StoreAvailability']['end_time'];
                }
            
            }
            $holidayList=array();
            $description="";
            foreach($holiday_list as $row){
                $holidayList[]=$row['StoreHoliday']['holiday_date'];
                $holiday_date=$row['StoreHoliday']['holiday_date'];
                if(strtotime($current_date)==strtotime($holiday_date)){
                    $description=$row['StoreHoliday']['description'];
                }
            }
           //echo "<pre>"; print_r($storeAvailable);die;
            $start_time=strtotime($start_time);
            $end_time=strtotime($end_time);
            $store_status=array();
            if(in_array($current_date,$holidayList)){ //Holiday List
                $store_status['status']="Holiday";
                $store_status['description']=$description;
                return $store_status;
            }elseif(!in_array($current_day,$available_day)){ //Week Day CLosed
                $store_status['status']="WeekDay";
                return $store_status;
            }elseif($current_time>$end_time || $current_time<$start_time ){
                $store_status['status']="Timeoff";
                $store_status['start_time']=$start_time;
                $store_status['end_time']=$end_time;
                return $store_status;
            }else{
                return true;
            }
    }
    
    }
    
    
    /*---------------------------------------------
    Function name:uploadMenuItemImages()
    Description:To upload Admin Images
    -----------------------------------------------*/
    public function uploadMenuItemImages($image=null,$path=null,$storeId=null){
        if($image['name']!=""){
            $ImageStatus="";
            $errormsg='';
            $arr=pathinfo($image['name']);
            //$arr = explode(".",$_FILES["StoreGallery"]['name']);
            $fileextension="";
            if(isset($arr['extension'])){
                $fileextension= $arr['extension'];
            }
            //if(trim((strtolower($fileextension)!="jpg")) && trim((strtolower($fileextension)!="gif")) && trim((strtolower($fileextension)!="jpeg")) && trim((strtolower($fileextension)!="png")))
            if(!$this->checkImageExtension($fileextension))
            {
                $errormsg=$errormsg."Only jpg,gif,png type images are allowed<br />";
                $ImageStatus="false"; 
            }
            $maxsize=2097152;             //In Byte
            $actualSize=$image['size'];           
            if(($actualSize > $maxsize) || $image['error']=="1"){
                $errormsg=$errormsg."The image you are trying to upload is too large. Please limit the file size upto 2MB.";
                $ImageStatus="false";
            }
            $target_dir = WWW_ROOT.$path;                        
            $uniqueImageName = $arr['filename'].'_'.date('Y-m-d-H-s').'_'.$storeId.'.'.$fileextension;                     
            $target_file = $target_dir.$uniqueImageName;
            $response=array();
            $response['imagename']=$uniqueImageName;
            if($errormsg==""){                
               if (move_uploaded_file($image['tmp_name'], $target_file)){
                    $response['status']=true; 
               }else{
                    $response['status']=false;
                    $response['errmsg']="Unable to upload image";                  
               }
            }else{
                $response['status']=false;
            }
            $response['errmsg']=$errormsg; 
            return $response;
        }else{
            $response['imagename']='';
            $response['status']=true;
            return $response;
        }
    }
    
    
    /*---------------------------------------------
    Function name:checkExtension
    Description:To verify Image Extensions
   -----------------------------------------------*/
  
   function checkImageExtension($extension=null){
               $extarr=array('jpg','gif','jpeg','png');
               $extension=strtolower($extension);
               if(in_array($extension,$extarr)){
                           return true;    
               }else{
                           return false;
               }
   }
   
    /*---------------------------------------------
    Function name:RandomString
    Description:To generate unique number
   -----------------------------------------------*/
   
   function RandomString()
    {
        //$unique_key = substr(md5(rand(0, 1000000)), 0, 7);
        $unique_key = sprintf("%06d", mt_rand(1, 999999));
        return $unique_key;
    }
    
    
    /*---------------------------------------------
    Function name:checkPermissionByaction
    Description:For permissions of Controllers and actions
   -----------------------------------------------*/
    
    function checkPermissionByaction($controller=null,$action=null) {
	  $userId=AuthComponent::User('id'); 	 
	  if (!empty($controller)) {
	       App::import('Model', 'Tab');
	       $this->Tab = new Tab();
	       $tabid = $this->Tab->getTabData(null,$controller,$action);
	       App::import('Model', 'Permission');
               $this->Permission = new Permission();
	      $permissiondata = $this->Permission->getPermissionData($userId, $tabid);                            
	      if (!empty($permissiondata)) {
		  $permission = 1;
	      } else {
		  $permission = 0;
	      }            
	      return $permission;
	  }
     }
     
    /*---------------------------------------------
    Function name:sendsmsNotification
    Description:For SMS Notification
    -----------------------------------------------*/
     
    function sendSmsNotification($toNumber=null,$message=null){
        if($toNumber){
            App::import('Model', 'MainSiteSetting');
	    $this->MainSiteSetting = new MainSiteSetting();
            $settings=$this->MainSiteSetting->getSiteSettings();
            $tApikey=$settings['MainSiteSetting']['twilio_api_key'];
            $tApiToken=$settings['MainSiteSetting']['twilio_api_token'];
            App::import('Vendor', 'Twilio', array('file' => 'Twilio'.DS.'Services'.DS.'Twilio.php'));           
            $client = new Services_Twilio($tApikey,$tApiToken);            
            $sms=$client->account->messages->create(array(
                'To' =>$toNumber,
                'From' => "+12602040555",
                'Body' => $message,  
            ));
            
           
        }
    }
    
   
    function printdemo($printer_name = "smb://192.168.0.251/JetDirect"){
        App::import('Vendor', 'escpos', array('file' => 'escpos'.DS.'Escpos.php'));
        $connector = new NetworkPrintConnector("192.168.0.251", 9100);
        //$connector = new FilePrintConnector($printer_name);
        //$connector = null;
        //$connector = new WindowsPrintConnector('smb://192.168.0.250/HP LaserJet 3055 PCL5');
        //$connector = new WindowsPrintConnector($printer_name);
        
        $printer = new Escpos($connector);
        //echo "<pre>"; print_r($printer); exit;
        $printer -> text("Hello World!\n Good Job");
        $printer -> cut();
        $printer -> close();
        die;
    }
    
    
    
    function PrintReceipt(){
        App::import('Vendor', 'escpos', array('file' => 'escpos'.DS.'Escpos.php'));
        
        //$tmpdir = sys_get_temp_dir();
        //$file =  tempnam($tmpdir, 'ctk');
        //$fp = fopen($file, 'w+');
        $connector = new FilePrintConnector("/dev/ttyS0");
        //$fp = fopen("/dev/usb/lp1", "w+");
        $printer = new Escpos($connector);
        App::import('Component', 'Item');
        /* Information for the receipt */
        $items = array(
            new Item("Example item #1", "4.00"),
            new Item("Another thing", "3.50"),
            new Item("Something else", "1.00"),
            new Item("A final item", "4.45"),
        );
        $subtotal = new Item('Subtotal', '12.95');
        $tax = new Item('A local tax', '1.30');
        $total = new Item('Total', '14.25', true);
        //$logo = new EscposImage("images/escpos-php.png");
        
        /* Print top logo */
        //$printer -> setJustification(Escpos::JUSTIFY_CENTER);
        //$printer -> graphics($logo);
        
        /* Name of shop */
        $printer -> selectPrintMode(Escpos::MODE_DOUBLE_WIDTH);
        $printer -> text("ExampleMart Ltd.\n");
        $printer -> selectPrintMode();
        $printer -> text("Shop No. 42.\n");
        $printer -> feed();
        
        /* Title of receipt */
        $printer -> setEmphasis(true);
        $printer -> text("SALES INVOICE\n");
        $printer -> setEmphasis(false);
        
        /* Items */
        $printer -> setJustification(Escpos::JUSTIFY_LEFT);
        $printer -> setEmphasis(true);
        $printer -> text(new item('', '$'));
        $printer -> setEmphasis(false);
        foreach($items as $item) {
            $printer -> text($item);
        }
        $printer -> setEmphasis(true);
        $printer -> text($subtotal);
        $printer -> setEmphasis(false);
        $printer -> feed();
        
        /* Tax and total */
        $printer -> text($tax);
        $printer -> selectPrintMode(Escpos::MODE_DOUBLE_WIDTH);
        $printer -> text($total);
        $printer -> selectPrintMode();
        
        /* Footer */
        $printer -> feed(2);
        $printer -> setJustification(Escpos::JUSTIFY_CENTER);
        $printer -> text("Thank you for shopping at ExampleMart\n");
        $printer -> text("For trading hours, please visit example.com\n");
        $printer -> feed(2);
        $printer -> text(date('l jS \of F Y h:i:s A') . "\n");
        
        /* Cut the receipt and open the cash drawer */
        $printer -> cut();
        $printer -> pulse();        
        fclose($fp);       
    }
     
    

}
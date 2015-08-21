<?php

App::uses('Component', 'Controller');


class DateformComponent extends Component {
    

    var $components = array('Cookie','Session','Email','Upload','Categories.Easyphpthumbnail');
      /*------------------------------------------------
        Function name:formatDate()
        Description:To format date in Y-m-d format
        created:22/7/2015
       -----------------------------------------------------*/
        
        public function formatDate($userDate=null){
            //if($userDate){
            //    $dateArray=explode("-",$userDate);
            //    $correctDate=$dateArray[2]."-".$dateArray[0]."-".$dateArray[1];
            //    return $correctDate;
            //}
             if($userDate){
                $userDate=str_replace('-','/',$userDate);   
                $correctDate=date('Y-m-d',strtotime($userDate));                
                 if($correctDate=="1970-01-01"){
                    $dateArray=explode("-",$userDate);
                    $correctDate=$dateArray[2]."-".$dateArray[0]."-".$dateArray[1];
                    
                 }
                return $correctDate;
            }
        }
        
        
         /*------------------------------------------------
        Function name:us_format()
        Description:To format date in m-d-y format
        created:22/7/2015
       -----------------------------------------------------*/
        
        public function us_format($userDate=null){
            if($userDate){
                
                
                 if($userDate){
                
                    $correctDate=date('m-d-Y',strtotime($userDate));
                
                    if($correctDate=="1970-01-01"){
                       $dateArray=explode("-",$userDate);
                       $correctDate=$dateArray[2]."-".$dateArray[0]."-".$dateArray[1];
                       
                    }
                
                   
                }
                 return $correctDate;
            }
        }
        
    
        
    
    
}

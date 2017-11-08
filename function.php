<?php
/**
 * Plugin Name: GCMW Plugin for Contact Form 7
 * Plugin URI: http://elementdesignllc.com/2011/11/contact-form-7-get-parameter-from-url-into-form-plugin/
 * Description: GCMW Plugin for Contact Form 7
 * Version: 0.1
 * Author: Chad Huntley, Praful Ghadge
 * Author URI: http://URI_Of_The_Plugin_Author
 * License: GPL2
 */

/*  Copyright 2013  Chad Huntley  (email : chad@elementdesignllc.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/*
add_action('wpcf7_mail_sent', 'save_cf7_data');
add_action( 'wpcf7_before_send_mail', wpcf7_disablEmailAndRedirect );

function wpcf7_disablEmailAndRedirect( $cf7 ) {
    // get the contact form object
    $wpcf7 = WPCF7_ContactForm::get_current();
    // do not send the email
    $wpcf7->skip_mail = true;

}*/

 

add_action( 'wpcf7_init', 'wpcf7_add_shortcode_gcmwplugin' );
function wpcf7_add_shortcode_gcmwplugin() {
    if ( function_exists( 'wpcf7_add_shortcode' ) ) {
        wpcf7_add_shortcode( 'gethiddentag', 'wpcf7_gethiddentag_shortcode_handler', true );
        wpcf7_add_shortcode( 'getqueryparam', 'wpcf7_getqueryparam_shortcode_handler', true );
        wpcf7_add_shortcode( 'getpostmeta', 'wpcf7_getpostmeta_shortcode_handler', true );
    }
}


/* Add input hidden form tag by fetching values from page URL*/
function wpcf7_gethiddentag_shortcode_handler($tag) {
 
   if (!is_array($tag)) return '';
    $name = $tag['name'];
    if (empty($name)) return '';
    $html = '<input type="hidden" name="' . $name . '" value="'. esc_attr( $_GET[$name] ) . '" />';
    return $html;
}


/* pass value to the form by fetching values from page URL*/
function wpcf7_getqueryparam_shortcode_handler($tag) {
    if (!is_array($tag)) return '';

    $name = $tag['name'];
    if (empty($name)) return '';

    $html = esc_html( $_GET[$name] );
    return $html;
}


/* pass value to the form by fetching values from page URL*/
function wpcf7_getpostmeta_shortcode_handler($tag) {
    
    global $post;
    if (!is_array($tag)) return '';

    $name = $tag['name'];
    if (empty($name)) return '';
    write_log("Contact Form Tag Name" .$name );
    //$customValues= getCustomValues($post->ID);
    //write_log(print_r($customValues).'xyz');
    if($_GET[$name]) {
       $html = esc_html( $_GET[$name]);
    }else {
       // print_r($customValues);
       // print_r ('xxxx'.$name);
      $html = esc_html( strtolower(get_post_meta( $post->ID, $name, true )));
    }
    return $html;
}

function encryptIt( $q ) {
    return urlencode(base64_encode($q));
}

function decryptIt( $q ) {
     return base64_decode(urldecode($q));
}


remove_all_filters ('wpcf7_before_send _mail');
add_action('wpcf7_before_send_mail', 'contactform7_before_send_mail');

function contactform7_before_send_mail() {
    $wpcf7 = WPCF7_ContactForm::get_current();
    $wpcf7->skip_mail = true;

    global $wpdb,$post;
    $form_to_DB = WPCF7_Submission::get_instance();
    if ( $form_to_DB ) {
        $formData = $form_to_DB->get_posted_data();
    }
  
    write_log('last query'.$wpdb->last_query);
     if(session_id() == '') {
       session_start();
    }


    //validation
     
     $post= $_SESSION['reg_post_id'];
     $event_type=get_post_meta( $post, 'gcmw_event_type', true );
     $donation_type=get_post_meta( $post, 'gcmw_donation_type', true );
     write_log("event_type ".$event_type);
     write_log("donation_type ".$donation_type);
    // check for Modification request
     try{
          $wpdb->query("BEGIN");
        if($formData['status']=='true'){ 
          storeModificationRequest($formData); 
          // update registration
        }else if($formData['mod_status']=='true' ){ 
          updateDonationData($formData);
          updateDonationEvent($formData);
          updateAcharyaConfernce($formData);
        //new registrations        
        }else{
            
          if($event_type=='Camp' && $donation_type=='FreeCamp') {
              if(duplicateDataCheck($formData)){
                return;
              } 
           } 

           storeDonationData($formData);
           storeDonationEvent($formData);
           if($event_type=='Camp' && $donation_type=='FreeCamp')
            storeAcharyaConfernce($formData);
      }
      if($event_type=='Seva')
        storeDonationSevaData($formData);
      if( $event_type=='Donation' && $donation_type=='Registration')
        storeDonationCategoryData($formData,$result);
       $wpdb->query("COMMIT ");
   }catch (Exception $e){
      $wpdb->query("ROLLBACK  ");
    }  

}

function storeModificationRequest($formData)
{
     global $wpdb;
     global $post;
     global $_SESSION;
      $abc=encryptIt($formData['id']);
          $query = $formData['id'];
          $acharya_name= explode("-",$query);
          $id=$acharya_name[0];
          $modifydata=array(
          'modify_status'=>$formData['status'],
          'email'=>$formData['your-email'],
          'url'=>get_site_url()."/acharya-conference-registration/?id=".$abc,
          'id_name'=>$formData['id'],
          'id'=>$id,
          'first_name'=>$acharya_name[1]
          );
        $_SESSION['modifydata'] = $modifydata;
}

function duplicateDataCheck($formData){
    global $wpdb;
    global $post;
    global $_SESSION;
    $duplicate = false; 
    $name =  $formData['first_name'];
    $email=  $formData['your-email'];
    $sqls=$wpdb->get_results("SELECT count(*) as countdata  from wp_donation as wp
      inner join wp_donation_event as wde on wp.id = wde.donation_id
      where wde.donation_type='FreeCamp' and wp.first_name='".$name."' and wp.email = '".$email."'",ARRAY_A);
    if($sqls[0]['countdata'] > 0){ 
       $duplicate = true;
       $_SESSION['validation_error']="Duplicate registration for ".$name." and " .$email  ;
     }   
    return $duplicate;
}

function storeDonationData($formData){
    global $wpdb;
    global $post;
    global $_SESSION;
    $donationData=array(
      'id' => '',
      'date'=>date('m/d/y'),
      'time'=>date('h:i:s'),
      'prefix'=>$formData['selectg'],
      'first_name'=>$formData['first_name'],  
      'last_name'=>$formData['last_name'],
      'parents_name'=>$formData['parents_name'],
      'email'=>$formData['your-email'],
      'pan_num'=>$formData['pan_card'],
      'passport_num'=>$formData['passport'],
      'contact'=>$formData['phone_number'],
      'address1'=>$formData['address_line1'],
      'address2'=>$formData['address_line2'],
      'city'=>$formData['city'],
      'state'=>$formData['state'],
      'country'=>$formData['country'],
      'nationality'=>$formData['nationality'],
      'currency'=>$formData['currency'],
      'pincode'=>$formData['pincode'],
      'center'=>$formData['center'],
      'total_amount'=>$formData['donation_amt'],
      'resume'=>$formData['your-file'],
      'payment_method'=>$formData['payment_method'],
      'subscription_term' => $formData['subscription']); 
     $result = $wpdb->insert( $wpdb->prefix.'donation',$donationData, array( '%s' )); 
     if($result > 0 ){
          $_SESSION['donation'] = $donationData;  
     }else{
        $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
          write_log("Insert Failed for ".$wpdb->prefix."donation, insert values ".print_r($donationData,true) );
        throw new Exception("Form Processing failed for ".$wpdb->prefix."donation");
     }
}

function storeDonationEvent($formData){
     global $wpdb;
     if($_GET['postid'])
       $post= $_GET['postid'];
    else
      $post= $_SESSION['reg_post_id'];
      $donationid=$wpdb->insert_id;
      write_log(get_post_meta( $post, 'gcmw_event_type', true ),'shubhangi');
      $donationEventData= array(
        'id' => '',
        'donation_id'=>$wpdb->insert_id,
        'event_id'=>$formData['event_id'],
        'event_type'=> get_post_meta( $post, 'gcmw_event_type', true ),
        'donation_type'=> get_post_meta( $post, 'gcmw_donation_type', true ),
        'amount' =>(is_null($formData['donation_amt']))?'0':$formData['donation_amt'],
        'currency'      =>$formData['currency'],
        'payment_method'=>$formData['payment_method'],
        'option_field1' =>$formData['textfield1'],
        'option_field2' =>$formData['textfield2'],
        'option_field3' =>$formData['textfield3'],
        'option_field4' =>$formData['textfield4'],
        'purpose' =>$formData['purpose'],
        'subscription_month' =>$formData['subscription_month'],
        ///'amount'=>$formData['pledge']
         'status'=>'NOT_PAID'
    );
    $result = $wpdb->insert( $wpdb->prefix.'donation_event',$donationEventData, array( '%s' ));
    if($result >0 ){
        $_SESSION['donationEvent'] = $donationEventData;
        $_SESSION['donationlastid'] = $wpdb->insert_id;
     }else{
        $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
        write_log("Insert Failed for ".$wpdb->prefix."donation_event, insert values ".print_r($donationEventData,true) );
        throw new Exception("Form Processing failed for ".$wpdb->prefix."donation_event");
     }  
}




function updateDonationPaymentStatus($result){
    global $wpdb;
    $last_insert_id = $_SESSION['donationlastid'];
      write_log("prafulsss".$last_insert_id);
      $timestamp = date_create();
      $timestampid = date_timestamp_get($timestamp);
      $trans_id = $timestampid.$last_insert_id;
      write_log('prafulg'.$trans_id);
    $data= array(
        'status'           => $result ["status"],
        'receipt_no'       => $result["receiptNo"],
        'bank_authorization_id'=> $result["authorizeID"],
        'batch_no'         => $result["batchNo"],
        'transaction_no'   => $trans_id,
        'txn_response_code'=> $result["txnResponseCode"],
        'txn_response_desc'=> $result ["txnResponseCodeDesc"],
        'acq_response_code'=> $result["merchTxnRef"],
        'pymt_gw_message'  => $result ["message"]
    );
    $_SESSION['paymentdata'] = $data;
    $wpdb->update($wpdb->prefix.'donation_event', 
    $data , array('donation_id'=> $result["merchTxnRef"]));
}

function storeDonationSevaData($formData){
    global $wpdb;
    if($formData['selectproject']!='')
    { $donationid=$wpdb->get_results("select MAX(id) as lastinsertid from ".$wpdb->prefix."donation",ARRAY_A);
    foreach($formData['selectproject'] as $data)
       {
         $str = $data;
         $sevadata=array(
         'id'=>'',
         'event_donation_id'=>$donationid[0]['lastinsertid'],
         'sevaproject_id'=>$str
         );     
        $wpdb->insert( $wpdb->prefix.'seva',$sevadata, array( '%s' ));
      }
    } 
  
}

function storeDonationCategoryData($formData,$result){
  global $wpdb;
 if($formData['donationproject']!='' )
 {
   $terms =$wpdb->get_results("select * from ".$wpdb->prefix."terms where term_id =".$formData['category'],ARRAY_A); 
   $posts =$wpdb->get_results("select post_title from ".$wpdb->prefix."posts where ID=".$formData['donationproject'],ARRAY_A);
   $lastinsertid=$wpdb->get_results("select MAX(id) as lastinsertid from ".$wpdb->prefix."donation",ARRAY_A);
 }
   $categorydonation=array(
                'id' => '',
                'category_id'=>$formData['category'],
                'project_id'=>$formData['donationproject'],
                'category_name'=>$terms[0]['name'],
                'project_name'=>$posts[0]['post_title'],
                'amount'=>(is_null($formData['donation_amt']))?'0':$formData['donation_amt'],
                'donation_id'=>$lastinsertid[0]['lastinsertid']
             );
    
    write_log("categorydonation ".print_r($categorydonation,true));
   $result = $wpdb->insert( $wpdb->prefix.'donation_project',$categorydonation, array( '%s' ));
   if($result >0 ){
        $_SESSION['donationtitle'] = $posts[0]['post_title'];
        $_SESSION['categorydonation'] = $categorydonation;
     }else{
        $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
        write_log("Insert Failed for ".$wpdb->prefix."donation_project, insert values ".print_r($categorydonation,true) );
        throw new Exception("Form Processing failed for ".$wpdb->prefix."donation_project");
     }
}

function storeAcharyaConfernce($formData){
    global $wpdb;
    global $post;
    global $_SESSION;
      $modified_date=date('m/d/y');
      $modified_time=date('h:i:s');
      write_log('xxxxxxshubhangi'.$formData['arrival_time_ap']);
      $arrivaltime=$formData['arrivaltime'];
      write_log('arrivaltime'.$arrivaltime);
      $departuretime=$formData['departuretime'];
      $donationTravellingData=array(
          'id' => '',
          'date'=>date('m/d/y'),
          'time'=>date('h:i:s'),
          'acharya_lookup'=>$formData['acharya_lookup'],
          'special_arrengment' =>$formData['specialre'],
          'need_transport'     =>$formData['need_Trans'][0],
          'arrivalFrom'        =>$formData['arrivalToCV'],
          'registration_type'  =>$formData['registration_type'],
          'departureTo'        =>$formData['departureFromCV'],
          'arrival_date'       =>$formData['arrivaldate'],
          'arrival_time'       =>$arrivaltime,
          'departure_date'     =>$formData['departuredate'],
          'departure_time'     =>$departuretime,
          'donation_event_id'  =>$wpdb->insert_id
          );
       
      $result = $wpdb->insert( $wpdb->prefix.'camp_registration',$donationTravellingData, array( '%s' ));
      if($result > 0 ){
         $_SESSION['acharyaConfernceData'] = $donationTravellingData;
     }else{
        $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
        write_log("Insert Failed for ".$wpdb->prefix."camp_registration, insert values ".print_r($donationTravellingData,true) );
        throw new Exception("Form Processing failed for ".$wpdb->prefix."camp_registration");
     }
}

function updateDonationData($formData){
    global $post;
    global $_SESSION;
    global $wpdb;
            $modified_date=date('m/d/y');
            $modified_time=date('h:i:s');
            $firstname=$formData['search_name'];
            $id=$formData['id'];
    $donationData=array(
      'prefix'=>$formData['selectg'],
      'first_name'=>$formData['first_name'],  
      'last_name'=>$formData['last_name'],
      'parents_name'=>$formData['parents_name'],
      'email'=>$formData['your-email'],
      'pan_num'=>$formData['pan_card'],
      'passport_num'=>$formData['passport'],
      'contact'=>$formData['phone_number'],
      'address1'=>$formData['address_line1'],
      'address2'=>$formData['address_line2'],
      'city'=>$formData['city'],
      'state'=>$formData['state'],
      'country'=>$formData['country'],
      'nationality'=>$formData['nationality'],
      'currency'=>$formData['currency'],
      'pincode'=>$formData['pincode'],
      'center'=>$formData['center'],
      'total_amount'=>$formData['donation_amt'],
      'resume'=>$formData['your-file'],
      'payment_method'=>$formData['payment_method'],
      'subscription_term' => $formData['subscription'],
      'modified_date'=>$modified_date,
      'modified_time'=>$modified_time
      ); 
       $updateData=array(
          'updated_id'=>$id,
          'mod_status'=>$formData['mod_status']);
      
       $result =$wpdb->update( $wpdb->prefix.'donation',$donationData,array( 'id' => $id ));
       if($result > 0 ){
          $_SESSION['updateData']=$updateData;
          $_SESSION['donation'] = $donationData;
       }else{
          $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
          write_log("Update Failed for ".$wpdb->prefix."donation, updateDonationEvent values ".print_r($donationData,true) );
          throw new Exception("Form Processing failed for ".$wpdb->prefix."donation");
       }   
}

function updateDonationEvent($formData){
    global $wpdb;
    global $post;
    global $_SESSION;
      $id=$formData['id'];
      $modified_date=date('m/d/y');
      $modified_time=date('h:i:s');
      $donationEventData=array(
          'modified_date'      =>$modified_date,
          'modified_time'      => $modified_time
          );
      
       $result = $wpdb->update( $wpdb->prefix.'donation_event' ,$donationEventData,array('donation_id' =>$id));
      if($result > 0 ){
           $_SESSION['donationEvent'] = $donationEventData;
       }else{
          $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
          write_log("Update Failed for ".$wpdb->prefix."donation_event, Update values ".print_r($donationEventData,true) );
         throw new Exception("Form Processing failed for ".$wpdb->prefix."donation_event");
       }


}
function updateAcharyaConfernce($formData){
    global $wpdb;
    global $post;
    global $_SESSION;
   
      $modified_date=date('m/d/y');
      $modified_time=date('h:i:s');
      
      $donationeventid=$wpdb->get_results("select wcr.id as cid from ".$wpdb->prefix."camp_registration as wcr , ".$wpdb->prefix."donation_event as wed 
                                                where  wcr.donation_event_id=wed.id and wed.donation_id='".$formData['id']."'",ARRAY_A);
      $id=$donationeventid[0]['cid'];
      $arrivaltime=$formData['arrivaltime'];
      $departuretime=$formData['departuretime'];
      $donationTravellingData=array(
          'acharya_lookup'=>$formData['acharya_lookup'],
          'special_arrengment' =>$formData['specialre'],
          'need_transport'     =>$formData['need_Trans'][0],
          'arrivalFrom'        =>$formData['arrivalToCV'],
          'departureTo'        =>$formData['departureFromCV'],
          'arrival_date'       =>$formData['arrivaldate'],
          'arrival_time'       =>$arrivaltime,
          'departure_date'     =>$formData['departuredate'],
          'departure_time'     =>$departuretime,
          'registration_type'  =>$formData['registration_type'],
          'modified_date'      =>$modified_date,
          'modified_time'      => $modified_time
          );
      
        $result = $wpdb->update( $wpdb->prefix.'camp_registration' ,$donationTravellingData,array('id' =>$id));
      if($result > 0 ){
            $_SESSION['acharyaConfernceData'] = $donationTravellingData;
       }else{
          $_SESSION['sql_failed'] = "Form Processing failed , contact webmaster@chinmayamission.com";
          write_log("Update Failed for ".$wpdb->prefix."camp_registration, Update values ".print_r($donationTravellingData,true) );
           throw new Exception("Form Processing failed for ".$wpdb->prefix."camp_registration");
       }   

}
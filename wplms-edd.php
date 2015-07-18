<?php
/**
 * Plugin Name: WPLMS EDD Add-On
 * Plugin URI: http://www.vibethemes.com/
 * Description: Integrates EDD with WPLMS
 * Author: VibeThemes
 * Version: 1.0.0
 * Author URI: https://vibethemes.com/
 * License: GNU AGPLv3
 * License URI: http://www.gnu.org/licenses/agpl-3.0.html
 */
/* ===== INTEGRATION with Easy Digital Downloads plugin =========
 *==============================================*/

class WPLMS_EDD{
	function __construct(){
		add_action('init',array($this,'add_vibe_edd_metaboxes'));	
		add_filter('wplms_course_product_metabox',array($this,'wplms_course_edd_product_metabox'));	
		add_filter('edd_microdata_wrapper',array($this,'wplms_courseinfo_edd_downloads_excerpt'));
		add_action( 'edd_update_payment_status', array($this,'wplms_edd_completed_purchase'), 100, 3 );
		add_filter('wplms_course_credits',array($this,'wplms_edd_course_credits'),10,2);
		add_filter('wplms_private_course_button_label',array($this,'wplms_edd_private_course_button_label'));
		add_filter('wplms_private_course_button',array($this,'wplms_edd_private_course_button_link'));
	}
	function add_vibe_edd_metaboxes(){
	$prefix = 'vibe_';
	$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400);
	$wplms_download_metabox = array(  
		array( // Text Input
			'label'	=> __('Associated Courses','vibe'), // <label>
			'desc'	=> __('Associated Courses with this product. Enables access to the course.','vibe'), // description
			'id'	=> $prefix.'courses', // field id and name
			'type'	=> 'selectmulticpt', // type of field
			'post_type'=>'course'
		),
	    array( // Text Input
			'label'	=> __('Subscription ','vibe'), // <label>
			'desc'	=> __('Enable if Product is Subscription Type (Price per month)','vibe'), // description
			'id'	=> $prefix.'subscription', // field id and name
			'type'	=> 'showhide', // type of field
	        'options' => array(
	          array('value' => 'H',
	                'label' =>'Hide'),
	          array('value' => 'S',
	                'label' =>'Show'),
	        ),
	                'std'   => 'H'
		),
	    array( // Text Input
			'label'	=> __('Subscription Duration','vibe'), // <label>
			'desc'	=> __('Duration for Subscription Products (in ','vibe').calculate_duration_time($product_duration_parameter).')', // description
			'id'	=> $prefix.'duration', // field id and name
			'type'	=> 'number' // type of field
		),
	);
		if ( in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$events_metabox = new custom_add_meta_box( 'page-settings', __('WPLMS Product Settings','vibe'), $wplms_download_metabox, 'download', true );
		}
	}

	function wplms_course_edd_product_metabox($course_product_metabox){
		if ( in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$prefix = 'vibe_';
			$course_product_metabox[]=array(
				'label'	=> __('Easy Digital Download','vibe'), // <label>
				'desc'	=> __('Connect EDD Download for this course','vibe'), // description
				'id'	=> $prefix.'edd_download', // field id and name
				'type'	=> 'selectcpt', // type of field
				'post_type'=> 'download',
		        'std'   => ''
				);
		}
		return $course_product_metabox;
	}


	function wplms_courseinfo_edd_downloads_excerpt($content){
		$courses = vibe_sanitize(get_post_meta(get_the_ID(),'vibe_courses',false));
		$excerpt ='';
		if(isset($courses) && is_array($courses)){
			$excerpt.= '<div class="connected_courses"><h6>'.__('Courses Included','vibe').'</h6><ul>';
			foreach($courses as $course){
				$excerpt.= '<li><a href="'.get_permalink($course).'"><i class="icon-book-open"></i> '.get_the_title($course).'</a></li>';
			}
			$excerpt.= '</ul></div>';
		}
		$excerpt .=$content;
		return $excerpt;
	}

	function wplms_edd_completed_purchase( $payment_id, $new_status, $old_status ) {
		if ( $old_status == 'publish' || $old_status == 'complete' )
			return; // Make sure that payments are only completed once

		// Make sure the payment completion is only processed when new status is complete
		if ( $new_status != 'publish' && $new_status != 'complete' )
			return;

		$user_id = get_current_user_id();
		$cart_items   = edd_get_payment_meta_cart_details( $payment_id );
		foreach ( $cart_items as $key => $cart_item ){
			$item_id  = isset( $cart_item['id']    ) ? $cart_item['id']    : $cart_item;
			if(is_numeric($item_id) && get_post_type($item_id) == 'download'){
				$courses = vibe_sanitize(get_post_meta($item_id,'vibe_courses',false));
				$subscribed=get_post_meta($product_id,'vibe_subscription',true);

				if(vibe_validate($subscribed) ){

					$duration=get_post_meta($product_id,'vibe_duration',true);
					$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400); // Product duration for subscription based
					$t=time()+$duration*$product_duration_parameter;

					foreach($courses as $course){
						update_post_meta($course,$user_id,0);
						update_user_meta($user_id,$course,$t);
						$group_id=get_post_meta($course,'vibe_group',true);
						if(isset($group_id) && $group_id !='')
						groups_join_group($group_id, $user_id );  

						bp_course_record_activity(array(
						      'action' => __('Student subscribed for course ','vibe').get_the_title($course),
						      'content' => __('Student ','vibe').bp_core_get_userlink( $user_id ).__(' subscribed for course ','vibe').get_the_title($course).__(' for ','vibe').$duration.__(' days','vibe'),
						      'type' => 'subscribe_course',
						      'item_id' => $course,
						      'primary_link'=>get_permalink($course),
						      'secondary_item_id'=>$user_id
				        ));      
					}
				}else{	
					if(isset($courses) && is_array($courses)){
					foreach($courses as $course){
						$duration=get_post_meta($course,'vibe_duration',true);
						$course_duration_parameter = apply_filters('vibe_course_duration_parameter',86400); // Course duration for subscription based
						$t=time()+$duration*$course_duration_parameter;
						update_post_meta($course,$user_id,0);
						update_user_meta($user_id,$course,$t);
						$group_id=get_post_meta($course,'vibe_group',true);
						if(isset($group_id) && $group_id !='')
						groups_join_group($group_id, $user_id );

						bp_course_record_activity(array(
						      'action' => __('Student subscribed for course ','vibe').get_the_title($course),
						      'content' => __('Student ','vibe').bp_core_get_userlink( $user_id ).__(' subscribed for course ','vibe').get_the_title($course).__(' for ','vibe').$duration.__(' days','vibe'),
						      'type' => 'subscribe_course',
						      'item_id' => $course,
						      'primary_link'=>get_permalink($course),
						      'secondary_item_id'=>$user_id
				        )); 
						}
					}
				}
			}
		}
	}


	function wplms_edd_course_credits($credits,$course_id){
		if ( in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$edd_product = get_post_meta($course_id,'vibe_edd_download',true);
			
			if(isset($edd_product) && is_numeric($edd_product)){
				ob_start();
				edd_price($edd_product);
				$price=ob_get_contents();
				ob_end_clean();
				
				$private = apply_filters('wplms_private_course_label',__('PRIVATE'));
				
				if(strpos($credits,$private) !== false){
					$credits = $price;
				}else{
					$credits .= $price;
				}
			}
		}
		return $credits;
	}

	function wplms_edd_private_course_button_link($link){
		global $post;
		$course_id = $post->ID;
		if( in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$edd_product = get_post_meta($course_id,'vibe_edd_download',true);
			if(isset($edd_product) && is_numeric($edd_product)){
				$link = get_permalink($edd_product);
			}
		}
		return $link;
	}


	function wplms_edd_private_course_button_label($label){
		global $post;
		$course_id = $post->ID;
		if( in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$edd_product = get_post_meta($course_id,'vibe_edd_download',true);
			if(isset($edd_product) && is_numeric($edd_product)){
				$label = __('Take this Course','vibe');
			}
		}	
		return $label;
	}
}

new WPLMS_EDD();


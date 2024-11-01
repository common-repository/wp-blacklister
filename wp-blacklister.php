<?php
/* 
Plugin Name: WP-Blacklister
Plugin URI: http://shinraholdings.com/plugins/wp-blacklister
Description: Plugin tool for assembling lists of IP addresses, emails, and urls from spam comments.
Version: 1.2.1
Author: bitacre
Author URI: http://shinraholdings.com

License: GPLv2 
	Copyright 2012 Shinra Web Holdings (plugins@shinrasecurity.com)
	
This plugin creates an admin paged called Blacklist under the Settings menu that contains 3 text boxes which are automatically populated with lists of all the IP addresses, urls, and email addresses of comments marked as spam. It also includes the option to sort these 3 lists by instance counts (so it is easy to find the worst offenders). 

This makes it easy to include in your comment moderation/blacklist or to export for other uses.
*/

// this is the first plugin I wrote from scratch as a class instead of regular so there's probably something very wrong/stupid with it.
if( !class_exists( 'wpBlacklister' ) ) {
	class wpBlacklister {
		
		// load option name into variavle
		var $adminOptionsName = 'wpBlacklister_item';
		var $adminOptionsGroup = 'wpBlacklister_group';
		var $pluginSlug = 'wpBlacklister';
		
		function wpBlacklister() { // constructor 
		}
	
		// defines additional plugin meta links (appearing under plugin on Plugins page)
		function set_plugin_meta( $links, $file ) { 
			$plugin = plugin_basename( __FILE__ ); // '/nofollow/nofollow.php' by default
			if ( $file == $plugin ) { // if called for THIS plugin then:
				$newlinks = array( '<a href="options-general.php?page=' . $this->pluginSlug . '">Settings</a>'	); // array of links to add
				return array_merge( $links, $newlinks ); // merge new links into existing $links
			}
			return $links; // return the $links (merged or otherwise)
		}
	
		/* aggregates comment data by type
		thank you to MomDad (wordpress.org/support/profile/momdad) for suggesting instance count
		and providing the function code adapted below */
		function aggComments( $spam_comments, $index_type, $display_counts = 0, $remove_duplicates = 1, $hard_sort = 1 ) {
    		$return_array = array(); // create blank array
	
		    foreach( $spam_comments as $spam_comment ) // for each spam comment
		        array_push( $return_array, trim( $spam_comment->$index_type ) ); // slice its data into new array
		
		    if( $display_counts ) {
		        $return_array_with_counts = array_count_values( $return_array );
		        $return_array = $ip_or_email = $count = array();
		
		        foreach( $return_array_with_counts as $key => $value ){
        		    $ip_or_email[] = $key;
		            $count[] = $value;
        		}
				
		        array_multisort( $count, SORT_DESC, $ip_or_email, SORT_ASC, $return_array_with_counts ); //STABLE SORT THAT MAINTAINS THE ORIGINAL ORDER FOR ELEMENTS THAT HAVE EQUAL COUNTS
        		foreach( $return_array_with_counts as $key => $value )
        		    if( !empty( $key ) ) //SKIP ANY BLANK EMAIL ADDRESSES/URLS
		                array_push( $return_array, $key . ' (' . $value . ')' );
       			
				return $return_array;
    		}
			
		    if( $remove_duplicates ) $return_array = array_flip( array_flip( array_reverse( $return_array, true ) ) ); // faster dedupe
    		if( $hard_sort ) sort( $return_array ); // hard (rekey) sort
		
		    return $return_array;
		}
		
		// load options on initilize 
		function init() {
			$this->getOptions();
			if( function_exists( 'register_setting' ) ) 
				register_setting( $this->adminOptionsGroup, $this->adminOptionsName );
			if( function_exists( 'wp_register_style' ) ) 
				wp_register_style( 'wpBlacklisterOptionsStyle', plugins_url( 'wp-blacklister-options.css', __FILE__) );
			if( function_exists( 'wp_register_script' ) ) 
				wp_register_style( 'wpBlacklisterOptionsScript', plugins_url( 'wp-blacklister-options.js', __FILE__) );
		}
		
		function enque_styles() {
			wp_enqueue_style( 'wpBlacklisterOptionsStyle' );
			wp_enqueue_script( 'wpBlacklisterOptionsScript' );
		}
		
		// returns an array of admin options
		function getOptions() {
			// default options array to fill in any gaps
			$defaultOptions = array(
				'spam_ip_array' => array(),
				'spam_email_array' => array(), 
				'spam_url_array' => array(),
				'show_counts' => 0
			);
			
			// pulled options array
			$pulledOptions = get_option( $this->adminOptionsName );
			if( !empty( $pulledOptions ) ) { // if we pulled something;
				foreach( $pulledOptions as $key => $value ) // split into key pairs;
					$defaultOptions[$key] = $value; // and patch over the default array
				update_option( $this->adminOptionsName, $defaultOptions); // then store it
			}

			return $defaultOptions; // return array (patched or otherwise)
		}
		
		// prints the admin page
		function printAdminPage() {
			$adminOptions = $this->getOptions(); // pull the options array
			
			 $spamComments = get_comments( 'status=spam' ); // get the spam comments array once
			 $spamComments_count = count( $spamComments ); // count the total
			
			// extract unique ips, emails, & urls from spam comments
			$ip_array = $this->aggComments( $spamComments, 'comment_author_IP', $adminOptions['show_counts'] );
			$email_array = $this->aggComments( $spamComments, 'comment_author_email', $adminOptions['show_counts'] );
			$url_array = $this->aggComments( $spamComments, 'comment_author_url', $adminOptions['show_counts'] );
			
			// count size of each array
			$ip_count = count( $ip_array );
			$email_count = count( $email_array );
			$url_count = count( $url_array);
			
			// implode to linebroken strings
			$ip_string = implode( "\r\n", $ip_array );
			$email_string = implode( "\r\n", $email_array );
			$url_string = implode( "\r\n", $url_array );
			
?>

<div class="wrap">
	<?php screen_icon(); ?>
	<h2>WP-Blacklister</h2>
	
	<!-- Description -->
	<p class="wpb-description"><?php echo sprintf( 'You may post a comment on this plugin\'s %1$shomepage%2$s if you have any questions, bug reports, or feature suggestions.', '<a href="http://shinraholdings.com/plugins/wp-blacklister" rel="help">', '</a>' ); ?></p>
	
    <h3 class="wpb-sec-title">Spam Comments: <span class="wpb-count"><?php echo $spamComments_count; ?></span></h3>

		<div id="wpb-display-container">
    		
			<div class="wpb-col-container">
            	<h4 class="wpb-col-title">IP addresses: <span class="wpb-count"><?php echo $ip_count; ?></span></h4>
				<textarea id="<?php echo $this->adminOptionsName; ?>[spam_ip_array]" name="<?php echo $this->adminOptionsName; ?>[spam_ip_array]" draggable="false" cols="40" rows="10"><?php echo $ip_string; ?></textarea>
			</div>
       		
			<div class="wpb-col-container">
            	<h4 class="wpb-col-title">e-mails: <span class="wpb-count"><?php echo $email_count; ?></span></h4>
				<textarea id="<?php echo $this->adminOptionsName; ?>[spam_email_array]" name="<?php echo $this->adminOptionsName; ?>[spam_email_array]" draggable="false" cols="40" rows="10"><?php echo $email_string; ?></textarea>
			</div>
        	
           	<div class="wpb-col-container">
            	<h4 class="wpb-col-title">URLs: <span class="wpb-count"><?php echo $url_count; ?></span></h4>
				<textarea id="<?php echo $this->adminOptionsName; ?>[spam_url_array]" name="<?php echo $this->adminOptionsName; ?>[spam_url_array]" draggable="false" cols="40" rows="10"><?php echo $url_string; ?></textarea>
			</div>
		
			<div class="wpb-clear">&nbsp;</div>

		</div>	

<h3 class="wpb-sec-title">Options</h3>
	<form method="post" action="options.php">
	<?php settings_fields( $this->adminOptionsGroup ); ?>
	       
        <table class="form-table"><tbody>
			
            <tr valign="top">
				<th scope="row"><label for="<?php echo $this->adminOptionsName; ?>[show_counts]">Show and sort by instance counts?</label></th>
				<td><input type="checkbox" id="<?php echo $this->adminOptionsName; ?>[show_counts]" name="<?php echo $this->adminOptionsName; ?>[show_counts]" value="1" <?php checked( $adminOptions['show_counts'] )?>/></td>
            </tr>

		</tbody></table>

            <p class="submit">
            	<input type="submit" id="submit" name="submit" class="button-primary" value="Save Changes" />
            </p>
	</form>
</div>
    	<?php } 
	}

 // end class wpBlacklister

if( class_exists("wpBlacklister" ) ) {
	$wpBlacklist_instance = new wpBlacklister();
}

// initialize the admin panel
if( !function_exists( "wpBlacklister_add_options_page" ) ) {
	function wpBlacklister_add_options_page() {
		global $wpBlacklist_instance;
		if( !isset( $wpBlacklist_instance ) ) {
			return;
		}
		if( function_exists( 'add_options_page' ) )
			$page = add_options_page( 'Blacklist', 'Blacklist', 'manage_options', 'wpBlacklister', array( &$wpBlacklist_instance, 'printAdminPage' ) );
			add_action( 'admin_print_styles-' . $page, array( &$wpBlacklist_instance, 'enque_styles' ) );
	}
		
}
}

// hooks and filters	
if( isset( $wpBlacklist_instance ) ) {
	add_action( 'admin_menu', 'wpBlacklister_add_options_page' );
	add_action( 'activate_wp-blacklister/wp-blacklister.php',  array( &$wpBlacklist_instance, 'init' ) );
	add_action( 'admin_init', array( &$wpBlacklist_instance, 'init' ) );
	add_filter( 'plugin_row_meta', array( &$wpBlacklist_instance, 'set_plugin_meta' ), 10, 2 ); // add plugin page meta links
}

?>
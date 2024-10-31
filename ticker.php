<?php
/*
Plugin Name: Post-Ticker
Plugin URI: http://wordpress.org/extend/plugins/post-ticker/
Description: Inserts a scrolling text banner with Entries or Comments RSS feeds, or a more selective list of posts
Author: Chris Wright
Version: 1.3.2
*/


define('TICKER_VERSION', '1.3.2');
define('TICKER_MAX_INT', defined('PHP_INT_MAX') ? PHP_INT_MAX : 32767);
define('PHPREQ',5);

$phpver=phpversion();$phpmaj=$phpver[0];
if($phpmaj>=PHPREQ){
  require_once('rss.php');
 }

//Hooks
register_activation_hook( __FILE__, 'ticker_activate' );
register_deactivation_hook( __FILE__, 'ticker_deactivate' );

//Actions
add_action('switch_theme', 'ticker_activate');
add_action('admin_menu', 'ticker_add_pages');


function insert_ticker(){
  $javascript=ticker_get_plugin_web_root()."webticker_lib.js";
  $tickerspeed=get_option('ticker_speed');
  ?>
  <!-- BEGIN TICKER VER<?php echo TICKER_VERSION; ?> -->
  <div id="TICKERSPEED" style="visibility:hidden"> <?php echo $tickerspeed; ?></div>
  <div id="TICKER" style="overflow:hidden">
    <?php ticker_content($content); ?>
  </div>
  <script type="text/javascript" src="<?php echo $javascript; ?>" language="javascript"></script>
<!-- END TICKER -->
  <?php
}

function ticker_content(){
//first protect against php4 with rss options
 $phpver=phpversion();$phpmaj=$phpver[0];
 if($phpmaj<PHPREQ){
   $postorcomment=get_option('ticker_rss');
   update_option('ticker_rss','norss');
 }
 $site_url = get_option('siteurl');

 // retrieve rss options
 $rss_opt_val = get_option('ticker_rss');

 // find if Recent Comments was selected under Post Type
 $type_opt_val = get_option('ticker_type');
 if($type_opt_val=='recent-comments' && $rss_opt_val=='norss'){
   $posts = ticker_recent_comments(
			     get_option('ticker_num_posts'),
			     get_option('ticker_auto_excerpt_length')
			     );
   
 }else{
   // get posts depending on rss settings
   switch($rss_opt_val){ 
   case 'comments':
     $posts = ticker_use_rss($site_url."/?feed=comments-rss2");
     break;
   case 'entries':
     $posts = ticker_use_rss($site_url."/?feed=rss2");
     break;
   case 'norss':
     $posts = ticker_get_posts(
			       get_option('ticker_type'),
			       get_option('ticker_category_filter'),
			       get_option('ticker_num_posts'),
			       get_option('ticker_user_specified_posts')
			       );
     break;
   case 'norss-comments':
     //dont do anything - we got the comments above
     break;
   default:
     $posts = ticker_get_posts(
			       get_option('ticker_type'),
			       get_option('ticker_category_filter'),
			       get_option('ticker_num_posts'),
			       get_option('ticker_user_specified_posts')
			       );
     break;
   }
 }
 
 foreach ($posts as $post_id => $post){
   $title=$posts[$post_id]['post_title'];
   $excerpt=$posts[$post_id]['post_excerpt'];
   $link=$posts[$post_id]['url'];?>
     <span><b><?php echo $title;?>: </b></span><span><a href="<?php echo $link; ?>"><?php echo $excerpt; ?>... </a></span><?php
														   }
}






/**
 * Get an array of recent comments
 * Adapted from simple_recent_comments http://www.g-loaded.eu/2006/01/15/simple-recent-comments-wordpress-plugin/
 */
//$src_count=7, $src_length=60, $pre_HTML='<li><h2>Recent Comments</h2>', $post_HTML='</li>'
function ticker_recent_comments($src_count, $src_length) {
	global $wpdb;
	
	$sql = "SELECT DISTINCT ID, post_title, post_password, comment_ID, comment_post_ID, comment_author, comment_date_gmt, comment_approved, comment_type, 
			SUBSTRING(comment_content,1,$src_length) AS com_excerpt 
		FROM $wpdb->comments 
		LEFT OUTER JOIN $wpdb->posts ON ($wpdb->comments.comment_post_ID = $wpdb->posts.ID) 
		WHERE comment_approved = '1' AND comment_type = '' AND post_password = '' 
		ORDER BY comment_date_gmt DESC 
		LIMIT $src_count";
	$comments = $wpdb->get_results($sql);

	foreach($comments as $comment){
	  $title="Comment on ".$comment->post_title." by ".$comment->comment_author;
	  $link =get_permalink($comment->ID);
	  $description=$comment->com_excerpt;

	  $posts[$comment->comment_ID]['post_title']=ticker_html_to_text($title);
	  $posts[$comment->comment_ID]['post_excerpt']=ticker_html_to_text($description);
	  $posts[$comment->comment_ID]['url']=$link;
	}
return $posts;

//
//	$output = $pre_HTML;
//	$output .= "\n<ul>";
//	foreach ($comments as $comment) {
//	  $output .= "\n\t<li><a href=\"" . get_permalink($comment->ID) . "#comment-" . $comment->comment_ID  . "\" title=\"on " . $comment->post_title . "\">" . $comment->comment_author . "</a>: " . strip_tags($comment->com_excerpt) . "...</li>";
//	}
//	$output .= "\n</ul>";
//	$output .= $post_HTML;
//	
//	echo $output;
//

}





/**
 * Get an array of $n posts according to the post selection type ($type) and
 * (if 'userspecified' is chosen) $post_list.
 */
function ticker_get_posts($type, $cat_filter, $n, $post_list=null){
	switch($type){
		case 'popular':
			$days = get_option('ticker_popular_days');
			$popular_posts = stats_get_csv('postviews', "days=$days&limit=0"); //Get all posts with stats over the last $days
			
			$post_list = '';
			foreach ($popular_posts as $post) {
				if($post_list!='')
					$post_list .= ', ';
					
				$post_list .= $post['post_id'];
			}
			
			return ticker_get_posts('userspecified', $cat_filter, $n, $post_list);
			break;

		case 'recent':
			$posts = get_posts(
				array(
					'numberposts' => TICKER_MAX_INT, //Get all posts.  This is a sufficient solution for now.
					'orderby' => 'post_date',
				)
			);
			
			break;

		case 'commented':
			$posts = get_posts(
				array(
					'numberposts' => TICKER_MAX_INT,
					'orderby' => 'comment_count',
				)
			);
			break;

		case 'userspecified':
			$posts_tmp = get_posts(
				array(
					'numberposts' => TICKER_MAX_INT,
					'include' => $post_list, //Only get posts within the comma-separated $post_list
				)
			);
			
			//Order posts according to their order in $post_list
			$posts = array();
			$post_list_arr = preg_split('/[\s,]+/', $post_list); //From WP's post.php
			
			//For all post id's in the $post_list
			foreach($post_list_arr as $post_id) {
				//Find the post with the corresponding post id
				foreach($posts_tmp as $post) {
					if($post->ID==$post_id) {
						$posts[] = $post;
						break; //Break out of the inner-most loop
					}
				}
			}
			break;

		
		//TODO: combinations of types

		default:
			$posts = null;
			break;
	}
	
	if($cat_filter==null || sizeof($cat_filter)<1)
		$do_category_filter = false;
	else
		$do_category_filter = true;
		
	//Convert get_posts()'s returned array of objects to an array of arrays to make the data easier to work with.
	//Also, re-index the posts so they can be accessed by their post id.  Note that given PHP's arrays, this still retains the order of the posts in the new $posts_fixed array - they will be in the same order as we insert them, not in order according to their numeric keys (post id)
	$posts_fixed = array();
	if($posts!=null && sizeof($posts)>0 && is_object($posts[0])) {
		foreach($posts as $k => $v){
			//Once we've stored the specified max number of posts, stop searching for more posts (break out of this loop)
			if(sizeof($posts_fixed)==$n)
				break;
			//Copy the post to $posts_fixed if it belongs to a category specified in $cat_filter OR if category filtering is disabled
			$post_categories = wp_get_post_categories($v->ID);
			if(!$do_category_filter || ($do_category_filter && sizeof(array_intersect($cat_filter, $post_categories))>0))
				$posts_fixed[$v->ID] = (array) $v;
		}
	}
	ticker_get_posts_categories($posts_fixed);	//Add categories
	ticker_get_posts_tags($posts_fixed);	//Add tags
	ticker_get_posts_meta($posts_fixed);	//Add custom fields
	ticker_get_posts_tweak($posts_fixed);
	
	return $posts_fixed;
}

/**
 * Get the text version of categories for all $posts.  Since this is pass by
 * reference, we modify $posts in place and return nothing.
 */
function ticker_get_posts_categories(&$posts) {
	//For each post, get the categories
	foreach ($posts as $post_id => $post) {
		$cats = wp_get_post_categories($post_id);
		//For each category, get the name
		$categories = '';
		$cat_num = 1;
		foreach ($cats as $cat_id) {
			$cat = get_category($cat_id);
			//Comma-separated list of categories
			if($categories!='')
				$categories .= ', ';
			$categories .= $cat->name;
			//New entry for every category
			$posts[$post_id]["category_$cat_num"] = $cat->name;
			$cat_num++;
		}
		$posts[$post_id]['categories'] = $categories;
	}
}

/**
 * Get the text version of tags for all $posts. 
 */
function ticker_get_posts_tags(&$posts) {
	//For each post, get the tags
	foreach ($posts as $post_id => $post) {
		$tags = get_the_tags($post_id);
		$tags_str = '';
		if($tags!=null && sizeof($tags)>0) {
			//For each tag, get the name
			$tag_num = 1;
			foreach ($tags as $tag) {
				//Comma-separated list of tags
				if($tags_str!='')
					$tags_str .= ', ';
				$tags_str .= $tag->name;
				//New entry for every tag
				$posts[$post_id]["tag_$tag_num"] = $tag->name;
				$tag_num++;
			}
		}
		$posts[$post_id]['tags'] = $tags_str;
	}
}

/**
 * Get the custom fields for all $posts. 
 */
function ticker_get_posts_meta(&$posts) {
	//For each post, get the custom fields
	foreach ($posts as $post_id => $post) {
		$custom_fields = get_post_custom($post_id);
		//For each field, get the value
		foreach ($custom_fields as $k => $v) {
			$posts[$post_id][$k] = $v[0];
		}
	}
}


/**
 * Tweak certain values for all $posts. 
 * Due to the fact that this function tweaks values created by other
 * ticker_get_posts_xxx() functions, it should be called last of all of
 * the ticker_get_posts_xxx() functions.
 */
function ticker_get_posts_tweak(&$posts) {
	//NOTE: Since this method is called AFTER ticker_get_posts_meta(), we can't use custom fields to override any tags defined in this method (e.g. post_human_date) unless we've explicitly checked to ensure that the value is empty before we write to it (like we're doing with 'post_excerpt' and 'image_x').  As for the other tags, (e.g. the 'post_xxx_date' fields, writing a method to only perform the assignment if the original value is null would be trivial.
	
	$date_chars = array('d', 'D', 'j', 'l', 'N', 'S', 'w', 'z', 'W', 'F', 'm', 'M', 'n', 't', 'L', 'o', 'Y', 'y', 'a', 'A', 'B', 'g', 'G', 'h', 'H', 'i', 's', 'u', 'e', 'I', 'O', 'P', 'T', 'Z', 'c', 'r', 'U');

	//For each post...
	foreach ($posts as $post_id => $post) {
		//Post Date/Time
		$date_str = $post['post_date'];
		$date = ticker_parse_date($date_str);
		$posts[$post_id]['post_human_date'] = ticker_date_to_human_date($date);
		$posts[$post_id]['post_long_human_date'] = ticker_date_to_long_human_date($date);
		$posts[$post_id]['post_slashed_date'] = ticker_date_to_slashed_date($date);
		$posts[$post_id]['post_dotted_date'] = ticker_date_to_dotted_date($date);
		$posts[$post_id]['post_human_time'] = ticker_date_to_human_time($date);
		$posts[$post_id]['post_long_human_time'] = ticker_date_to_long_human_time($date);
		$posts[$post_id]['post_military_time'] = ticker_date_to_military_time($date);
		
		foreach($date_chars as $dc)
			$posts[$post_id]["post_date_$dc"] = date($dc, $date);

		//Modified Date/Time
		$date_str = $post['post_modified'];
		$date = ticker_parse_date($date_str);
		$posts[$post_id]['post_modified_human_date'] = ticker_date_to_human_date($date);
		$posts[$post_id]['post_modified_long_human_date'] = ticker_date_to_long_human_date($date);
		$posts[$post_id]['post_modified_slashed_date'] = ticker_date_to_slashed_date($date);
		$posts[$post_id]['post_modified_dotted_date'] = ticker_date_to_dotted_date($date);
		$posts[$post_id]['post_modified_human_time'] = ticker_date_to_human_time($date);
		$posts[$post_id]['post_modified_long_human_time'] = ticker_date_to_long_human_time($date);
		$posts[$post_id]['post_modified_military_time'] = ticker_date_to_military_time($date);

		foreach($date_chars as $dc)
			$posts[$post_id]["post_modified_date_$dc"] = date($dc, $date);		

		//Copy the post_content from $post to $posts[]
		$posts[$post_id]['post_content'] = $post['post_content'];

		//Process any shortcodes, converting them into their resulting HTML.
		if(function_exists('do_shortcode'))
			$posts[$post_id]['post_content'] = do_shortcode($posts[$post_id]['post_content']);

		//Fix up the post content for plaintext display.
		$posts[$post_id]['post_content'] =
			ticker_html_to_text(		 //Convert the HTML to text
				str_replace("\xC2\xA0", '',	 //The wp editor puts "\xC2\xA0" into content.
					$posts[$post_id]['post_content']
				)
			);

		//If the post doesn't have a post_excerpt, then create one.
		if($posts[$post_id]['post_excerpt']==null || $posts[$post_id]['post_excerpt']=='') {
			$auto_excerpt_chars = get_option('ticker_auto_excerpt_length');
			$s = $posts[$post_id]['post_content'];
			$s = substr($s, 0, $auto_excerpt_chars);
			$s = substr($s, 0, strrpos($s, ' '));
			
			$posts[$post_id]['post_excerpt'] = $s;
		}
		//If the post does already have an excerpt, convert the HTMl to text.
		else {
			$posts[$post_id]['post_excerpt'] = ticker_html_to_text($posts[$post_id]['post_excerpt']);
		}

		//Etc
		$posts[$post_id]['nickname'] = get_usermeta($post['post_author'], 'nickname');
		$posts[$post_id]['url'] = apply_filters('the_permalink', get_permalink($post_id));
	}
}


/**
 * Convert $html to plain text.  Newlines, <div>s, <table>s (etc) become
 * ' - '.  Unlike many html to plain text converters, this function makes no
 * attempt to emulate HTML formatting in the plaintext (beyond replacing
 * certain elements with ' - '.)
 *
 * Adapted from a (slightly flawed) function found at
 * http://sb2.info/php-script-html-plain-text-convert/
 */
function ticker_html_to_text($html) {
	//Remove everything inside of <style> tags.
	$html = preg_replace('/<style[^>]*>.*?<\/style[^>]*>/si','',$html);

	//Remove everything inside of <script> tags.
	$html = preg_replace('/<script[^>]*>.*?<\/script[^>]*>/si','',$html);

	//Replace certain elements (that typically result in line breaks) with a newline character.
  $tags = array (
	  0 => '/<(\/)?h[123][^>]*>/si',
	  1 => '/<(\/)?h[456][^>]*>/si',
	  2 => '/<(\/)?table[^>]*>/si',
	  3 => '/<(\/)?tr[^>]*>/si',
	  4 => '/<(\/)?li[^>]*>/si',
	  5 => '/<(\/)?br[^>]*>/si',
	  6 => '/<(\/)?p[^>]*>/si',
	  7 => '/<(\/)?div[^>]*>/si',
  );
  $html = preg_replace($tags, "\n", $html);

	//Remove tags
	$html = preg_replace('/<[^>]+>/s', '', $html);
	//Replace non-breaking spaces with actual spaces.
	$html = preg_replace('/\&nbsp;/', ' ', $html);
	//Reduce spaces
	$html = preg_replace('/ +/s', ' ', $html);
	$html = preg_replace('/^\s+/m', '', $html);
	$html = preg_replace('/\s+$/m', '', $html);
	//Replace newlines with spaces
	$html = preg_replace('/\n+/s', '-!Line Break123!-', $html); //-!Line Break123!- is just a string that is highly unlikely to occur in the original string.
	//Reduce line break chars.
	$html = preg_replace('/(-!Line Break123!-)+/s', ' - ', $html);
	//Reduce spaces
	$html = preg_replace('/ +/s', ' ', $html);
	$html = preg_replace('/^\s+/m', '', $html);
	$html = preg_replace('/\s+$/m', '', $html);

	return $html;
}

/**
 * Date Conversions
 */
function ticker_date_to_human_date($date) {
  return date('F j, Y', $date);
}
function ticker_date_to_long_human_date($date) {
  return date('l jS \of F Y', $date);
}
function ticker_date_to_slashed_date($date) {
  return date('m/d/y', $date);
}
function ticker_date_to_dotted_date($date) {
  return date('m.d.y', $date);
}
function ticker_date_to_human_time($date) {
  return date('g:i a', $date);
}
function ticker_date_to_long_human_time($date) {
  return date('g:i:s a', $date);
}
function ticker_date_to_military_time($date) {
  return date('H:i:s', $date);
}
function ticker_parse_date($string) {
  preg_match('#([0-9]{1,4})-([0-9]{1,2})-([0-9]{1,2}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})#', $string, $matches);
  return mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
}


/**
 * Activate ticker.
 */
function ticker_activate()
{
  //echo('Activating Ticker');
  ticker_set_default_options();
}
/**
 * Perform actions on plugin deactivation.
 */
function ticker_deactivate()
{
  //echo('Deactivating Ticker');
  //ticker_delete_options(); 
}


/**
 * Set options according to their defaults, but only if the option is undefined.
 * This allows user-specified options to persist if the user disables the
 * plugin for a period of time and then re-enables it later.
 */
function ticker_set_default_options() {
  //User-specified options
  if(get_option('ticker_type')===false)		                add_option('ticker_type', 'commented');
  if(get_option('ticker_category_filter')===false)		add_option('ticker_category_filter', array());
  if(get_option('ticker_user_specified_posts')===false)		add_option('ticker_user_specified_posts', '');
  if(get_option('ticker_num_posts')===false)			add_option('ticker_num_posts', 5);
  if(get_option('ticker_popular_days')===false)			add_option('ticker_popular_days', 90);
  if(get_option('ticker_auto_excerpt_length')===false)		add_option('ticker_auto_excerpt_length', 150);
  if(get_option('ticker_admin_messages_to_show_once')===false)  add_option('ticker_admin_messages_to_show_once', array());
  if(get_option('ticker_rss')===false)		                add_option('ticker_rss', 'norss');
  if(get_option('ticker_speed')===false)		        add_option('ticker_speed', 2);
}
/**
 * Delete Ticker Options
 */
function ticker_delete_options() {
	delete_option('ticker_type');
	delete_option('ticker_category_filter');
	delete_option('ticker_user_specified_posts');
	delete_option('ticker_num_posts');
	delete_option('ticker_popular_days');
	delete_option('ticker_auto_excerpt_length');
	delete_option('ticker_admin_messages_to_show_once');
	delete_option('ticker_rss');
	delete_option('ticker_speed');
}
/**
 * Add the Ticker options page to the Settings menu.
 */
function ticker_add_pages() {
	// Add a new submenu under Options:
	add_options_page('Post-Ticker', 'Post-Ticker', 8, 'tickeroptions', 'ticker_options_page');
}

/**
 * Display the page content for the Ticker admin submenu.
 * Adapted from http://codex.wordpress.org/Adding_Administration_Menus
 */
function ticker_options_page() {
	$hidden_field_name = 'ticker_submit_hidden';
	
	//Set up names
	$type_opt_name = 'ticker_type';
	$category_filter_opt_name = 'ticker_category_filter';
	$user_specified_posts_opt_name = 'ticker_user_specified_posts';
	$num_posts_opt_name = 'ticker_num_posts';
	$popular_days_opt_name = 'ticker_popular_days';
	$auto_excerpt_length_opt_name = 'ticker_auto_excerpt_length';
	$rss_opt_name = 'ticker_rss';
	$ticker_speed_opt_name = 'ticker_speed';
	//$_opt_name = 'ticker_';

	//Read in existing option values from database
	$type_opt_val = get_option($type_opt_name);
	$category_filter_val = get_option($category_filter_opt_name);
	$user_specified_posts_opt_val = get_option($user_specified_posts_opt_name);
	$num_posts_opt_val = get_option($num_posts_opt_name);
	$popular_days_opt_val = get_option($popular_days_opt_name);
	$auto_excerpt_length_opt_val = get_option($auto_excerpt_length_opt_name);
	$rss_opt_val = get_option($rss_opt_name);
	$ticker_speed_opt_val = get_option($ticker_speed_opt_name);
	//$_opt_val = get_option($_opt_name);
	
	// See if the user has posted us some information
	// If they did, this hidden field will be set to 'Y'
	if( $_POST[ $hidden_field_name ] == 'Y' ) {
		//Read the posted values
		$type_opt_val = $_POST[$type_opt_name];
		$category_filter_val = $_POST[$category_filter_opt_name];
		$user_specified_posts_opt_val = $_POST[$user_specified_posts_opt_name];

		//Make sure there's a valid value in the frequency field.  If not, we just insert our own valid value.
		$frequency_opt_val = $_POST[$frequency_opt_name];
		if($_POST[$frequency_opt_name]==null || $_POST[$frequency_opt_name]=='' || $_POST[$frequency_opt_name]<1)
			$frequency_opt_val = 10;

		$num_posts_opt_val = $_POST[$num_posts_opt_name];
		$popular_days_opt_val = $_POST[$popular_days_opt_name];
		$auto_excerpt_length_opt_val = $_POST[$auto_excerpt_length_opt_name];
		$rss_opt_val = $_POST[$rss_opt_name];
		$ticker_speed_opt_val = $_POST[$ticker_speed_opt_name];
		//$_opt_val = $_POST[$_opt_name];
		
		//If 'popular' post selection was chosen but the user has not installed Wordpress.com stats correctly, report an error and fall back to another post selection type.
		if($type_opt_val=='popular' && !function_exists('stats_get_csv')) {
			echo "<div class='updated' style='background-color:#f66;'><p><a href='options-general.php?page=tickeroptions'>Ticker for Wordpress</a> needs attention: please install the <a href='http://wordpress.org/extend/plugins/stats/'>Wordpress.com Stats</a> plugin to use the 'Most popular' post selection type.  Until the plugin is installed, consider using the 'Most commented' post selection type instead.</p></div>";
			$type_opt_val = 'commented'; //'commented' is the best approximation of 'popular' that we have
		}
		
		//Save the posted values in the database
		update_option($type_opt_name, $type_opt_val);
		update_option($category_filter_opt_name, $category_filter_val);
		update_option($user_specified_posts_opt_name, $user_specified_posts_opt_val);
		update_option($num_posts_opt_name, $num_posts_opt_val);
		update_option($popular_days_opt_name, $popular_days_opt_val);
		update_option($auto_excerpt_length_opt_name, $auto_excerpt_length_opt_val);
		update_option($rss_opt_name, $rss_opt_val);
		update_option($ticker_speed_opt_name, $ticker_speed_opt_val);
		//update_option($_opt_name, $_opt_val);
		
		// Output a status message.
		echo '<div class="updated"><p><strong>Options saved.</strong></p></div>';
	}


	//Prepare other assorted values
	$stats_installed_str = function_exists('stats_get_csv')?'<font color="#00cc00">is installed</font>':'<font color="#ff0000">is not installed</font>';
	$plugin_directory = ticker_get_plugin_root();

	// Display the options editing screen
	?>
<div class="wrap">
<h2>Post-Ticker</h2>

<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

<table class="form-table">


 <tr valign="top">
  <th scope="row">Post Selection:</th>
  <td>
   <input type="radio" name="<?php echo $type_opt_name; ?>" value='popular' <?php if($type_opt_val=='popular') { echo 'checked'; } ?>> Most popular posts over the last <input type="text" name="<?php echo $popular_days_opt_name; ?>" value="<?php echo $popular_days_opt_val; ?>" size="2"> days (<a href='http://wordpress.org/extend/plugins/stats/'>Wordpress.com Stats Plugin</a> <?php echo $stats_installed_str; ?>)<br/>
   <input type="radio" name="<?php echo $type_opt_name; ?>" value='commented' <?php if($type_opt_val=='commented') { echo 'checked'; } ?>> Most commented posts<br/>
   <input type="radio" name="<?php echo $type_opt_name; ?>" value='recent' <?php if($type_opt_val=='recent') { echo 'checked'; } ?>> Most recent posts<br/>
   <input type="radio" name="<?php echo $type_opt_name; ?>" value='userspecified' <?php if($type_opt_val=='userspecified') { echo 'checked'; } ?>> User-specified posts: <input type="text" name="<?php echo $user_specified_posts_opt_name; ?>" value="<?php echo $user_specified_posts_opt_val; ?>" size="35"> (comma separated - e.g. "4, 1, 16, 5")<br/>
   <!--This field is only used if  '<code>User-specified posts</code>' is selected as the <code>Post Selection</code>.-->

   <input type="radio" name="<?php echo $type_opt_name; ?>" value='recent-comments' <?php if($type_opt_val=='recent-comments') { echo 'checked'; } ?>> Recent COMMENTS<br/>

  </td>
 </tr>

 <tr valign="top">
  <th scope="row">Category Filter:</th>
  <td>
     Select the categories whose posts you want to include.  Select one or more categories to restrict post selection to those categories.  Select zero categories to allow all posts to be selected regardless of category.  (Select/Deselect with Ctrl-click (PC) or Command-click (Mac))<br />
   <select style="height: auto;" name="<?php echo $category_filter_opt_name; ?>[]" multiple="multiple"> 
    <?php 
			$categories =  get_categories(array('hide_empty' => false));
			if($categories!=null) {
				foreach ($categories as $cat) {
					if(in_array($cat->cat_ID, $category_filter_val))
						$selected = 'selected="selected"';
					else
						$selected = '';

					$option = '<option value="'.$cat->cat_ID.'" '.$selected.'>';
					$option .= $cat->cat_name;
					$option .= ' ('.$cat->category_count.')';
					$option .= '</option>';
					echo $option;
				}
			}
    ?>
   </select>
  </td>
 </tr>

 <tr valign="top">
  <th scope="row">Number of Posts:</th>
  <td>
   <input type="text" name="<?php echo $num_posts_opt_name; ?>" value="<?php echo $num_posts_opt_val; ?>" size="2"> posts
  </td>
 </tr>

 <tr valign="top">
  <th scope="row">Auto-Excerpt Length:</th>
  <td>
   When an excerpt is not provided for a post, an excerpt is automatically created. Select how many characters long the auto-excerpt should be.<br />
   First <input type="text" name="<?php echo $auto_excerpt_length_opt_name; ?>" value="<?php echo $auto_excerpt_length_opt_val; ?>" size="3"> characters
   </td>
 </tr>

 <tr valign="top">
  <th scope="row">RSS Override Settings:</th>
  <td>You may elect to display the contents of RSS feeds as an alternative to the more selective post filters above. <br />
  <?php $phpver=phpversion();$phpmaj=$phpver[0];
  if($phpmaj<PHPREQ){?>
    Use of these options currently requires php version <?php echo PHPREQ;?>. Your current version is <?php echo $phpver;?>.<br />
   </td>
  <?php }else{ ?>
    <br />
     <input type="radio" name="<?php echo $rss_opt_name; ?>" value='norss' <?php if($rss_opt_val=='norss'){echo 'checked';} ?> > No override - use setting above<br/>
     <input type="radio" name="<?php echo $rss_opt_name; ?>" value='entries' <?php if($rss_opt_val=='entries'){echo 'checked';} ?>> Use contents of Wordress Entries RSS feed <br/>
     <input type="radio" name="<?php echo $rss_opt_name; ?>" value='comments' <?php if($rss_opt_val=='comments'){echo 'checked';} ?>> Use contents of Wordress Comments RSS feed<br/>
    </td>
  <?php } ?>
 </tr>

 <tr valign="top">
  <th scope="row">Ticker Speed:</th>
  <td>
   Select the ticker speed.<br />
   <select name="<?php echo $ticker_speed_opt_name; ?>" value="<?php echo $ticker_speed_opt_val; ?>">
     <?php for($i="1"; $i<="10";$i++){
               if($i==$ticker_speed_opt_val){
		 echo "<option value='$i' selected='selected'>$i</option>";
	       }else{
		 echo "<option value='$i'>$i</option>";
	       }
       }?>
      </select>
   </td>
 </tr>


</table>

<hr />

<p class="submit">
<input type="submit" name="Submit" value="<?php _e('Update Options', 'ticker_trans_domain' ) ?>" />
</p>

</form>
</div>

<?php
 
}

/**
 * Get the root directory of the Ticker for Wordpress plugin relative to the filesystem root.
 */
function ticker_get_plugin_root() {
	return dirname(__FILE__).'/'; //Should work on all systems
}
/**
 * Get the root directory of the Ticker for Wordpress plugin relative to the web root.
 */
function ticker_get_plugin_web_root(){
	$site_url = get_option('siteurl');
	$pos = ticker_strpos_nth(3, $site_url, '/');
	$plugin_root = ticker_get_plugin_root();
	//PHP 5 only
	//$plugin_dir_name = substr($plugin_root, strrpos($plugin_root, '/', -2)+1); //-2 to skip the trailing '/' on $plugin_root
	//PHP 4 workaround
	$plugin_dir_name = substr($plugin_root, strrpos(substr($plugin_root, 0, strlen($plugin_root)-2), DIRECTORY_SEPARATOR)+1); //-2 to skip the trailing '/' on $plugin_root
	if($pos===false)
		$web_root = substr($site_url, strlen($site_url));
	else
		$web_root = '/' . substr($site_url, $pos);
	if($web_root[strlen($web_root)-1]!='/')
		$web_root .= '/';
	$web_root .= 'wp-content/plugins/' . $plugin_dir_name;
	return $web_root;
}

/**
 * Find the position of the $n-th occurence of $needle in $haystack, starting at $offset. 
 */
function ticker_strpos_nth($n, $haystack, $needle, $offset=0){
	$needle_len = strlen($needle);
	$hits = 0;
	while($hits!=$n) {
		$offset = strpos($haystack, $needle, $offset);
		if($offset===false)
			return false;
		$offset += $needle_len;
		$hits++;
	}
	return $offset;
}


?>

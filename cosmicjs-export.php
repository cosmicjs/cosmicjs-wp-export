<?php 
/*

Plugin Name: Cosmic JS Export
Plugin URI: https://cosmicjs.com
Description: A plugin that helps you export your site to Cosmic JS.
Version: 0.1
Author: Tony Spiro
Author URI: http://tonyspiro.com
License: GPL2

Copyright 2014  Tony Spiro (email: tspiro@tonyspiro.com)

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

if(isset($_POST['types'])){

	$types = $_POST['types'];
	$types = implode("', '", $types);

	$cosmic = new stdClass();
	$cosmic->bucket = new stdClass();
	global $wpdb;
	
	// Get object types
	$sql = "SELECT * FROM wp_posts WHERE post_type IN ('$types') AND post_status = 'publish' GROUP by post_type";
	$wp_posts = $wpdb->get_results($sql);
	$cosmic->bucket->object_types = Array();
	$num = 0;
	foreach($wp_posts as $post){	
		$type = $post->post_type;
		$type_obj = get_post_type_object( $type );
		$cosmic->bucket->object_types[$num] = new stdClass();
		if($type_obj){
			$cosmic->bucket->object_types[$num]->title = $type_obj->labels->name;
			$cosmic->bucket->object_types[$num]->slug = $type_obj->name;
			$cosmic->bucket->object_types[$num]->singular = $type_obj->labels->singular_name;
		} else {
			$cosmic->bucket->object_types[$num]->title = ucfirst($type);
			$cosmic->bucket->object_types[$num]->slug = $type;
			$cosmic->bucket->object_types[$num]->singular = $type;
		}
		$num++;
	}
	
	// Get objects
	$sql = "SELECT * FROM wp_posts WHERE post_type IN ('$types') AND post_status = 'publish'";
	$wp_posts = $wpdb->get_results($sql);
	$num_objects = 0;
	$cosmic->bucket->media = Array();
	foreach($wp_posts as $key => $post){
		$cosmic->bucket->objects[$num_objects] = new stdClass();
		$cosmic->bucket->objects[$num_objects]->title = $post->post_title;
		$cosmic->bucket->objects[$num_objects]->content = $post->post_content;
		$cosmic->bucket->objects[$num_objects]->type_slug = $post->post_type;
		$cosmic->bucket->objects[$num_objects]->slug = $post->post_name;
		$num_objects++;
	}

	// Grab media
	$sql = "SELECT * FROM wp_posts WHERE post_type = 'attachment'";
	$wp_media = $wpdb->get_results($sql);
	foreach($wp_media as $key => $item){
		
		$ext = AppUtil::fileExt($item->post_mime_type);
		$name = $item->post_title . $ext;
		$og_name = $item->post_title . $ext;
		$cosmic->bucket->media[$key] = new stdClass();
		$cosmic->bucket->media[$key]->name = $name;
		$cosmic->bucket->media[$key]->original_name = $og_name;
		$cosmic->bucket->media[$key]->type = $item->post_mime_type;
		$cosmic->bucket->media[$key]->location = str_replace("/" . $name, "", $item->guid);
	}

	$site_name = get_bloginfo('site');
	$export_slug = AppUtil::makeSlug($site_name);

	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="' . $export_slug . '.json"');
	echo json_encode($cosmic, JSON_PRETTY_PRINT);
	die();
}

/*	Cosmic JS export
==================================== */
class AppUtil
{
  public static function fileExt($contentType)
  {
    $map = array(
        'application/pdf'   => '.pdf',
        'application/zip'   => '.zip',
        'image/gif'         => '.gif',
        'image/jpeg'        => '.jpg',
        'image/png'         => '.png',
        'text/css'          => '.css',
        'text/html'         => '.html',
        'text/javascript'   => '.js',
        'text/plain'        => '.txt',
        'text/xml'          => '.xml',
    );
    if (isset($map[$contentType]))
    {
        return $map[$contentType];
    }

    // HACKISH CATCH ALL (WHICH IN MY CASE IS
    // PREFERRED OVER THROWING AN EXCEPTION)
    $pieces = explode('/', $contentType);
    return '.' . array_pop($pieces);
  }

  public static function makeSlug($str){
	   $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $str));
	   return $slug;
	}
}

// Testing

// ini_set('display_errors',1); 
// error_reporting(E_ALL);

function cosmicjs_export_output() {

	?>
	<h1>Select your post types for export</h1>
	<form action="options-general.php?page=<?php echo $_GET['page']; ?>" method="post">
		<?php
		global $wpdb;
		$sql = "SELECT * FROM wp_posts WHERE post_status = 'publish' GROUP by post_type";
		$wp_posts = $wpdb->get_results($sql);
		if(isset($_POST['types'])){
			$types = $_POST['types'];
		} else {
			$types = Array();
		}
		foreach($wp_posts as $key => $post){	
			$type = $post->post_type;
			$type_obj = get_post_type_object( $type );
			?>
			<div>
				<label>
					<input type="checkbox" name="types[]" value="<?php echo $type; ?>"<?php if(in_array($type, $types)) echo " checked"; ?>> <?php echo $type_obj->labels->name; ?>
				</label>
			</div>
			<?php
		}
		?>
		<br>
		<button class="button" type="submit">Submit</button>
		<p>*Your file will download automatically</p>
	</form>
	<?php
}

add_action( 'admin_menu', 'cosmicjs_export' );

function cosmicjs_export() {
	add_menu_page( 'Cosmic JS Export', 'Cosmic JS Export',  'manage_options', 'Cosmic-JS-export', 'cosmicjs_export_output' );
}

<?php
class FbToWpPostAdmin {
	
	/**
	* Consctructor
	**/
	public function __construct() {
		$this->init_admin();
	}
	
	public function init_admin()
	{
		add_action( 'admin_init' , array( $this, 'register_settings' ) );
		add_action( 'admin_menu' , array( $this, 'admin_main_menu' ) );
		add_action('wp_ajax_import_fb_feed',  array( $this, 'import_fb_feed' ) );
	}
	
	public function admin_main_menu()
	{	
		add_menu_page( 'Facebook to WP Post Setting', 'Facebook to WP Post', 'manage_options', 'fb-to-wp-post-setting', array($this, 'main_menu_page'), FACEBOOK_TO_WP_POST_URL . 'images/drop_small_bw.png', 55.5);
		
	}
	
	public function main_menu_page()
	{
	    wp_enqueue_script('fbtowppost_script');	
	
		?>
        <div class="wrap">
          <h2><?php _e('Facebook to WP Post Configuration', 'facebook_to_wp_post'); ?></h2>
          <p>Click import button to import feed from facebook to wordpress post.</p>
          <form id="frmSetting" name="frmSetting">
            <table class="table">
              <tr>
              	<td id="cls_result"></td>
              </tr>
              <tr>
                 <td><input type="button" id="import_feed" name="import_feed" value="Import" /></td>
              </tr>
            </table>
            <img class="loading hidden" src="<?php echo FACEBOOK_TO_WP_POST_URL; ?>images/spinner.gif" />
          </form>
        </div>
        <?php
	}
	
	public function register_settings()
	{
		wp_register_script( 'fbtowppost_script', FACEBOOK_TO_WP_POST_URL . 'js/init.js' , array( 'jquery' ));
	}
	
	public function import_fb_feed()
	{
		$response = array();
		try
		{
			$jsonFile = FACEBOOK_TO_WP_POST_DIR. '/facebook-data.json';
			$feed = file_get_contents( $jsonFile );
			$jsonStr = json_decode($feed, true);
			
			$post_array = array();
			$post_meta = array();
			if($jsonStr['data'])
			{
				$feed_count = count($jsonStr['data']);
				$i = 0;
				foreach( $jsonStr['data'] as $posts )
				{
					if(!$this->checkMetaValue( 'fb_feed_id', $posts['id']))
					{

					
						$title = $posts['name'];
						$post_content = $posts['message'];
						$post_content.= '<br />'. $posts['link'];
						
						/** Get Image URL from the string ***************/
						$featured_img_link = urldecode($posts['picture']);
						$featured_img_pattern = "/url=(.*)&cfs/";
					    preg_match($featured_img_pattern, $featured_img_link, $match_array);
					    $featured_img_url = $match_array[1];
					    
					    $post_date= $this->getTime($posts['created_time']);

					    /******** Post values *********************/
						$post_array = array( 'post_title' => $title , 'post_content' => $post_content , 'post_status' => 'publish' , 'post_type' => 'post', 'post_date' => $post_date );

						/****** Save Post *************************************/
						$post_id = $this->savePost($post_array);

						/*********** Save Featured Image **********************/
						if($this->validUrl($featured_img_url))
							$image_id = $this->saveImage($featured_img_url);

						/*************** Set Featured Image to Post ***********/
						if($image_id)
							set_post_thumbnail( $post_id, $image_id );

						/****** Save Post meta value *************************/
						$post_meta = array('metakey' => 'fb_feed_id' , 'metavalue' => $posts['id']);
						$this->addMetaValue( $post_id, $post_meta );

						$i++;
					}
				}
				if( $i > 1 || $feed_count == $i)
				{
					$response['status'] = 1;
					$response["message"] = "Facebook feed has been successfully imported as Posts! $i posts have been imported!";
				}
				else if($i==0)
				{
					$response['status'] = 1;
					$response["message"] = "Duplicate posts!";
				}					
				else
				{
					$response['status'] = 0;
					$response["message"] = "There was an error while processing your request. Please refresh the page and try again.";
				}
					
			}
		}
		catch(Exception $ex)
		{
			$response['status'] = 0;
			$response["message"] = "There was an error while processing your request"; // todo: refactor this later - [ this is a generic error message ]
			$response['ex'] = $ex->getMessage();
		}

		header('Content-type: application/json');
		echo json_encode($response);
		wp_die();		

	}

	function getTime($time)
	{
		$date_arr = explode('T', $time);
		$date = $date_arr[0];
		$time_arry = explode('+', $date_arr[1]);
		$time = $time_arry[0];

		return $date. ' ' . $time;
	}

	public function savePost($post_data)
	{
		// Insert the post into the database
		$post_id = wp_insert_post( $post_data );
		return $post_id;
	}

	public function addMetaValue($post_id,$metadata)
	{
		
		if($metadata)
		{
			for ($i=0; $i < count($metadata); $i++) { 
				add_post_meta($post_id, $metadata['metakey'], $metadata['metavalue'], true);
			}
		}
		
	}

	public function checkMetaValue( $key , $value )
	{
		global $wpdb;

		$result = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '$key' AND meta_value = '$value' ");
		
		if($result)
			return true;
		else
			return false;
	}

	public function saveImage($image)
	{
		$attach_id = false;
		$upload_dir = wp_upload_dir();
		
		
		/* use curl to get image instead of $image_data = file_get_contents($image);*/
		$ch = curl_init();
		$timeout = 0;

		// curl set options
		curl_setopt ($ch, CURLOPT_URL, $image);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt ($ch, CURLOPT_AUTOREFERER, true);
		
		// Getting binary data
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		
		//exec curl command
		$image_data = curl_exec($ch);
		
		/* get the mime type incase there is no extension */
		$mime_type =  curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		//close the curl command
		curl_close($ch);

		//get the filename
		/* ! development add sanatize filename if name has spaces or %20 */
		$filename =  sanitize_file_name( basename(urldecode($image)) );


		//create the dir or take the current one
		if (wp_mkdir_p($upload_dir['path'])) {
			$file = $upload_dir['path'] . '/' . $filename;
		} else {
			$file = $upload_dir['basedir'] . '/' . $filename;
		}

		/* check if file is already there and rename it if needed */
		$i= 1;
		
		//split it up
		list($directory, , $extension, $filename) = array_values(pathinfo($file));
		
		//loop until it works
		while (file_exists($file))
		{
			//create a new filename
			$file = $directory . '/' . $filename . '-' . $i . '.' . $extension;
			$i++;
		}

		if (file_put_contents($file, $image_data)) {
			$wp_filetype = wp_check_filetype($filename, null );
			
			/* added mime type */
			if (!$wp_filetype['type'] && !empty($mime_type)) {
				$allowed_content_types = wp_get_mime_types();
				
				if (in_array($mime_type, $allowed_content_types)){
					$wp_filetype['type'] = $mime_type;
				}
			}
			
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => sanitize_file_name($filename),
				'post_content' => '',
				'post_status' => 'inherit'
			);

			$attach_id = wp_insert_attachment( $attachment, $file );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_data = @wp_generate_attachment_metadata( $attach_id, $file );
			wp_update_attachment_metadata( $attach_id, $attach_data );	
		}
		return $attach_id;
	}

	public function validUrl($url)
	{
		// check for a valid url
		if  (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false; else return true;

	}
	
}
new FbToWpPostAdmin();
?>
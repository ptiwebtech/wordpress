<?php
/*
Plugin Name: Geolocation Redirect
Description: Redirect users based on their geolocation.
Version: 1.0
Author: PTI WEB TECH
*/
add_action('admin_menu', 'geolocation_redirect_menu');
function geolocation_redirect_menu() {
	add_menu_page(
		'Geolocation Redirect Settings',
		'Geolocation Redirect',
		'manage_options',
		'geolocation_redirect',
		'geolocation_redirect_page',
		'dashicons-location-alt',
		21
	);
	add_submenu_page(
		'geolocation_redirect',
		'Manage Postcodes',
		'Postcodes',
		'manage_options',
		'edit.php?post_type=postcode' 
	);
	add_submenu_page(
		'geolocation_redirect',
		'CSV Import',
		'CSV Import',
		'manage_options',
		'geolocation_csv_import',
		'geolocation_csv_import_page'
	);
	
}
function geolocation_redirect_page() {
	?>
	<div class="wrap">
		<h2>Geolocation Redirect Settings</h2>
		<form method="post" action="options.php">
			<?php
			settings_fields('geolocation_redirect_settings');
			do_settings_sections('geolocation_redirect');
			submit_button();
			?>
		</form>
		<p>Use this shortcode to see this option : [geo_location_form]</p>
	</div>
	<?php
}
//add_action('admin_menu', 'geolocation_remove_postcode_menu');
function geolocation_remove_postcode_menu() {
	remove_menu_page('edit.php?post_type=postcode');
}
add_action('admin_init', 'geolocation_redirect_settings');
function geolocation_redirect_settings() {
	register_setting('geolocation_redirect_settings', 'google_maps_api_key');
	add_settings_section(
		'geolocation_redirect_section',
		'Google Maps API Key',
		'geolocation_redirect_section_callback',
		'geolocation_redirect'
	);

	add_settings_field(
		'google_maps_api_key',
		'API Key',
		'geolocation_redirect_api_key_callback',
		'geolocation_redirect',
		'geolocation_redirect_section'
	);
}
// Section callback
function geolocation_redirect_section_callback() {
	echo 'Enter your Google Maps API key below.';
}
// API Key callback
function geolocation_redirect_api_key_callback() {
	$api_key = get_option('google_maps_api_key');
	echo '<input type="text" name="google_maps_api_key" value="' . esc_attr($api_key) . '" />';
}
add_action('init', 'geolocation_redirect_init');
function geolocation_redirect_init() {
	register_post_type('postcode',
		array(
			'labels' => array(
				'name' => __('Postcodes'),
				'singular_name' => __('Postcode'),
			),
			'public' => true,
			'has_archive' => true,
			'rewrite' => array('slug' => 'postcodes'),
			'supports' => array('title', 'editor'),
		)
	);
	add_action('add_meta_boxes', 'postcode_meta_box');
	add_action('save_post', 'save_postcode_meta');
	function postcode_meta_box() {
		add_meta_box(
			'postcode_meta_box',
			'Postcode Details',
			'postcode_meta_box_callback',
			'postcode',
			'normal',
			'high'
		);
	}
	function postcode_meta_box_callback($post) {
		wp_nonce_field('postcode_meta_box', 'postcode_meta_box_nonce');
		$postcode = get_post_meta($post->ID, '_postcode_meta_key', true);
		$city_name = get_post_meta($post->ID, '_city_name_meta_key', true);
		$page_url = get_post_meta($post->ID, '_page_url_meta_key', true);
		?>
		<style type="text/css">
			.fm_group {
				margin: 8px 0;
				display: flex;
			}
			.fm_group label {width: 8%;}
		</style>
		<div class="post_code_search_form">
			<div class="fm_group">
				<label for="postcode">Postcode</label>
				<input type="text" id="postcode" name="postcode" value="<?php echo esc_attr($postcode); ?>">
			</div>
			<div class="fm_group">
				<label for="city_name">City Name</label>
				<input type="text" id="city_name" name="city_name" value="<?php echo esc_attr($city_name); ?>">
			</div>
			<div class="fm_group">
				<label for="page_url">Page Url</label>
				<input type="text" id="page_url" name="page_url" value="<?php echo esc_attr($page_url); ?>">
			</div>
			<?php
		}
		function save_postcode_meta($post_id) {
			if (!isset($_POST['postcode_meta_box_nonce'])) {
				return;
			}
			if (!wp_verify_nonce($_POST['postcode_meta_box_nonce'], 'postcode_meta_box')) {
				return;
			}
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}
			if (isset($_POST['postcode'])) {
				$postcode=strtoupper($_POST['postcode']);
				update_post_meta($post_id, '_postcode_meta_key',$postcode);
			}
			if (isset($_POST['city_name'])) {
				update_post_meta($post_id, '_city_name_meta_key', sanitize_text_field($_POST['city_name']));
			}
			if (isset($_POST['page_url'])) {
				update_post_meta($post_id, '_page_url_meta_key', sanitize_text_field($_POST['page_url']));
			}
		}
	}
	function geolocation_form_shortcode() {
		ob_start(); ?>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
		<style type="text/css">
			.post_city_form{display:none;}
			.post_city_form.show{display:block;}
			.geo_location_form {
				position: relative;
			}
			li#city_search_form {
				list-style: none;
				cursor: pointer;
			}
			span.fa.fa-times.close-btn {
				cursor: pointer;
				font-size: 21px;
				color: black;
			}
			div#search-error {
				font-size: 13px;
				line-height: normal;
				color: red;
				padding-top: 11px;
			}
			.post_city_form {
				padding: 20px;
				box-shadow: 0 4px 8px 0 rgba(0,0,0,.2), 0 6px 20px 0 rgba(0,0,0,.19);
				position: absolute;
				top: 38px;
				width: 275px;
				background-color: #fff;
				right: -20px;
			}
			.post_city_form h5 {
				margin: 0;
				font-size: 20px;
				letter-spacing: 0;
				color: #000;
				font-weight: 600;
				font-family: var(--wp--preset--font-family--body);
			}
			.post_city_form .header {
				display: flex;
				border-bottom: 1px solid #ccc;
				padding-bottom: 20px;
				margin-bottom: 20px;
			}
			.post_city_form .form_body p {
				margin: 0 0 7px;
				font-size: 14px;
				color: #595959;
				justify-content: space-between;
			}
			.location_input_sec {
				display: flex;
				justify-content:space-between;
			}
			.location_input_sec input {
				width: 195px;
				border: #dbdbdb solid 1px;
				height: 30px;
				padding-left: 10px;
				border-radius: 33px;
			}
			.location_input_sec button.go_btn {
				border-radius: 33px;
				width: 56px;
				border: 1px solid #ccc;
				height: auto;
				background: #111;
				color: #fff;
				cursor: pointer;
			}
			.post_city_form.show:before {
				width: 0;
				height: 0;
				border-left: 13px solid rgba(0,0,0,0);
				border-right: 13px solid rgba(0,0,0,0);
				border-bottom: 13px solid #fff;
				top: -10px;
				right: 84px;
				position: absolute;
				content: '';
			}
			.post_city_form .header p {
				margin: 0;
			}
		</style>
		<div class="geo_location_form">
			<li class="pointer-container" role="presentation" id="city_search_form">
				<span style="margin-right:4px;font-size: 20px;" class="fa fa-map-marker"></span><span class="location-label" id="main-loc">Click here to select your city</span>
				<p class="tmp_show"></p>
			</li>
			<div class="post_city_form">
				<div class="header">
					<h5>Your service branch is <span class="main-loc_in">Corporate</span></h5>
					<span class="fa fa-times close-btn"></span>
				</div>
				<div class="form_body">
					<p>Enter your location</p>
					<div class="location_input_sec">
						<input type="text" name="city_post_code" class="city_post_code" placeholder="Postal Code, City">
						<button class="go_btn">Go</button>
					</div>
					<div id="search-error" class="gl-error-msgs" style="display:none;">Location selected is outside of our serviceable area. <a onclick="goCorporate()" href="/">Click here</a> to visit our Corporate site.</div>
				</div>
			</div>
		</div><script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $api_key=get_option('google_maps_api_key');?>&libraries=places"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
		<script type="text/javascript">
			jQuery('#city_search_form').click(function(){
				jQuery('.post_city_form').addClass('show');
			})
			jQuery('.close-btn').click(function(){
				jQuery('.post_city_form').removeClass('show');
			})
			jQuery('.go_btn').click(function(e){
				e.preventDefault();
				var city_post_code=jQuery('.city_post_code').val();
				loaction_page_redirect(city_post_code,'no');
			})
			$('.city_post_code').on('keypress', function(e){
				if (e.which === 13) { 
					var city_post_code=jQuery('.city_post_code').val();
					loaction_page_redirect(city_post_code,'no');
				}
			});
			function loaction_page_redirect(city_post_code,map){
				$('.gl-error-msgs').hide();
				if(city_post_code!=''){
					$.ajax({
						type: 'POST',
						dataType:'json',
						url:"<?php echo admin_url('admin-ajax.php'); ?>",
						data: {
							action: 'search_city_url',
							city_post_code: city_post_code,
						},
						success: function(response) {
							console.log(response.page_url);
							if(response.page_url==='No'){
								if(map=='no'){
									$('.gl-error-msgs').show();
								}
							}else{ 
								jQuery('#main-loc').html(response.city_name);
								jQuery('.main-loc_in').html(response.city_name);
								jQuery('.post_city_form').removeClass('show');
								localStorage.setItem('city_name',response.city_name);
								window.location.href = response.page_url;
							}
						},
					});
				}
			}
		</script>
		<?php $api_key=get_option('google_maps_api_key');
		if($api_key==''){?>
			<script type="text/javascript">
				$(document).ready(function(){
					var storedData =localStorage.getItem('city_name');
					if (localStorage.getItem('city_name')) {
						jQuery('#main-loc').html(storedData);
						jQuery('.main-loc_in').html(storedData);
					}else{
						if (navigator.geolocation) {
							navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
						} else {
							console.log("Geolocation is not supported by this browser.");
						}
					}
					function successCallback(position) {
						var latitude = position.coords.latitude;
						var longitude = position.coords.longitude;
						var osmUrl = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + latitude + '&lon=' + longitude;
						$.get(osmUrl, function(data) {
							if (data.address) {
								var city = getCityName(data.address);
								console.log("City: " + city);
								loaction_page_redirect(city,'geo');
							} else {
								console.log("Unable to retrieve city information.");
							}
						});
					}
					function errorCallback(error) {
						console.log("Error getting geolocation: " + error.message);
					}
					function getCityName(address) {
						return address.city;
					}
				});
			</script>
		<?php }else{?>
			<script type="text/javascript">
				$(document).ready(function() {
					var storedData =localStorage.getItem('city_name');
					if (localStorage.getItem('city_name')) {
						jQuery('#main-loc').html(storedData);
						jQuery('.main-loc_in').html(storedData);
					}else{
						navigator.geolocation.getCurrentPosition(success, error);	
						function success(position) {
							console.log(position);
							var lat =position.coords.latitude; //49.049999;
							var lng =position.coords.longitude; //-122.316666
							var latlng = new google.maps.LatLng(lat, lng);
							var geocoder = new google.maps.Geocoder();
							geocoder.geocode({ 'latLng': latlng }, function (results, status) {
								if (status == google.maps.GeocoderStatus.OK) {
									if (results[0]) {
										var postcode = getPostcodeFromAddress(results[0].address_components);
										if(postcode===null||postcode.length<3){
											$('.location-label').html('Postal code not auto-detects')
										}else{
											loaction_page_redirect(postcode,'geo');
										}
									}
								}
							});
						}
						function error() {
							console.error('Unable to retrieve your location.');
						}
						function getPostcodeFromAddress(addressComponents) {
							for (var i = 0; i < addressComponents.length; i++) {
								var types = addressComponents[i].types;
								if (types.includes('postal_code')) {
									var postal_code =addressComponents[i].long_name;
									console.log(postal_code);
									$('.tmp_show').html(postal_code);
									var postal_code = postal_code.split(' ');
									postal_code=postal_code[0];
									console.log(postal_code);
									return postal_code;
								}
							}
							return null;
						}
					}
				});
			</script>
		<?php }?>
		<?php
		return ob_get_clean();
	}
	add_shortcode('geo_location_form', 'geolocation_form_shortcode');
	function search_city_url() {
		$city_post_code = $_POST['city_post_code'];
		$c_postcode=strtoupper($city_post_code);
		$c_postcode=substr($c_postcode,0, 3);
		$args = array(
			'post_type' => 'postcode',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_postcode_meta_key',
					'value' => $c_postcode,
					'compare' => '=',
				),
				array(
					'key' => '_city_name_meta_key',
					'value' => $city_post_code,
					'compare' => 'LIKE',
				),
			),
		);
		$query = new WP_Query($args);
		if ($query->have_posts()) :
			while ($query->have_posts()) : $query->the_post();
				$page_url = get_post_meta(get_the_ID(),'_page_url_meta_key', true);
				$_city_name_meta_key = get_post_meta(get_the_ID(),'_city_name_meta_key', true);
				$response = array(
					'city_name' =>$_city_name_meta_key,
					'page_url'    => $page_url,
				);
			endwhile;
		else :
			$response = array(
				'city_name' =>$_city_name_meta_key,
				'page_url'    =>'No',
			);
		endif;
		wp_send_json($response);
		wp_reset_postdata();
		die();
	}
	add_action('wp_ajax_search_city_url', 'search_city_url');
	add_action('wp_ajax_nopriv_search_city_url', 'search_city_url');


// Callback function for the CSV Import submenu page
	function geolocation_csv_import_page() {
    // Check if form is submitted
		if (isset($_FILES['csv_file'])) {
        // Process CSV file and import data
			geolocation_process_csv($_FILES['csv_file']);
		}

    // Display HTML for CSV import form
		?>
		<div class="wrap">
			<h2>CSV Import</h2>
			<p>Sample File <a href="<?php echo plugin_dir_url(__FILE__) . 'sample_file.csv'; ?>" download="sample_file.csv" id="file-download-button">Download</a></p>
			<hr>
			<form method="post" enctype="multipart/form-data">
				<label for="csv_file">Select CSV File:</label>
				<input type="file" name="csv_file" accept=".csv">
				<input type="submit" value="Import" class="button button-primary">
			</form>
			
			
		</div>
		<?php
	}

	function geolocation_process_csv($file) {
		if ($file['type'] !== 'text/csv') {
			echo '<div class="error"><p>Invalid file type. Please upload a CSV file.</p></div>';
			return;
		}
		$csv_data = array_map('str_getcsv', file($file['tmp_name']));
		foreach ($csv_data as $row) {
			$postcode = $row[0];
			if($postcode!='postcode'){
				$postcode=strtoupper($postcode);
				$postcode=substr($postcode,0, 3);
				$city_name =$row[1];
				$page_url = $row[2];
				$term_name =$row[3];

				$title=$city_name.'-'.$postcode;
				$existing_post = get_page_by_title($title, OBJECT, 'postcode');
				if ($existing_post) {
					$post_id = $existing_post->ID;
					wp_update_post(array(
						'ID'          => $post_id,
						'post_title'  => $title,
						'post_type'   => 'postcode',
						'post_status' => 'publish',
					));
				} else {
					$post_id = wp_insert_post(array(
						'post_title'   => $title,
						'post_type'    => 'postcode',
						'post_status'  => 'publish',
					));
				}
				$taxonomy_name = 'state';
				wp_set_post_terms($post_id, $term_name,$taxonomy_name);
				update_post_meta($post_id, '_postcode_meta_key',$postcode);
				update_post_meta($post_id, '_city_name_meta_key',$city_name);
				update_post_meta($post_id, '_page_url_meta_key', $page_url);
			}
		}
		echo '<div class="updated"><p>CSV file imported successfully.</p></div>';
	}
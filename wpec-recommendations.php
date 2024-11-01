<?php
/**
	* Plugin Name: WP e-Commerce Recommendations
	* Plugin URI: http://ntoklo.com/
	* Description: A plugin that provides recommendations for the WordPress e-Commerce plugin
	* Version: 1.1.3
	* Author: ntoklo
	* Author URI: http://ntoklo.com
	**/
	
	define('NTOKLO_FILE_PATH', dirname(__FILE__));
	//echo NTOKLO_FILE_PATH;
	
	global $wpdb;
	global $event_type;
	global $ntoklo_db_version;
	global $universal_variable;
	global $nt_recommendations_widget_layout_options;
	global $nt_recommendations_widget_colors;
	global $chart_on_settings_page;
	global $no_activity;
	global $json;

	$no_activity = false;

	$nt_recommendations_widget_layout_options = array(
														"row_3" 				=> "3 images in a single row",
														"row_4" 				=> "4 images in a single row",
														"column_image_above" 	=> "Single column (image above)",
														"column_image_right" 	=> "Single column (image at right)",
														"grid_2_column" 		=> "2-column grid",
														"grid_3_column" 		=> "3-column grid",
														"grid_4_column" 		=> "4-column grid"
													);

	$nt_recommendations_widget_colors = array(
												"plum" => "Plum",
												"pink" => "Pink",
												"orange" => "Orange",
												"green" => "Green",
												"blue" => "Blue",
												"dark_blue" => "Dark Blue"
											);

	$chart_on_settings_page = true;

	$universal_variable = array();

	// initialize

	function nt_add_widget_css () {
		wp_enqueue_style("widget_style", plugins_url("css/widget.css", __FILE__));
		wp_enqueue_style("settings_style", plugins_url("css/settings.css", __FILE__));
	}

	add_action("admin_init", "nt_add_widget_css");
	add_action("wp_enqueue_scripts", "nt_add_widget_css");

	// POSTing events

	function nt_set_event_type () {
		global $event_type;
		global $universal_variable;

		if (get_post_type() == "wpsc-product") {
			$event_type 		= "browse";
			if (is_single()) {
				$event_type 	= "preview";
			}
		}
	}

	function nt_set_event_type_to_purchase () {
		global $event_type;

		$event_type = "purchase";
	}

	function nt_render_event_posting_key () {
		$user_key 			= get_option( "key" );
		
		if(!empty($user_key)){
			echo '<script type="text/javascript">var _ntoklo_key = "' . $user_key . '";</script>';
		}else{
			return FALSE;
		}
	}

	function nt_render_ntoklo_js_link () {
		$user_key 			= get_option( "key" );
		if(!empty($user_key)){
			echo '<script type="text/javascript" src="https://console.ntoklo.com/js/ntoklo.js"></script>';
		}else{
			return false;
		}
	}
	
	function getCurrency(){
		$isocode = wpsc_currency_display(' ', array(
					'display_currency_symbol' => false,
					'display_decimal_point'   => false,
					'display_currency_code'   => true,
					'display_as_html'         => false,
					'isocode'                 => false, )
		);
		
		$isocode = trim(str_replace(range(0,9),'', $isocode));
		
		return $isocode;
	}

	function nt_append_universal_variable () {
		global $universal_variable;

		$product_category_obj 	= wp_get_object_terms(wpsc_the_product_id(), 'wpsc_product_category');
		$product_categories 	= array();
		$product_id 			= wpsc_the_product_id();
		$product_title 			= wpsc_the_product_title();
		$product_url 			= wpsc_the_product_permalink();
		$product_price 			= get_post_meta( $product_id, '_wpsc_price', true );
		$product_image_url 		= wpsc_the_product_thumbnail($product_id);
		
		$isocode = getCurrency();		
		
		$product_details = array(
			"id" => (string)$product_id,
			"url" => $product_url,
			"name" => $product_title,
			"unit_price" => (float)$product_price,
			"currency" => $isocode, 
			"image_url" => $product_image_url
		);

		if (count($product_category_obj) > 0) {
			foreach ($product_category_obj as $index => $category) {
				if ($category->parent == 0) {
					array_push($product_categories, $category->name);
				}
			}
			$product_details["category"] = end($product_categories);
		}

		if (is_single()) {
			$universal_variable["product"] = $product_details;
		} else {
			if ( ! isset ($universal_variable["listing"]) ) {
				$universal_variable["listing"] = array(
					"items" => array()
				);
			}
			array_push($universal_variable["listing"]["items"], $product_details);
		}
	}

	function nt_create_universal_variable_and_post () {
		global $event_type;
		global $universal_variable;
		global $user_ID;

		$universal_variable["user"] = array();
		$universal_variable["user"]["user_id"] = (string)wpsc_get_current_customer_id();
		$universal_variable["events"] = array();
		$universal_variable["version"] = "1.1.1";
		$universal_variable["wpecommerce_version"] = "3.8.12.1";
		$universal_variable["ntoklo_version"] = "1.1.3";

		if ( isset($_GET["clickthrough"]) ) {
			$pageSource = new StdClass();
			$pageSource->category = $_GET["category"];
			$the_event = array("type" => $event_type, "cause" => $_GET["clickthrough"] . "-click", "pagesource" => $pageSource);
		} else {
		$the_event = array("type" => $event_type);
		}

		array_push($universal_variable["events"], $the_event);
		
		$user_key 			= get_option( "key" );
		if(!empty($user_key)){
			
			echo '<script type="text/javascript">window.universal_variable = ' . json_encode($universal_variable) . ';</script>';
		}else{
			return FALSE;
		}
		
		nt_render_ntoklo_js_link();
	}

	function nt_post_from_transaction ($purchase_log_object, $sessionid) {
		global $wpdb;
		global $event_type;
		global $universal_variable;
		global $cart_log_id;

		$event_type = "purchase";

		$cart_contents 		= $cart_items = $wpdb->get_results ("SELECT * FROM ".WPSC_TABLE_CART_CONTENTS." WHERE purchaseid = ".$cart_log_id, ARRAY_A);
	
		$universal_variable["transaction"] = array("line_items" => array());
		
		if (count($cart_contents) > 1) {

			foreach ($cart_contents as $key => $item) {
				$product_id 		= $item["prodid"];
				$product_category 	= wp_get_object_terms($product_id, 'wpsc_product_category');
				$product_title 		= $item["name"];
				$product_price 		= $item["price"];
				$quantity 			= $item["quantity"];
				
				$isocode = getCurrency(); 

				$product_details = array(
					"product" => array(
						"id" =>  $product_id,
						"name" =>  $product_title,
						"category" => end($product_category)->name,
						"unit_price" => (float)$product_price,
						"currency" => $isocode
					),
						
					"quantity" => (int)$quantity

				);

				array_push($universal_variable["transaction"]["line_items"], $product_details);
			}
		} else {
						
			$item 				= $cart_contents[0];
			$product_id 		= $item["prodid"];
			$product_category 	= wp_get_object_terms($product_id, 'wpsc_product_category');
			$product_title 		= $item["name"];
			$product_price 		= $item["price"];
			$quantity 			= $item["quantity"];
			
			$isocode = getCurrency();

			$product_details = array(
					"product" => array(
						"id" =>  $product_id,
						"name" =>  $product_title,
						"category" => end($product_category)->name,
						"unit_price" => (float)$product_price,
						"currency" => $isocode
					),
					
					"quantity" => (int)$quantity

				);

			array_push($universal_variable["transaction"]["line_items"], $product_details);
			
		}
	
		nt_create_universal_variable_and_post();
	}

	add_filter( "wpsc_top_of_products_page", "nt_render_event_posting_key", 10 );
	add_filter( "wpsc_product_form_fields_end", "nt_set_event_type", 20 );
	add_filter( "wpsc_product_form_fields_end", "nt_append_universal_variable", 30 );
	add_filter( "wpsc_theme_footer", "nt_create_universal_variable_and_post", 40 );
	add_filter( "wpsc_transaction_results_shutdown", "nt_render_event_posting_key", 40 );
	add_filter( "wpsc_transaction_results_shutdown", "nt_post_from_transaction", 50, 2 );
	

	// settings page

	function nt_account_settings_init () {
		$admin_email 	= get_option( "admin_email" );
		$app_name 		= get_option( "blogname" );
		$app_name 		= preg_replace('/[^a-z\d_\- ]/i', "", $app_name);
		$app_domain 	= parse_url( get_option( "siteurl" ) );

		update_option( "nt_admin_email", $admin_email );
		update_option( "nt_app_name", $app_name );
		update_option( "nt_app_domain", $app_domain["host"] );
	}

	function nt_display_account_creation_form() {
		nt_account_settings_init();
		$admin_email 	= get_option( "nt_admin_email" );
		$app_name 		= get_option( "nt_app_name" );
		$app_domain 	= get_option( "nt_app_domain" );

		echo '
			<div class="nt_settings_panel_wrapper">
				<h2>Create your nToklo account</h2>
				<div class="nt_explanation">
					<p>We need to link your store to an nToklo account to provide you with recommendations. Please choose from the following options to get yourself set up.</p>
					<div id="buttons">
						<div class="btn_wrap">
							<a class="nt_btn nt_no_account" href="#" id="ntLaunchRegister">I don\'t have an nToklo account</a>
							<a class="nt_btn nt_account" href="#" id="ntLaunchLogin">I already have an nToklo account</a>
						</div>
					</div>
					<p>Please follow the instructions in the panel that opens when you click on the buttons.</p>
					<div id="nt_confirmation_code_wrapper">
						<p id="nt_confirmation_assist_text_1">Your confirmation code (which you receive once you\'ve set up your account) goes in here.</p>
						<textarea id="nt_key_and_secret" name="wpsc_options[key_secret_json_string]"></textarea>
						<p id="nt_confirmation_assist_text_2"><strong>Click "Save changes" below to finish.</strong></p>
					</div>
					<p><strong>N.B.</strong> This plugin will send data about user activity on your site to our servers. The data is stored there (so we can process it and to avoid filling up your server) and sent back to this plugin each time you ask for recommendations or charts. For more details about how this works, please <a href="http://www.ntoklo.com/">take a look at our site</a>.</p>
				</div>
				<div id="ntIFrameWrapper"></div>
				<script type="text/javascript">
						window.ntParams = {
							"p" : "wpecommerce",
							"e" : "' . $admin_email . '",
							"n" : "' . $app_name . '",
							"d" : "' . $app_domain . '"
						}

						if (jQuery) {
							var 
								nt_confirmation_code_wrapper 	= jQuery("#nt_confirmation_code_wrapper"),
								nt_launch_register_button 		= jQuery("#ntLaunchRegister"),
								nt_launch_login_button 			= jQuery("#ntLaunchLogin"),
								nt_key_and_secret 				= jQuery("#nt_key_and_secret");

								nt_launch_register_button.on("click", function () {
									nt_confirmation_code_wrapper.addClass("nt_active");
								});

								nt_launch_login_button.on("click", function () {
									nt_confirmation_code_wrapper.addClass("nt_active");
								});

								nt_key_and_secret.on("focus", function () {
									jQuery("#nt_confirmation_assist_text_2").show();
									jQuery("#nt_confirmation_code_wrapper.nt_active").css({"padding": "1em"});
								});
						}
				</script>
				<script src="https://console.ntoklo.com/js/ntoklo.js" type="text/javascript"></script>
			</div>';
	}

	function nt_open_settings_section () {
		echo '<div class="nt_settings_section clearfix">';
	}

	function nt_close_settings_section () {
		echo '</div>';
	}
	
	function nt_render_how_to_place_widgets () {
		global $nt_recommendations_widget_layout_options;
		global $nt_recommendations_widget_colors;

		nt_open_settings_section();

		echo   '<h2 class="nt_section_head" style="margin-top: 0;">How to place widgets on your page</h2>
				<p>You can place recommendations or charts on your store pages using the nToklo widgets, either on the widgets page by dragging a widget on to a sidebar, or by calling the_widget() function from within a template.</p>
				<div id="nt_accordion">
					<h4 class="nt_accordion_toggle">
						<a href="#">
							<span>
								<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="20px" height="10px" viewBox="0 0 20 10" enable-background="new 0 0 20 10" xml:space="preserve">
									<path fill="#010101" d="M2.427,0.21c-0.283-0.28-0.741-0.28-1.024,0c-0.283,0.279-0.283,0.734,0,1.014l8.275,8.194c0.283,0.28,0.741,0.28,1.024,0l8.276-8.194c0.282-0.28,0.283-0.734,0-1.014c-0.283-0.28-0.741-0.28-1.024,0L10.19,7.683L2.427,0.21z"/>
								</svg>
							</span>
							1) Via the widgets menu (easy) 
						</a>
					</h4>
					<div class="nt_accordion_container">
						<p>This is the easiest way and is recommended for non-technical users. Go to the <a href="widgets.php">Appearance > Widgets</a> page and drag either or both of the WPeC widgets on to your sidebar (WPeC chart or WPeC recommendations). From there you can configure settings for each widget and preview them on your store.</p>
					</div>
					<h4 class="nt_accordion_toggle">
						<a href="#">
							<span>
								<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="20px" height="10px" viewBox="0 0 20 10" enable-background="new 0 0 20 10" xml:space="preserve">
									<path fill="#010101" d="M2.427,0.21c-0.283-0.28-0.741-0.28-1.024,0c-0.283,0.279-0.283,0.734,0,1.014l8.275,8.194c0.283,0.28,0.741,0.28,1.024,0l8.276-8.194c0.282-0.28,0.283-0.734,0-1.014c-0.283-0.28-0.741-0.28-1.024,0L10.19,7.683L2.427,0.21z"/>
								</svg>
							</span>
							2) Using shortcodes
						</a>
					</h4>
					<div class="nt_accordion_container">
						<p>This method gives you greater flexibility when positioning your widget but is not recommended for non-technical users.</p>
						<p class="nt_subsection">For recommendations, you should place the following code in the appropriate template file:</p>
						<p class="nt_code">[ntoklo_recommendations $arguments]</p>
						<p>Where $arguments can be any of the following:</p>
						<table cellpadding="10" cellspacing="0" class="nt_settings_table">
							<thead>
								<tr>
									<th>Key</th>
									<th>Accepted values</th>
									<th>Defaults</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>title</td>
									<td>String</td>
									<td>Recommended for you</td>
								</tr>
								<tr>
									<td>max_items</td>
									<td>An integer between 1 - 9. Please note that 2 and 3-column grids will only display multiples of 2 and 3 respectively.</td>
									<td>6</td>
								</tr>
								<tr>
									<td>layout</td>
									<td>
										<ul>';
											foreach ($nt_recommendations_widget_layout_options as $key => $value) {
												echo '<li>' . $key . '</li>';
											}
				echo 					'</ul>
									</td>
									<td>
										row
									</td>
								</tr>
								<tr>
									<td>image_width</td>
									<td>integer: can be any number, but must be appropriate to your layout</td>
									<td>220</td>
								</tr>
								<tr>
									<td>image_height</td>
									<td>As above</td>
									<td>140</td>
								</tr>
								<tr>
									<td>widget_color</td>
									<td>
										<ul>';
											foreach ($nt_recommendations_widget_colors as $key => $value) {
												echo '<li>' . $key . '</li>';
											}
				echo 					'</ul>
									</td>
									<td>
										nt_plum
									</td>
								</tr>
							</tbody>
						</table>
						<p>These arguments should be passed as query string parameters, such as:</p>
						<p class="nt_code">layout=grid_2_column image_width=190 image_height=100 widget_color=blue max_items=4</p>
						<p>Meaning that call to a recommendation widget might look like this:</p>
						<p class="nt_code">[ntoklo_recommendations layout=grid_2_column image_width=190 image_height=100 widget_color=blue max_items=4]</p>
						<p class="nt_subsection"><strong>Charts</strong> are called in a similar way, but with different options. Once again you should place the following code in the appropriate template file:</p>
						<p class="nt_code">[ntoklo_chart $arguments]</p>
						<p>$arguments for charts can be any of the following:</p>
						<table cellpadding="10" cellspacing="0" class="nt_settings_table">
							<thead>
								<tr>
									<th>Key</th>
									<th>Accepted values</th>
									<th>Defaults</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>title</td>
									<td>String</td>
									<td>Recommended for you</td>
								</tr>
								<tr>
									<td>max_items</td>
									<td>Integer between 1 and 100</td>
									<td>10</td>
								</tr>
								<tr>
									<td>tw</td>
									<td>
										<ul>
											<li>DAILY</li>
											<li>WEEKLY</li>
										</ul>
									</td>
									<td>DAILY</td>
								</tr>
								<tr>
									<td>image_width</td>
									<td>integer: can be any number, but must be appropriate to your layout</td>
									<td>100</td>
								</tr>
								<tr>
									<td>image_height</td>
									<td>As above</td>
									<td>100</td>
								</tr>
								<tr>
									<td>widget_color</td>
									<td>
										<ul>';
											foreach ($nt_recommendations_widget_colors as $key => $value) {
												echo '<li>' . $key . '</li>';
											}
				echo 					'</ul>
									</td>
									<td>
										nt_plum
									</td>
								</tr>
							</tbody>
						</table>
						<p>A call to a chart widget might look like this:</p>
						<p class="nt_code">[ntoklo_chart title="Top 10" max_items=10 image_width=150]</p>
					</div>
				</div>
				<script type="text/javascript">
					if (jQuery) {
						jQuery("#nt_accordion").addClass("nt_collapsed");
						jQuery(".nt_accordion_toggle a").on("click", function () {
							if (jQuery(this).parent().next().is(":visible")) {
								jQuery(this).removeClass();
								jQuery(".nt_accordion_container").slideUp();
							} else {
								jQuery(".nt_accordion_toggle a").removeClass();
								jQuery(".nt_accordion_container").slideUp();
								jQuery(this).addClass("nt_rotate_icon").parent().next().slideDown();
							}
						});
					}
				</script>';

		nt_close_settings_section();
	}

	function nt_migrate_store () {
		echo   '<script type="text/javascript">
					window.nt_confirm_new_account = function () {
						if (window.confirm("I want to delete my current settings and create a new application")) {
							document.getElementById("nt_delete_settings_wrapper").style.display = "block";
							document.getElementById("nt_i_understand").style.display = "none";
						}
					}
				</script>';
		nt_open_settings_section();
		echo   '<h2 class="nt_section_head">What to do if you need to change the URL of your store</h2>
				<h3>You\'ll have to create a new nToklo application</h3>
				<p>If you\'re moving your site to a new URL, such as from a test platform to a production one (e.g. test.mystore.com => mystore.com), then <strong>you MUST make sure your nToklo application has the same domain as your store or it won\'t give you recommendations or charts</strong>.</p>
				<h4>How do you create a new nToklo application?</h4> 
				<p>It\'s pretty straightforward - you just delete your current settings following the steps below, then repeat the process you went through when you first set up your nToklo account. To start, please click the button below to confirm that you understand, then check the delete checkbox that appears.</p>
				<p><a href="#" class="nt_btn nt_i_understand" id="nt_i_understand" onclick="nt_confirm_new_account(); return false;">Start settings deletion process</a></p>
				<div id="nt_delete_settings_wrapper">
					<div class="nt_checkbox_wrapper">
						<input type="checkbox" name="wpsc_options[nt_delete_settings]" id="nt_delete_settings" />
						<label for="nt_delete_settings">Please delete my settings and let me create a new application</label>
					</div>
					<p>Scroll down and click "Save changes" at the bottom of this page, <strong>then migrate your site to the new location</strong> and come back to this page, where you\'ll see the prompt to create a new account.</p>
					<h4>What happens to your old data?</h4>
					<p>If you create a new application, your existing data will be preserved in your current application but, if you wish, you can <a href="https://console.ntoklo.com/login" target="_blank">login to your nToklo console</a> and delete that application.</p>
				</div>';
		nt_close_settings_section();
	}

	function nt_render_console_link () {
		nt_open_settings_section();
		echo '	<img class="nt_console_img" src="' . plugins_url("img/console.png", __FILE__) . '" alt="nToklo console" />
				<h2>Recommendation analytics on your console</h2>
				<p>The nToklo console shows information about user activity on your store - think of it like Google Analytics, with a retail focus. You can:</p>
				<ul class="nt_features">
					<li>See a snapshot of all activity on your site on the <strong>platform usage tab</strong>. How busy are you today / this week / this month?</li>
					<li>See how well your recommendations are converting on the <strong>recommendations performance tab</strong>.</li>
					<li>Find out what the best performing location for recommendations is and reposition them if necessary.</li>
					<li>View your purchase funnel on the <strong>item activity tab</strong>, where user browsing history is broken down for you into browse, preview and purchase events.</li>
					<li>See which times of the day, week and month are the busiest on the <strong>user activity tab</strong>.</li>
					<li>See summary figures for today, this week and this month, in relation to the average, busiest and quietest days / weeks / months on <strong>on all four tabs</strong>.</li>
					<li>Keep track of real-world events such as promotional campaigns, overlaying the data on the graphs using our annotations.</li>
				</ul>
				<p>We\'ve packed a ton of features into this console but still kept it easy-to-use, so why not take a look? Please note that you\'ll need an up-to-date browser, such as Chrome, Safari or Firefox (or IE10).</p>
				<p>
					<a class="nt_btn nt_console_link" href="https://console.ntoklo.com/login" target="_blank">
						<span class="nt_svg_label">
							Launch console
						</span>
						<span class="nt_svg_wrap">
							<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="18px" height="16px" viewBox="0 0 25.51 22.68" enable-background="new 0 0 25.51 22.68" xml:space="preserve">
								<defs>
									<linearGradient id="gradConsole" x1="0%" y1="0%" x2="0%" y2="100%">
										<stop offset="0%" style="stop-color:#6384a8;stop-opacity:1" />
										<stop offset="20%" style="stop-color:#375575;stop-opacity:1" />
										<stop offset="100%" style="stop-color:#1c344e;stop-opacity:1" />
									</linearGradient>
								</defs>
								<g>
									<path fill="#6A9B1D" d="M7.791,12.842v-4h4c0-2.209-1.793-4-4-4c-2.211,0-4,1.791-4,4C3.791,11.05,5.58,12.842,7.791,12.842z"/>
									<path fill="#C79C00" d="M8.791,13.842c2.208,0,4-1.791,4-4h-4V13.842z"/>
									<rect x="14.42" y="5" fill="#B10F62" width="7" height="1.6"/>
									<path fill="url(#gradConsole)" d="M23.01,0.838H2.5c-1.128,0-2.051,0.921-2.051,2.051v12.306c0,1.125,0.923,2.051,2.051,2.051h8.204v3.102H9.678c-1.025,0-1.025,1.027-1.025,1.027h0.002v0.467h8.202v-0.469c-0.002-1.025-1.024-1.025-1.024-1.025h-1.025v-3.102h8.203c1.127,0,2.051-0.926,2.051-2.051V2.889C25.061,1.76,24.137,0.838,23.01,0.838z M23.01,15.195H2.5V2.889h20.51V15.195z"/>
									<rect x="14.42" y="8" fill="#910077" width="7" height="1.6"/>
									<rect x="14.42" y="11" fill="#B10F62" width="7" height="1.6"/>
								</g>
							</svg>
						</span>
					</a>
				</p>';
		nt_close_settings_section();
	}

	function nt_display_welcome_settings_panel () {
		global $chart_on_settings_page;
		global $no_activity;

		echo   '<div class="nt_settings_wrapper">
					<div class="nt_settings_panel">';
						nt_render_how_to_place_widgets();
						nt_migrate_store();
						nt_render_console_link();
		echo 		'</div>
					<div class="nt_chart_wrapper">
						<h2 class="nt_whats_selling_well">What\'s selling well on your store</h2>';
						the_widget("ntoklo_chart", "from_settings=true");
						if ($chart_on_settings_page == false) {
							if ($no_activity == true) {
								nt_render_no_chart_data_to_display();
							} else {
								nt_display_domain_mismatch_warning();
							}
							nt_hide_selling_well_css();
						}
		echo 		'</div>
				</div>';
	}

	function nt_hide_selling_well_css () {
							echo   '<style type="text/css">
										.nt_whats_selling_well {
											display: none;
										}
									</style>';
							}

	
	function nt_set_key_and_secret ($json) {
		$jsonObject = json_decode( $json  );
		
		update_option( "key", $jsonObject->key );
		update_option( "secret", $jsonObject->secret );
		
	}

	function nt_delete_recommendations_settings () {
		delete_option( "key_secret_json_string" );
		delete_option( "key" );
		delete_option( "secret" );
		delete_option( "nt_app_domain" );
		unregister_widget("ntoklo_recommendations");
		wp_unregister_sidebar_widget("ntoklo_recommendations");
		unregister_widget("ntoklo_chart");
		wp_unregister_sidebar_widget("ntoklo_chart");
	}

	function nt_render_exclamation_mark () {
		echo   '<div class="nt_exclamation_mark">
					<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="80px" height="80px" viewBox="0 0 80 80" enable-background="new 0 0 80 80" xml:space="preserve">
						<g>
							<path fill="#fff" d="M47.78,22.31c0,2.824-1.102,6.198-1.997,8.952c-1.583,4.751-3.099,9.572-3.304,14.598c-0.07,1.446-0.415,2.823-2.479,2.823c-2.066,0-2.411-1.377-2.479-2.823c-0.207-5.026-1.722-9.847-3.306-14.598c-0.895-2.754-1.997-6.129-1.997-8.952c0-4.889,2.411-9.502,7.782-9.502C45.37,12.808,47.78,17.421,47.78,22.31z M40.343,66.38c-4.27,0-7.437-3.443-7.437-7.438s3.167-7.437,7.437-7.437c4.27,0,7.437,3.442,7.437,7.437S44.613,66.38,40.343,66.38z"/>
						</g>
					</svg>
				</div>';
	}

	function nt_render_no_chart_data_to_display () {
		echo   '<div class="nt_warning">
					<h2>There is no chart data to display</h2>
					<p>This could mean that no activity has been posted from your site recently, in which case we\'re unable to give you recommendations or charts. Please <a href="https://console.ntoklo.com/login">check your nToklo console</a>.</p>
				</div>';
	}

	function nt_display_domain_mismatch_warning () {
		echo 	'<div class="nt_warning">';
					nt_render_exclamation_mark();
		echo		'<h2>There is a problem</h2>
						<p>We can\'t match your site\'s domain with the one used by your nToklo application. This means that we can\'t show you recommendations or charts, and also means that any user activity on your site can\'t be matched with your nToklo application.</p>
						<h2>Possible causes and ways to fix the problem</h2>
						<p>The most likely reason that we can\'t match your store\'s domain with your nToklo one is that you\'ve just migrated your site. Your nToklo application is linked to <strong>' . get_option("nt_app_domain") . '</strong> and your current domain is <strong>' . $_SERVER["SERVER_NAME"] . '</strong>. If these two domain do not match, we cannot track your site activity or send you recommendations and charts.</p>
						<p>You have two options:</p>
						<ol>
							<li>move your site back to <strong>' . get_option("nt_app_domain") . '</strong></li>
							<li>create a new nToklo application (follow steps below on "How to create a new nToklo application")</li>
						</ol>
				</div>';
	}

	function nt_hide_system_error () {
		echo   '<style type="text/css">
					#setting-error-settings_updated {
						display: none;
					}
				</style>';
	}

	//include a file from wp-e-commerce/wpsc-admin/settings-page.php

	include_once(ABSPATH . "/wp-content/plugins/wp-e-commerce/wpsc-admin/settings-page.php");

	function nt_ntoklo_settings_tab ( $settings_page ) {
			$settings_page->register_tab('recommendation_system', 'Recommendations');	
	}

	class WPSC_Settings_Tab_Recommendation_System extends WPSC_Settings_Tab {
		public function display() {
		$json = get_option('key_secret_json_string');			
			if (get_option( "nt_delete_settings" )) {			
				nt_delete_recommendations_settings();
				nt_display_account_creation_form();
				delete_option( "nt_delete_settings" );
				nt_hide_system_error();
			} else {

				if (get_option("nt_app_domain") && strstr($_SERVER["SERVER_NAME"], get_option("nt_app_domain")) == false) {
						echo '<div class="nt_settings_wrapper">';
						nt_display_domain_mismatch_warning();
						echo 	'<div class="nt_settings_panel">';
									nt_migrate_store();
									nt_render_console_link();
						echo 	'</div>
							</div>';
				} elseif (!get_option( "key_secret_json_string" )) {
						nt_display_account_creation_form();
				} else {
					if (!get_option( "key" )) {
						$json = get_option("key_secret_json_string");
						$json = json_decode($json);
						
						if (!is_object($json)) {
							echo '<div class="error"><p>';
							_e( '<strong>WP e-Commerce Recommendations:</strong> That key and secret was invalid. Please try again.', 'default' );
							echo '</p></div>';
							nt_hide_system_error();
							delete_option("key_secret_json_string");
							nt_display_account_creation_form();
						} elseif (gettype($json->key) == 'string' && gettype($json->secret) == 'string') {
							$json = get_option("key_secret_json_string");
							nt_set_key_and_secret($json);
							nt_display_welcome_settings_panel();
						} else {
							echo '<div class="error"><p>';
							_e( 'WP e-Commerce Recommendations: That key and secret was invalid. Please try again.', 'default' );
							echo '</p></div>';
							delete_option("key_secret_json_string");
							nt_display_account_creation_form();
						}
					} else {
						nt_display_welcome_settings_panel();
					}
				}
			}
		}
	}
add_action( "wpsc_register_settings_tabs", "nt_ntoklo_settings_tab", 10, 1);


	/*
	 * Error handling for activation
	 */
	function my_admin_notice() {
		$json = get_option('key_secret_json_string');

		if ( !json_decode($json) && empty($json) ) {
			$path = get_site_url();
		?>
			<div class="error">
				<p><?php _e( '<strong>WP e-Commerce Recommendations:</strong> Please <a href="'.$path.'/wp-admin/options-general.php?page=wpsc-settings&tab=recommendation_system">create your nToklo account</a> to start using this plugin.', 'default' ); ?></p>
			</div>
		<?php
		} else {
			?>
			<div class=""></div>
			<?php
		}
		
		
		if ( get_option( "nt_delete_settings" ) ) {
			?>
				<div class="error">
					<p><?php _e( '<strong>WP e-Commerce Recommendations:</strong> Your plugin settings have been deleted.', 'default' ); ?></p>
				</div>
			<?php
				delete_option('key_secret_json_string');
		}
		
		
/*
	if( json_decode($json) && !empty($json) ) {	
		if( get_option( "nt_delete_settings" ) ){
		?>
			<div class="error">
				<p><?php _e( 'Your plugin has been reset.', 'default' ); ?></p>
			</div>
		<?php
			delete_option('key_secret_json_string');
		}else{	
		?>
			<div class="updated">
				<p><?php _e( '<strong>WP e-Commerce Recommendations:</strong> Ok, you\'re set up now. If you\'re a new user you must activate your account via the link we just emailed you.', 'default' ); ?></p>
			</div>		
		<?php
		}
	}
*/	
	
	
}
add_action( 'admin_notices', 'my_admin_notice' );	
	

	// Recommendations & chart helper functions

	function nt_fetch_data ($end_point, $query_params) {
		global $post;
		
				
		$url 					= "https://api.ntoklo.com/" . $end_point . $query_params;
		$signature 				= hash_hmac("sha1", "GET&" . $url, get_option("key") . "&" . get_option("secret"));
		$headerAuthorization 	= get_option("key") . ":" . $signature;
		$headers 				= array ("Authorization" => "NTOKLO " . $headerAuthorization);
		
		if (strstr($_SERVER["SERVER_NAME"], get_option("nt_app_domain")) != false) {
			$response 			= wp_remote_get($url, array("headers" => $headers));

			//echo $response;
			if (is_wp_error($response)) {
				return "error"; // store isn't linked to app
			} else {
			$response 				= json_decode($response["body"]);
				
				return $response;	
			}
		} else {
			return array(); // no activity
		}
	}

	function nt_parse_response ($response, $widget_options) {
		$max_items 			= $widget_options["max_items"];
		$image_height 		= $widget_options["image_height"];
		$image_width 		= $widget_options["image_width"];
		$i 					= 0;

		if ($response != null) {
			if ( isset($response->items) ) {
				$item_list 	= $response->items;
			} else {
				$item_list 	= $response;
			}
			
			//print_r($item_list);
			
			$id_list 			= array();

			foreach($item_list as $key => $value) {
				if ( isset($value->id) ) {
					$the_id = $value->id;
				} else {
					$the_id = $value->product->id;
				}
				array_push($id_list, str_replace('"', "", $the_id));
			}

			$post_ids 		= array(
								"post_type" 		=> "wpsc-product",
								"post__in" 			=> $id_list,
								"posts_per_page" 	=> $max_items
							);

			$products 		= get_posts($post_ids);
			

			foreach($products as $product) {
				$product_id 	= $product->ID;
				$product_title 	= $product->post_title;
				$product_price 	= wpsc_currency_display(wpsc_calculate_price($product_id));
				$product_link 	= get_permalink($product_id);
				$product_image 	= wpsc_the_product_thumbnail($image_width, $image_height, $product->ID, "");

				foreach($item_list as $key => $value) {
					if ( isset($value->id) ) {
						if (str_replace('"', '', $value->id) == (string)$product_id) {
							$item_list[$key]->item_data = array(
								"title" => $product_title,
								"price" => $product_price,
								"link" 	=> $product_link,
								"image" => $product_image
							);
						} else {
							$in_array = false;
						}
					} elseif ( isset($value->product->id) ) {
						if (str_replace('"', '', $value->product->id) == (string)$product_id) {
							$item_list[$key]->item_data = array(
								"title" => $product_title,
								"price" => $product_price,
								"link" 	=> $product_link,
								"image" => $product_image
							);
						}
					}
				}
			}

			foreach($item_list as $item => $data) {
				if ( !isset($data->item_data) ) {
					unset($item_list[$item]);
				}
			}
			return $item_list;
		} else {
			return false;
		}
	}


	//set widget thumbnail images
	function nt_set_widget_configuration ($instance, $chart_or_recommendation) {
		if ( isset($instance["image_width"]) ) {
			$image_width 	= $instance["image_width"];
		} else {
			if ($chart_or_recommendation == "recommendation") {
				$image_width 	= 220;
			} else {
				$image_width 	= 100;
			}
		}

		if ( isset($instance["image_height"]) ) {
			$image_height 	= $instance["image_height"];
		} else {
			if ($chart_or_recommendation == "recommendation") {
				$image_height 	= 140;
			} else {
				$image_height 	= 100;
			}
		}

		if ( isset($instance["widget_color"]) ) {
			$widget_color 	= strtolower($instance["widget_color"]);
		} else {
			$widget_color 	= "plum";
		}

		if ( isset($instance["max_items"]) ) {
			$max_items 		= $instance["max_items"];
		} else {
			$max_items 		= 10;
		}

		return array(
			"image_height" 	=> $image_height,
			"image_width" 	=> $image_width,
			"widget_color" 	=> $widget_color,
			"max_items" 	=> $max_items
		);
	}


// render a chart (widget)
class ntoklo_chart extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'ntoklo_chart', // Base ID
			'WP e-Commerce Chart', // Name
			array( 'description' => __( 'A Chart for your WP eCommerce store', 'text_domain' ), ) // Args
				);
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
			
		global $post;
		global $category_for_request;
		global $event_type;
		global $chart_on_settings_page;
		global $no_activity;
		global $account_not_activated;

		$no_activity = false;
	
		$event_for_clickthrough = $event_type;

		if (!get_option( "key_secret_json_string" )) {
			return;
		}

		if ( isset( $instance[ "title" ] ) ) {
			$widget_title = apply_filters( "widget_title", $instance["title"] );
		} else {
			$widget_title = __( "Best sellers", "text_domain" );
		}

		if ( isset( $instance[ "max_items" ] ) ) {
			$max_items = $instance[ "max_items" ];
		} else {
			$max_items = 10;
		}

		if ( isset( $instance[ "tw" ] ) ) {
			$tw = $instance["tw"];
		} else {
			$tw = "DAILY";
		}

		// fetch chart
		$query_params 	 = "?maxItems=" . $max_items;
		$query_params 	.= "&tw=" . $tw;
		
		
		if ( isset($event_type) ) {
			$query_params .= "&action=" . $event_type;
		} else {
			$event_for_clickthrough = get_the_title();
		}

		$response 		 = nt_fetch_data("chart", $query_params);
		

		if (isset($instance["from_settings"]) && ($response == "error" || $response->error)) {
			$no_activity = true;
			$chart_on_settings_page = false;
		} else {
			$widget_options  = nt_set_widget_configuration($instance, "chart");
			$products 		 = nt_parse_response($response, $widget_options);

			if ($products != false && count($products) > 0) {

				echo $args['before_widget'];
				$image_height 	 = $widget_options["image_height"];
				$image_width 	 = $widget_options["image_width"];
				$widget_color	 = $widget_options["widget_color"];

				// render the content
				echo '<div class="nt_wrapper nt_chart nt_' . $widget_color . '">
				<p class="nt_header">' . $widget_title . '</p>
				<table cellspacing="0" class="nt_widget">
				<tbody>';

				foreach ($products as $product) {
					$chart_current_position = $product->currentPosition;
					$chart_peak_position 	= $product->peakPosition;
					$chart_times_on 		= $product->timesOnChart;
					$product_title 			= $product->item_data["title"];
					$product_price 			= $product->item_data["price"];
					$product_link 			= $product->item_data["link"];
					$product_image 			= $product->item_data["image"];

					echo '<tr>
					<td class="nt_peak_time_wrapper">
					<table cellspacing="2" class="nt_item_info">
					<tbody>
					<tr>
					<td class="nt_position" rowspan="2">' . $chart_current_position . '</td>
					<td class="nt_peak" title="Peak position">' . $chart_peak_position . '</td>
					</tr>
					<tr>
					<td class="nt_time">' . $chart_times_on . '</td>
					</tr>
					</tbody>
					</table>
					</td>
					<td class="nt_table_item"><a href="' . $product_link . '?clickthrough=chart&category=' . $event_for_clickthrough . '">' . $product_title . '</a></td>
					<td class="nt_img_wrap"><img src="' . $product_image . '" alt="' . $product_title . '" /></td>
					</tr>';
				}

				echo '		</tbody>
				<tfoot>
				<tr>
				<td class="nt_peak_time_wrapper" data-title="nt_peak">
				<table cellspacing="3" class="nt_item_info">
				<tbody>
				<tr>
				<td class="nt_position" rowspan="2">#</td>
				<td class="nt_peak">
				<span class="nt_table_icon" title="Peak position">
				<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="14px" height="14px" viewBox="3 3 14 14" enable-background="new 3 3 14 14" xml:space="preserve">
				<path d="M15.635,5.865c0.125-0.172,0.188-0.156,0.188,0.046v9.022H4.499c-0.095,0-0.154-0.028-0.176-0.082c-0.024-0.055-0.004-0.121,0.059-0.2l2.702-3.384c0.156-0.171,0.313-0.179,0.47-0.021l0.869,0.773c0.079,0.063,0.16,0.091,0.247,0.083c0.086-0.009,0.153-0.051,0.199-0.13l1.855-2.796c0.125-0.203,0.274-0.219,0.447-0.047l1.315,1.223c0.156,0.156,0.305,0.14,0.445-0.047L15.635,5.865z"/>
				<path d="M23.018,7.037c0.915-0.914,2.015-1.371,3.303-1.371c1.287,0,2.389,0.458,3.302,1.371c0.914,0.916,1.372,2.016,1.372,3.303c0,1.287-0.458,2.388-1.372,3.304c-0.914,0.914-2.015,1.37-3.302,1.37c-1.288,0-2.389-0.456-3.303-1.37c-0.915-0.916-1.372-2.017-1.372-3.304C21.646,9.054,22.103,7.953,23.018,7.037z M26.32,13.998c1.016,0,1.88-0.358,2.591-1.077c0.711-0.717,1.067-1.577,1.067-2.58c0-1.017-0.356-1.879-1.067-2.59C28.2,7.038,27.337,6.683,26.32,6.683c-1.003,0-1.864,0.355-2.581,1.067c-0.717,0.711-1.077,1.574-1.077,2.59c0,1.003,0.36,1.863,1.077,2.58C24.456,13.64,25.317,13.998,26.32,13.998z M26.686,7.699v2.479l1.524,1.525l-0.508,0.507l-1.728-1.727V7.699H26.686z"/>
				</svg>
				</span>
				</td>
				</tr>
				<tr>
				<td class="nt_time">
				<span class="nt_table_icon" title="Time on chart">
				<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="14px" height="14px" viewBox="3 3 14 14" enable-background="new 3 3 14 14" xml:space="preserve">
				<path d="M-0.74,5.49c0.125-0.172,0.188-0.156,0.188,0.046v9.022h-11.324c-0.095,0-0.154-0.028-0.176-0.082c-0.024-0.055-0.004-0.121,0.059-0.2l2.702-3.384c0.156-0.171,0.313-0.179,0.47-0.021l0.869,0.773c0.079,0.063,0.16,0.091,0.247,0.083c0.086-0.009,0.153-0.051,0.199-0.13l1.855-2.796C-5.526,8.6-5.377,8.583-5.204,8.755l1.315,1.223c0.156,0.156,0.305,0.14,0.445-0.047L-0.74,5.49z"/>
				<path d="M6.643,6.662c0.915-0.914,2.015-1.371,3.303-1.371c1.287,0,2.389,0.458,3.302,1.371c0.914,0.916,1.372,2.016,1.372,3.303c0,1.287-0.458,2.388-1.372,3.304c-0.914,0.914-2.015,1.37-3.302,1.37c-1.288,0-2.389-0.456-3.303-1.37c-0.915-0.916-1.372-2.017-1.372-3.304C5.271,8.679,5.728,7.578,6.643,6.662z M9.945,13.623c1.016,0,1.88-0.358,2.591-1.077c0.711-0.717,1.067-1.577,1.067-2.58c0-1.017-0.356-1.879-1.067-2.59c-0.711-0.712-1.574-1.067-2.591-1.067c-1.003,0-1.864,0.355-2.581,1.067c-0.717,0.711-1.077,1.574-1.077,2.59c0,1.003,0.36,1.863,1.077,2.58C8.081,13.265,8.942,13.623,9.945,13.623z M10.311,7.324v2.479l1.524,1.525l-0.508,0.507L9.6,10.108V7.324H10.311z"/>
				</svg>
				</span>
				</td>
				</tr>
				</tbody>
				</table>
				</td>
				<td class="nt_table_item" colspan="2"></td>
				</tr>
				</tfoot>
				</table>
				</div>';

				echo $args['after_widget'];
			} else {
				if ($instance["from_settings"] == true) {
					$no_activity = true;
					$chart_on_settings_page = false;
					}
			}
		}
	}//End of widget function 

	/**
	 * Back-end widget form.
	 *
     * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		global $nt_recommendations_widget_colors;
	
		
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		} else {
			$title = __( 'Best sellers', 'text_domain' );
		}

		if ( isset( $instance[ 'max_items' ] ) ) {
			$max_items = $instance[ 'max_items' ];
		} else {
			$max_items = 10;
		}

		if ( isset( $instance[ 'tw' ] ) ) {
			$typeChecked = $instance[ 'tw' ];
		}
	?>

			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('title'); ?>" name="<?php echo $this -> get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('maxItems'); ?>"><?php _e('Max items: (must be less than 100)'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('max_items'); ?>" name="<?php echo $this -> get_field_name('max_items'); ?>" type="text" value="<?php echo esc_attr($max_items); ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('tw'); ?>"><?php _e('Type:'); ?></label> 
				<select id="<?php echo $this -> get_field_id('tw'); ?>" name="<?php echo $this -> get_field_name('tw'); ?>">
					<option value="DAILY" <?php if (isset($instance["tw"])) { selected($instance['tw'], 'DAILY');} ?>>Daily</option>
					<option value="WEEKLY" <?php if (isset($instance["tw"])) { selected($instance['tw'], 'WEEKLY');} ?>>Weekly</option>
				</select>
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('widget_color'); ?>"><?php _e('Color:'); ?></label> 
				<select id="<?php echo $this -> get_field_id('widget_color'); ?>" name="<?php echo $this -> get_field_name('widget_color'); ?>">
					<?php
					foreach ($nt_recommendations_widget_colors as $key => $value) {
						echo '<option value="' . $key . '"';
						if (isset($instance['widget_color'])) {
							selected($instance['widget_color'], $key);
						}
						echo '>' . $value . '</option>';
					}
					?>
				</select>
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('image_width'); ?>"><?php _e('Image width:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('image_width'); ?>" name="<?php echo $this -> get_field_name('image_width'); ?>" type="text" value="<?php
				if (isset($instance["image_width"])) {
					 echo esc_attr($instance["image_width"]);
				} else {
					 echo "100";
				}
				 ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('image_height'); ?>"><?php _e('Image height:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('image_height'); ?>" name="<?php echo $this -> get_field_name('image_height'); ?>" type="text" value="<?php		
				if (isset($instance["image_height"])) {
					 echo esc_attr($instance["image_height"]);
				} else {
					 echo "100";
				}
 				?>" />
			</div>
<?php
	}//End of form function 


	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance["title"] = ( ! empty( $new_instance["title"] ) ) ? strip_tags( $new_instance["title"] ) : "";
		$max_items = ( ! empty( $new_instance["max_items"] ) ) ? strip_tags( $new_instance["max_items"] ) : "";
		if (intval($max_items) != 0) {
			if (intval($max_items) > 100) {
				$instance["max_items"] = 100;
			} else {
				$instance["max_items"] = intval($max_items);
			}
		} else {
			$instance["max_items"] = 10;
		}
		
		$instance["tw"] = $new_instance["tw"];
		$instance["widget_color"] = $new_instance["widget_color"];
		$instance["image_width"] = $new_instance["image_width"];
		$instance["image_height"] = $new_instance["image_height"];

		return $instance;
	}
}//End of class


// register widgets
function nt_register_ntoklo_chart() {
	
	$json = get_option( "key_secret_json_string" );
	
	if ( !json_decode($json) || empty($json) || $account_not_activated == true ){
		
		unregister_widget("ntoklo_chart");
		//echo $json;	
	} elseif ( json_decode($json) ) {			
		register_widget( "ntoklo_chart" );
		//echo $json;
	}	
}


add_action( "widgets_init", "nt_register_ntoklo_chart" );
/*-------------------------------------------------------------------------------*/

//Shortcode.

// Chart
function nt_chart_shortcode ( $atts ) {
	extract( shortcode_atts( array(
		"title" => "Best sellers",
		"max_items" => "10",
		"image_width" => 100,
		"image_height" => 100,
		"widget_color" => plum,
		"tw" => "DAILY"
	), $atts ) );

	the_widget("ntoklo_chart", "title=${title}&image_width=${image_width}&image_height=${image_height}&widget_color=${widget_color}&max_items=${max_items}&tw=${tw}");
}
add_shortcode("ntoklo_chart", "nt_chart_shortcode");


// render recommendations (widget)
class ntoklo_recommendations extends WP_Widget {
	/**
	* Register widget with WordPress.
	*/
	function __construct() {
		parent::__construct('ntoklo_recommendations', // Base ID
			'WP e-Commerce Recommendations', // Name
			array('description' => __('Recommendations for your WP eCommerce store', 'text_domain'), ) // Args
		);
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		global $post;
		global $event_type;

		if ( isset($event_type) ) {
			$event_for_clickthrough = $event_type;
		} else {
			$event_for_clickthrough = get_the_title();
		}

		if (!get_option( "key_secret_json_string" )) {
			return;
		}

		if ( isset($instance["title"]) ) {
			$title = apply_filters( 'widget_title', $instance['title'] );
		} else {
			$title = "Recommended for you";
		}

		if ( isset($instance["layout"]) ) {
			$layout = $instance["layout"];
		} else {
			$layout = "row";
		}
		
		//need to be out into function --------------------------------
		// fetch recommendations
		$query_params = "?userId=" . wpsc_get_current_customer_id();
		$product_id 	 = wpsc_the_product_id();
		$query_params 	.= "&productId=" . $product_id;
		$product_category_obj = wp_get_object_terms(wpsc_the_product_id(), 'wpsc_product_category');

		if (count($product_category_obj) > 0) {
			$category_for_request = end($product_category_obj)->name;
			$query_params .= "&scope=category&value=" . $category_for_request;
		}

		$response 		= nt_fetch_data("recommendation", $query_params);
		
		// deal with the response 
		if ($response != "error") {
			$widget_options = nt_set_widget_configuration($instance, "recommendation");

			$widget_color 	= $widget_options["widget_color"];
			$image_height 	= $widget_options["image_height"];
			$image_width 	= $widget_options["image_width"];

			$products 		= nt_parse_response($response, $widget_options);

			if (count($products) > 0) {
					
				echo $args['before_widget'];

				// get the layout settings
				$layout 		= "nt_column";
				$is_grid		= false;
				$grid_columns 	= 2;

				if ( isset($instance["layout"]) ) {
					if ($instance["layout"] == "column_image_above") {
						$layout = "nt_column nt_img_above";
					} elseif ($instance["layout"] == "column_image_right") {
						$layout = "nt_column nt_img_right";
					} elseif ($instance["layout"] == "grid_2_column") {
						$layout = "nt_grid nt_2_column";
						$is_grid = true;
						$grid_columns = 2;
					} elseif ($instance["layout"] == "grid_3_column") {
						$layout = "nt_grid nt_3_column";
						$is_grid = true;
						$grid_columns = 3;
					} elseif ($instance["layout"] == "grid_4_column") {
						$layout = "nt_grid nt_4_column";
						$is_grid = true;
						$grid_columns = 4;
					} elseif ($instance["layout"] == "chart") {
						$layout = "nt_chart";
					} elseif ($instance["layout"] == "row_3") {
						$layout = "nt_row nt_r3";
					} elseif ($instance["layout"] == "row_4") {
						$layout = "nt_row nt_r4";
					}
			} else {
				$layout = "nt_row nt_r3";
			}

				// render the content
		if ($is_grid == false) {
				if ($layout == "nt_column nt_img_right") {
						
					echo '<style type="text/css">
							.nt_wrapper.nt_img_right .nt_widget .nt_item_wrap .nt_product_title {
									margin-right: ' . ($image_width + 10) . 'px;
								}
								.nt_wrapper.nt_img_right .nt_widget div.nt_img_wrap,
								.nt_wrapper.nt_img_right .nt_widget div.nt_img_wrap img {
									height: ' . $image_height . 'px;
									width: ' . $image_width . 'px;
								}
							</style>';
					}

					echo '<div class="nt_wrapper clearfix ' . $layout . ' nt_' . $widget_color . '">
							  <p class="nt_header">' . $title . '</p>
							  <ul class="nt_widget clearfix">';

					foreach ($products as $product) {
							$product_title 	= $product->item_data["title"];
							$product_price 	= $product->item_data["price"];
							$product_link 	= $product->item_data["link"];
							$product_image 	= $product->item_data["image"];

							echo '<li>
									<div class="nt_item_wrap">
										<div class="nt_img_wrap">
											<a href="' . $product_link . '?clickthrough=recommendation&category=' . $event_for_clickthrough . '">
												<img src="' . $product_image . '" alt="' . $product_title . '" />
											</a>
										</div>
										<div class="nt_info_wrap">
											<span class="nt_product_title">' . $product_title . '</span>
											<span class="nt_product_price">' . $product_price . '</span>
											<a class="nt_btn" href="' . $product_link . '?clickthrough=recommendation&category=' . $event_for_clickthrough . '">
												<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="10.853px" height="11.229px" viewBox="0 0 10.853 11.229" enable-background="new 0 0 10.853 11.229" xml:space="preserve">
													<g>
														<path fill="#fff" d="M8.825,6.164l-4.367,4.361c-0.154,0.154-0.375,0.248-0.603,0.248c-0.229,0-0.449-0.094-0.604-0.248l-0.509-0.502C2.589,9.862,2.495,9.64,2.495,9.413c0-0.229,0.094-0.449,0.248-0.604l3.255-3.255L2.743,2.305c-0.154-0.16-0.248-0.382-0.248-0.609s0.094-0.449,0.248-0.603L3.252,0.59C3.406,0.43,3.627,0.336,3.855,0.336c0.228,0,0.448,0.094,0.603,0.254l4.367,4.361c0.154,0.153,0.248,0.375,0.248,0.603S8.979,6.003,8.825,6.164z"/>
													</g>
												</svg>
											</a>
										</div>
									</div>
								  </li>';
								}
					echo '	  </ul>
						  <div class="nt_logo"></div>
					  </div>';
				} else {
					$i 					= 0;
					$offset_for_closing = 0;
					$how_many_products 	= count($products);
					$how_many_rows 		= floor($how_many_products / $grid_columns) == 0 ? 1 : floor($how_many_products / $grid_columns);
					$last_item 			= $how_many_rows * $grid_columns;
					if ($grid_columns > $how_many_products) {
						$grid_columns = $how_many_products;
					}

					echo '<div class="nt_wrapper nt_grid nt_' . $grid_columns . '_column nt_' . $widget_color . '">
							<p class="nt_header">' . $title . '</p>
							<div class="nt_widget clearfix">';

							foreach ($products as $product) {
								if ($i < $last_item && isset($product->item_data) ) {
									$product_title 	= $product->item_data["title"];
									$product_price 	= $product->item_data["price"];
									$product_link 	= $product->item_data["link"];
									$product_image 	= $product->item_data["image"];

									if ($i % $grid_columns == 0) {
										$offset_for_closing = $i;
										echo '<div class="nt_row clearfix">';
									}

										echo '<div class="nt_item_wrap">
												<div class="nt_img_wrap">
													<a href="' . $product_link . '?clickthrough=recommendation&category=' . $event_for_clickthrough . '"><img src="' . $product_image . '" alt="' . $product_title . '" /></a>
												</div>
												<span class="nt_product_title">' . $product_title . '</span>
												<span class="nt_product_price">' . $product_price . '</span>
												<a class="nt_btn" href="' . $product_link . '?clickthrough=recommendation&category=' . $event_for_clickthrough . '">
													<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="10.853px" height="11.229px" viewBox="0 0 10.853 11.229" enable-background="new 0 0 10.853 11.229" xml:space="preserve">
														<g>
															<path fill="#fff" d="M8.825,6.164l-4.367,4.361c-0.154,0.154-0.375,0.248-0.603,0.248c-0.229,0-0.449-0.094-0.604-0.248l-0.509-0.502C2.589,9.862,2.495,9.64,2.495,9.413c0-0.229,0.094-0.449,0.248-0.604l3.255-3.255L2.743,2.305c-0.154-0.16-0.248-0.382-0.248-0.609s0.094-0.449,0.248-0.603L3.252,0.59C3.406,0.43,3.627,0.336,3.855,0.336c0.228,0,0.448,0.094,0.603,0.254l4.367,4.361c0.154,0.153,0.248,0.375,0.248,0.603S8.979,6.003,8.825,6.164z"/>
														</g>
													</svg>
												</a>
											</div>';

									if ($i - $offset_for_closing + 1 == $grid_columns) {
										echo '</div>';
										$offset_for_closing = $i;
									}
								}
								$i = $i + 1;
							}

					echo '	</div>
							<div class="nt_logo"></div>
						</div>';
				}

				echo $args['after_widget'];
			}
		}
		}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
			
		global $nt_recommendations_widget_layout_options;
		global $nt_recommendations_widget_colors;

		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		} else {
			$title = __( 'Recommended for you', 'text_domain' );
		}
		
		if ( isset( $instance[ 'max_items' ] ) ) {
			$max_items = $instance[ 'max_items' ];
		} else {
			$max_items = 9;
		}

		?>

			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('title'); ?>" name="<?php echo $this -> get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('max_items'); ?>"><?php _e('Max items: (must be 9 or less)'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('max_items'); ?>" name="<?php echo $this -> get_field_name('max_items'); ?>" type="text" value="<?php echo esc_attr($max_items); ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('layout'); ?>"><?php _e('Layout:'); ?></label> 
				<select id="<?php echo $this -> get_field_id('layout'); ?>" name="<?php echo $this -> get_field_name('layout'); ?>">
					<?php
					foreach ($nt_recommendations_widget_layout_options as $key => $value) {
						echo '<option value="' . $key . '"';
						if (isset($instance['layout'])) {
							selected($instance['layout'], $key);
						}
						echo '>' . $value . '</option>';
					}
					?>
				</select>
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('widget_color'); ?>"><?php _e('Color:'); ?></label> 
				<select id="<?php echo $this -> get_field_id('widget_color'); ?>" name="<?php echo $this -> get_field_name('widget_color'); ?>">
					<?php
					foreach ($nt_recommendations_widget_colors as $key => $value) {
						echo '<option value="' . $key . '"';
						if (isset($instance['widget_color'])) {
							selected($instance['widget_color'], $key);
						}
						echo '>' . $value . '</option>';
					}
					?>
				</select>
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('image_width'); ?>"><?php _e('Image width:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('image_width'); ?>" name="<?php echo $this -> get_field_name('image_width'); ?>" type="text" value="<?php
				
				if (isset($instance["image_width"])) {
					echo esc_attr($instance["image_width"]);	
				} else {
					echo "220";
				}
 ?>" />
			</div>
			<div class="nt_row">
				<label for="<?php echo $this -> get_field_id('image_height'); ?>"><?php _e('Image height:'); ?></label> 
				<input class="widefat" id="<?php echo $this -> get_field_id('image_height'); ?>" name="<?php echo $this -> get_field_name('image_height'); ?>" type="text" value="<?php
				
				if (isset($instance["image_height"])) {
					 echo esc_attr($instance["image_height"]);
				} else {
					 echo "140";
				}
 ?>" />
			</div>
			<?php }

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
			
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$max_items = ( ! empty( $new_instance['max_items'] ) ) ? strip_tags( $new_instance['max_items'] ) : '';
		
		if (intval($max_items) != 0) {
			if (intval($max_items) > 9) {
				$instance["max_items"] = 9;
			} else {
				$instance["max_items"] = intval($max_items);
			}
		} else {
			$instance["max_items"] = 9;
		}
		
		$instance['layout'] = $new_instance['layout'];
		$instance['widget_color'] = $new_instance['widget_color'];
		$instance['image_width'] = $new_instance['image_width'];
		$instance['image_height'] = $new_instance['image_height'];

		return $instance;
	}
}// End render recommendations (widget)


//output recommendations widgets
function register_ntoklo_recommendations() {
	$json = get_option( "key_secret_json_string" );
	//echo $json;
		
	if ( !json_decode($json) || empty($json) || $account_not_activated == true ){
		unregister_widget( "ntoklo_recommendations" );
	} elseif ( json_decode($json) ){		
		register_widget( "ntoklo_recommendations" );
	}
}
add_action( "widgets_init", "register_ntoklo_recommendations" );

// Shortcodes

// Recommendations
function nt_rec_shortcode ( $atts ) {
	extract( shortcode_atts( array(
		"title" => "Recommended for you",
		"max_items" => "3",
		"layout" => "row",
		"image_width" => 220,
		"image_height" => 140,
		"widget_color" => plum
	), $atts ) );

	the_widget("ntoklo_recommendations", "layout=${layout}&image_width=${image_width}&image_height=${image_height}&widget_color=${widget_color}&max_items=${max_items}");
}
add_shortcode("ntoklo_recommendations", "nt_rec_shortcode");
 //*---------------------------------------------------------------------------------------------------*/
?>
<?php
/*
Plugin Name: wpsync-webspark
Plugin URI:  https://github.com/webinf77/wpsync-webspark
Description: wpsync-webspark
Version:     1.0
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

function wpmete_on_activation_wc(){

	$wpmetetxt = "
	# WP Maximum Execution Time Exceeded
	<IfModule mod_php5.c>
		php_value max_execution_time 3600
	</IfModule>";

	$htaccess = get_home_path().'.htaccess';
	$contents = @file_get_contents($htaccess);
	if(!strpos($htaccess,$wpmetetxt))
	file_put_contents($htaccess,$contents.$wpmetetxt);
}

/* On deactivation delete code (dc) from htaccess file */

function wpmete_on_deactivation_dc(){

	$wpmetetxt = "
	# WP Maximum Execution Time Exceeded
	<IfModule mod_php5.c>
		php_value max_execution_time 3600
	</IfModule>";

	$htaccess = get_home_path().'.htaccess';
	$contents = @file_get_contents($htaccess);
	file_put_contents($htaccess,str_replace($wpmetetxt,'',$contents));
	
}

register_activation_hook(   __FILE__, 'wpmete_on_activation_wc' );

register_deactivation_hook( __FILE__, 'wpmete_on_deactivation_dc' );



function getArrayFromUrl(){
// $url = "https://my.api.mockaroo.com/products.json?key=89b23a40";
// $curl = curl_init($url);
// curl_setopt($curl, CURLOPT_URL, $url);
// curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// $headers = array(
   // "Accept: application/json",
// );
// curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
// curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
// curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

// $resp = curl_exec($curl);
// curl_close($curl);
//    $url = plugin_dir_url(__FILE__) . '/products.json';
	$url = file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . 'products.json' );
	$resp = json_decode($url, true);
    return $resp;
}

function deleteProduct(){
    $productswc = wc_get_products(array(
        'limit'  => -1,
        'status' => 'publish',
    ));
	$products = getArrayFromUrl();

    foreach ($productswc as $productwc) {
		$issku =0;
		$dbSku = strtolower($productwc->get_sku());
		foreach ($products as $product) {
			$urlSku = strtolower($product['sku']);
			if ($dbSku === $urlSku) {
				$issku =1;
			}
		}
		if ($issku ==0){wp_delete_post($productwc->get_id());}		
	}
}

function checkProductBySku($urlSku)
{
    $products = wc_get_products(array(
        'limit'  => -1,
        'status' => 'publish',
    ));

    foreach ($products as $product) {
        $dbSku = strtolower($product->get_sku());
        $urlSku = strtolower($urlSku);
        $status = ['exist' => false, 'idProduct' => null];
        if ($dbSku === $urlSku) {
            $status =  ['exist' => true, 'idProduct' => $product->get_id()];
			return  $status;
        }
    }
    return  $status;
}

function insertProducts()
{
	deleteProduct();
    $products = getArrayFromUrl();

    foreach ($products as $product) {

        $productExist = checkProductBySku($product['sku']);

        $imagesFormated = array();
        $finalProduct = [
		    'sku' => $product['sku'],
            'name' => $product['name'],
            'description' => $product['description'],
            'image' => $product['picture'],
            'price' => $product['price'],
            'in_stock' => $product['in_stock']
        ];

        if (!$productExist['exist']) {

            $product = new WC_Product_Simple();
            $product->set_sku($finalProduct['sku']);			
            $product->set_name($finalProduct['name']);
            $product->set_description($finalProduct['description']);
            $product->set_price($finalProduct['price']);
            $product->set_regular_price($finalProduct['price']);	
            $product->set_stock_quantity($finalProduct['in_stock']);			
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_sold_individually(true);
            $product->set_manage_stock(true);

            $product->save();

            upload_image_from_url($finalProduct['image'], $product->get_id());
        } else {
            $idProduct = $productExist['idProduct'];

            $_product = new WC_Product($idProduct);

            try {

                if ($_product->get_name() != $finalProduct['name']) {
                    $_product->set_name($finalProduct['name']);
                }

                if ($_product->get_price() != $finalProduct['price']) {
                    $_product->set_price($finalProduct['price']);
                }

                if ($_product->get_regular_price() != $finalProduct['price']) {
                    $_product->set_regular_price($finalProduct['price']);
                }

                if ($_product->get_description() != $finalProduct['description']) {
                    $_product->set_description($finalProduct['description']);
                }

                if ($_product->get_stock_quantity() != $finalProduct['in_stock']) {
                    $_product->set_stock_quantity($finalProduct['in_stock']);
                }

                $_product->save();
            } catch (\Throwable $th) {
                file_put_contents('error.log', print_r($th, true));
            }
        }
    }
}

function upload_image_from_url($image_url, $attach_to_post = 0, $add_to_media = true)
{
    $remote_image = fopen($image_url, 'r');

    if (!$remote_image) return false;

    $meta = stream_get_meta_data($remote_image);

    $image_meta = false;
    $image_filetype = false;

    if ($meta && !empty($meta['wrapper_data'])) {
        foreach ($meta['wrapper_data'] as $v) {
            if (preg_match('/Content\-Type: ?((image)\/?(jpe?g|png|gif|bmp))/i', $v, $matches)) {
                $image_meta = $matches[1];
                $image_filetype = $matches[3];
            }
        }
    }

    // Resource did not provide an image.
    if (!$image_meta) return false;

    $v = basename($image_url);
    if ($v && strlen($v) > 6) {
        // Create a filename from the URL's file, if it is long enough
        $path = $v;
    } else {
        // Short filenames should use the path from the URL (not domain)
        $url_parsed = parse_url($image_url);
        $path = isset($url_parsed['path']) ? $url_parsed['path'] : $image_url;
    }

    $path = preg_replace('/(https?:|\/|www\.|\.[a-zA-Z]{2,4}$)/i', '', $path);
    $filename_no_ext = sanitize_title_with_dashes($path, '', 'save');

    $extension = $image_filetype;
    $filename = $filename_no_ext . "." . $extension;

    // Simulate uploading a file through $_FILES. We need a temporary file for this.
    $stream_content = stream_get_contents($remote_image);

    $tmp = tmpfile();
    $tmp_path = stream_get_meta_data($tmp)['uri'];
    fwrite($tmp, $stream_content);
    fseek($tmp, 0); // If we don't do this, WordPress thinks the file is empty

    $fake_FILE = array(
        'name'     => $filename,
        'type'     => 'image/' . $extension,
        'tmp_name' => $tmp_path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($stream_content),
    );

    // Trick is_uploaded_file() by adding it to the superglobal
    $_FILES[basename($tmp_path)] = $fake_FILE;

    // For wp_handle_upload to work:
    include_once ABSPATH . 'wp-admin/includes/media.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/image.php';

    $result = wp_handle_upload($fake_FILE, array(
        'test_form' => false,
        'action'    => 'local',
    ));

    fclose($tmp); // Close tmp file
    @unlink($tmp_path); // Delete the tmp file. Closing it should also delete it, so hide any warnings with @
    unset($_FILES[basename($tmp_path)]); // Clean up our $_FILES mess.

    fclose($remote_image); // Close the opened image resource

    $result['attachment_id'] = 0;

    if (empty($result['error']) && $add_to_media) {
        $args = array(
            'post_title'     => $filename_no_ext,
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_mime_type' => $result['type'],
        );

        $result['attachment_id'] = wp_insert_attachment($args, $result['file'], $attach_to_post);

        $attach_data = wp_generate_attachment_metadata($result['attachment_id'], $result['file']);

        wp_update_attachment_metadata($result['attachment_id'], $attach_data);

        if (is_wp_error($result['attachment_id'])) {
            $result['attachment_id'] = 0;
        }
    }

    set_post_thumbnail($attach_to_post, $result['attachment_id']);
    return $result;
}

add_filter('cron_schedules', 'add_every_one_minutes');
function add_every_one_minutes($schedules)
{
    $schedules['every_one_minutes'] = array(
        'interval'  => 300,
        'display'   => __('Every 1 Minutes', 'textdomain')
    );
    return $schedules;
}

// Schedule an action if it's not already scheduled
if (!wp_next_scheduled('add_every_one_minutes')) {
    wp_schedule_event(time(), 'every_one_minutes', 'add_every_one_minutes');
}

// Hook into that action that'll fire every five minutes
add_action('add_every_one_minutes', 'every_one_minutes_event_func');
function every_one_minutes_event_func()
{
    insertProducts();
}
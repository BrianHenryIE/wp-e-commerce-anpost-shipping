<?php
/*
Plugin Name: WP e-Commerce An Post Shipping Rates
Plugin URI: http://www.github.com/brianhenryie
Description: Uses <a href="http://www.anpost.ie/AnPost/PostalRates/Standard+Post.htm">An Post standard postal rates</a>.
Version: 1.0
Author: Brian Henry
Author URI: http://www.brianhenry.ie
License: GPL2
*/


class wpsc_anpost_shipping {

	var $internal_name, $name;

	/**
	 * Constructor
	 *
	 * @return boolean Always returns true.
	 */
	function wpsc_anpost_shipping() {
		$this->internal_name = "anpost";
		$this->name = __( "An Post", 'wpsc' );
		$this->is_external=false;
		return true;
	}

	/**
	 * Returns i18n-ized name of shipping module.
	 *
	 * @return string
	 */
	function getName() {
		return $this->name;
	}

	/**
	 * Returns internal name of shipping module.
	 *
	 * @return string
	 */
	function getInternalName() {
		return $this->internal_name;
	}



	/**
	 * Returns HTML settings form. Should be a collection of <tr> elements containing two columns. An Post has no settings. Maybe add in a "never use packet rate' option.
	 *
	 * @return string HTML snippet.
	 */
	function getForm() {
		
                ob_start();
		// Should there be a polite message here to display in the admin section?
		return ob_get_clean();
	}

	/**
	 * Saves shipping module setting submitted from getForm.
	 *
	 * @return boolean Always returns true.
	 */
	function submit_form() {
	
		return true;
	}

	/**
	 * returns shipping quotes using this shipping module.
	 *
	 * @return array collection of rates applicable.
	 */
	function getQuote() {

		global $wpdb, $wpsc_cart;
                
                $charge = 0;

		if (is_object($wpsc_cart)) {
			
                    $cart_total = $wpsc_cart->calculate_subtotal(true);
                       
                    // Get the weight    
                    $weight = $wpsc_cart->calculate_total_weight()*0.454*1000;
                                      
                    // Figure out their region:
                    
                    // "Ireland" (IE), "UK" (GB), "Europe" , "Rest of World"
                                        
                    if(wpsc_get_customer_meta( 'shipping_same_as_billing' ) == true){
                        $destination_country = $wpsc_cart->selected_country;
                    } else {
                        $destination_country = $wpsc_cart->delivery_country;
                    }
                    
                    if($destination_country ==" "){ // maybe use length here
                        $destination_country = "IE";
                    }
                    
                    if(WPSC_Countries::get_continent($destination_country) != "europe") {
                        $destination = "Rest of World";
                    }elseif($destination_country == "IE"){
                        $destination = "Ireland";
                    }elseif($destination_country == "GB"){
                        $destination = "UK";
                    } else {
                        $destination = "Europe";
                    }
                    
                    $rates = json_decode(file_get_contents(get_site_url()."/wp-content/plugins/wp-e-commerce-anpost-shipping/anpost.json"), TRUE);
                    
                    // What's a good way to determine when to use packets?
                    // Maximum dimensions: for a packet are a combined length, height and depth of 900mm. No individual dimension can exceed 600mm, with a tolerance of 2mm.
                    // Items which qualify for the Packet rate of postage must weigh no more than 2kg.  
                    // http://www.anpost.ie/anpost/what+are+you+sending+details.htm
                    $type = "parcel";
                    
                    foreach($rates[$destination][$type] as $key=>$value) {
                        if($key>$weight) {
                            break;
                        }    
                    }
                    $charge = $value; // Putting this here means the highest price will also get used.
                    // Though maybe the total weight is too high for An Post :/
                    
                    return array ("An Post standard ".$type." to ".$destination." (".($key < 999 ? $key."g)" : ($key/1000)."kg)") => (float) $charge);
            
                }
        
                return false;
	}

	/**
	 * calculates shipping price for an individual cart item.
	 *
	 * @param object $cart_item (reference)
	 * @return float price of shipping for the item.
	 */
	function get_item_shipping(&$cart_item) {
        
                // This might be completely wrong... I haven't touched it (BH)
                // Items have individual shipping specificed ontheir product pages... I think this
                // method may be unrelated to weight.

		global $wpdb, $wpsc_cart;

		$unit_price = $cart_item->unit_price;
		$quantity = $cart_item->quantity;
		$weight = $cart_item->weight;
		$product_id = $cart_item->product_id;

		$uses_billing_address = false;
		foreach ($cart_item->category_id_list as $category_id) {
			$uses_billing_address = (bool)wpsc_get_categorymeta($category_id, 'uses_billing_address');
			if ($uses_billing_address === true) {
				break; /// just one true value is sufficient
			}
		}

		if (is_numeric($product_id) && (get_option('do_not_use_shipping') != 1)) {
			if ($uses_billing_address == true) {
				$country_code = $wpsc_cart->selected_country;
			} else {
				$country_code = $wpsc_cart->delivery_country;
			}

			if ($cart_item->uses_shipping == true) {
				//if the item has shipping
				$additional_shipping = '';
				if (isset($cart_item->meta[0]['shipping'])) {
					$shipping_values = $cart_item->meta[0]['shipping'];
				}
				if (isset($shipping_values['local']) && $country_code == get_option('base_country')) {
					$additional_shipping = $shipping_values['local'];
				} else {
					if (isset($shipping_values['international'])) {
						$additional_shipping = $shipping_values['international'];
					}
				}
				$shipping = $quantity * $additional_shipping;
			} else {
				//if the item does not have shipping
				$shipping = 0;
			}
		} else {
			//if the item is invalid or all items do not have shipping
			$shipping = 0;
		}
		return $shipping;
	}


}

$anpost = new wpsc_anpost_shipping();
$wpsc_shipping_modules[$anpost->getInternalName()] = $anpost;
?>
<?php
/*
 * Plugin Name: House Numbers for WooCommerce Orders
 * Plugin URI: http://wordpress.lowtone.nl/woocommerce-orders-house_numbers
 * Plugin Type: plugin
 * Description: Modify order fields to include a house number.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2013, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\woocommerce\orders\house_numbers
 */

namespace lowtone\woocommerce\orders\house_numbers {
	
	use lowtone\content\packages\Package;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	Package::init(array(
			Package::INIT_SUCCESS => function() {

				/**
				 * Definitions for the address fields.
				 * @var array
				 */
				$_addressFields;

				/**
				 * Define the address fields once.
				 * @var Closure
				 * @return array Returns definitions for the address fields.
				 */
				$addressFields = function() use (&$_addressFields) {
					return isset($_addressFields)
						? $_addressFields
						: ($_addressFields = fields());
				};

				/**
				 * Get a copy of the $fields array with its keys prefixed.
				 * @var Closure
				 * @param string $prefix The prefix to apply to the keys.
				 * @return array Returns a copy of the fields array.
				 */
				$prefixAddressFields = function($prefix) use ($addressFields) {
					return array_combine(array_map(function($key) use ($prefix) {return $prefix . $key;}, array_keys($addressFields())), $addressFields());
				};

				// Replace address fields

				add_filter("woocommerce_checkout_fields", function($fields) use ($prefixAddressFields) {
					if (disabled()) return $fields;
					
					/**
					 * Insert one array into another without losing their keys
					 * (like array_splice() does).
					 * @var Closure
					 * @param array $array A reference to the subject array.
					 * @param int $postion To position where to insert the 
					 * array.
					 * @param array $instert The array to insert.
					 * @return array Returns a copy of the modified array.
					 */
					$insert = function(&$array, $position, $insert) {
						return $array = array_slice($array, 0, $position) 
							+ $insert
							+ array_slice($array, $position);
					};

					// Update billing fields

					if (isset($fields["billing"])) {
						unset($fields["billing"]["billing_address_1"]);
						unset($fields["billing"]["billing_address_2"]);

						$insert($fields["billing"], 3, $prefixAddressFields("billing_"));
					}

					// Update shipping fields

					if (isset($fields["shipping"]) ) {
						unset($fields["shipping"]["shipping_address_1"]);
						unset($fields["shipping"]["shipping_address_2"]);

						$insert($fields["shipping"], 3, $prefixAddressFields("shipping_"));
					}

					return $fields;
				});

				// Updated address values

				add_action("woocommerce_checkout_update_order_meta", function($orderId, $posted) use ($prefixAddressFields) {
					if (disabled()) return;

					/**
					 * Definitions for billing fields.
					 * @var array
					 */
					$billingFields = $prefixAddressFields("billing_");

					/**
					 * Definitions for shipping fields.
					 * @var array
					 */
					$shippingFields = $prefixAddressFields("shipping_");

					// Set default values for address fields

					$posted = array_merge(
						array_fill_keys(array_keys($billingFields), ""), 
						array_fill_keys(array_keys($shippingFields), ""),
						$posted
					);

					/**
					 * Create an address string from values for the provided 
					 * fields.
					 * @var Closure
					 * @param array $fields The fields which values should be 
					 * combined to create a single address string.
					 * @return string Returns the address string created from 
					 * the provided fields.
					 */
					$address = function($fields) use ($posted) {
						return trim(implode(" ", array_map(__NAMESPACE__ . "\\clean", array_intersect_key($posted, $fields))));
					};

					// Update the billing address

					update_post_meta($orderId, "_billing_address_1", $address($billingFields));

					// Update the shipping address

					update_post_meta($orderId, "_shipping_address_1", $address($shippingFields));
				}, 10, 2);

				// Register textdomain
				
				add_action("plugins_loaded", function() {
					load_plugin_textdomain("lowtone_woocommerce_orders_house_numbers", false, basename(__DIR__) . "/assets/languages");
				});

			}
		));

	// Functions
	
	/**
	 * Check if the use of house number fields is disabled. Other plugins could 
	 * use the lowtone_woocommerce_orders_house_numbers_disabled filter to 
	 * disable the use of house number (e.g. for specific countries).
	 * @return bool Returns TRUE if the use of house number fields is disabled 
	 * or FALSE if not.
	 */
	function disabled() {
		return apply_filters("lowtone_woocommerce_orders_house_numbers_disabled", false);
	}

	/**
	 * Get definitions for the address fields.
	 * @return array Returns an array of field definitions.
	 */
	function fields() {
		$fields = array(
				"street" => array(
					"label" => __("Street", "lowtone_woocommerce_orders_house_numbers"),
					"placeholder" => _x("Street", "placeholder", "lowtone_woocommerce_orders_house_numbers"),
					"required" => true,
					"class" => array("form-row-wide", "street-field"),
					"clear" => false
				),
				"house_number" => array(
					"label" => __("House Number", "lowtone_woocommerce_orders_house_numbers"),
					"placeholder" => _x("Number", "placeholder", "lowtone_woocommerce_orders_house_numbers"),
					"required" => true,
					"class" => array("form-row-first", "house-number-field"),
					"clear" => false
				),
				"house_number_extra" => array(
					"label" => __("Extra", "lowtone_woocommerce_orders_house_numbers"),
					"placeholder" => _x("Extra", "placeholder", "lowtone_woocommerce_orders_house_numbers"),
					"required" => false,
					"class" => array("form-row-last", "house-number-extra-field"),
					"clear" => true
				),
			);

		return apply_filters("lowtone_woocommerce_orders_house_numbers_fields", $fields);
	}

	/**
	 * Abstraction for the wc_clean function (use woocommerce_clean before 
	 * WooCommerce v2.1).
	 * @param string $var The subject string.
	 * @return string Returns the cleaned string.
	 */
	function clean($var) {
		return function_exists("wc_clean")
			? wc_clean($var)
			: woocommerce_clean($var);
	}

}
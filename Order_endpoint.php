<?php
/**
 * Plugin Name: Get Orders by Email Endpoint
 * Description: Adds a custom REST API endpoint to fetch WooCommerce orders by billing email.
 * Version: 1.0.0
 * Author: Con
 */
 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
 
class Get_Orders_By_Email_Endpoint {
    protected $namespace = 'path_to_namespace_here';
    protected $route = 'api_route_here';
 
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
 
 
    public function register_routes() {
        register_rest_route($this->namespace, $this->route, array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders'),
            'permission_callback' => array($this, 'permissions_check'),
            'args' => array(
                'billing_email' => array(
                    'required' => true,
                    'validate_callback' => array($this, 'validate_email'),
                    'sanitize_callback' => 'sanitize_email',
                ),
                'number_of_orders' => array(
                    'required' => false,
                    'default' => 5,
                    'validate_callback' => array($this, 'validate_number_of_orders'),
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));
    }
 
    public function permissions_check($request) {
        return current_user_can('manage_options');
    }
 
    public function validate_email($param, $request, $key) {
        return is_email($param);
    }
 
    public function validate_number_of_orders($param, $request, $key) {
        return is_numeric($param);
    }
 
    public function get_orders($request) {
        $billing_email = sanitize_email($request['billing_email']);
        $number_of_orders = absint($request['number_of_orders']);
 
        // Fetch orders using WooCommerce functions
        $args = array(
            'billing_email' => $billing_email,
            'limit' => $number_of_orders,
            'orderby' => 'date',
            'order' => 'DESC'
        );
 
        $orders = wc_get_orders($args);
        if (empty($orders)) {
            return new WP_Error('no_orders', 'No orders found', array('status' => 404));
        }
 
        $response_data = array();
        foreach ($orders as $order) {
            $order_data = $order->get_data();
            $order_array = array(
                'id' => $order_data['id'],
                'parent_id' => $order_data['parent_id'],
                'status' => $order_data['status'],
                'currency' => $order_data['currency'],
                'version' => $order_data['version'],
                'prices_include_tax' => $order_data['prices_include_tax'],
                'date_created' => $order_data['date_created']->date('Y-m-d\TH:i:s'),
                'date_modified' => $order_data['date_modified']->date('Y-m-d\TH:i:s'),
                'discount_total' => $order_data['discount_total'],
                'discount_tax' => $order_data['discount_tax'],
                'shipping_total' => $order_data['shipping_total'],
                'shipping_tax' => $order_data['shipping_tax'],
                'cart_tax' => $order_data['cart_tax'],
                'total' => $order_data['total'],
                'total_tax' => $order_data['total_tax'],
                'customer_id' => $order_data['customer_id'],
                'order_key' => $order_data['order_key'],
                'billing' => $order_data['billing'],
                'shipping' => $order_data['shipping'],
                'payment_method' => $order_data['payment_method'],
                'payment_method_title' => $order_data['payment_method_title'],
                'transaction_id' => $order_data['transaction_id'],
                'customer_ip_address' => $order_data['customer_ip_address'],
                'customer_user_agent' => $order_data['customer_user_agent'],
                'created_via' => $order_data['created_via'],
                'customer_note' => $order_data['customer_note'],
                'date_completed' => $order_data['date_completed'] ? $order_data['date_completed']->date('Y-m-d\TH:i:s') : null,
                'date_paid' => $order_data['date_paid'] ? $order_data['date_paid']->date('Y-m-d\TH:i:s') : null,
                'cart_hash' => $order_data['cart_hash'],
                'number' => $order_data['number'],
                'meta_data' => $this->get_meta_data($order),
                'line_items' => $this->get_order_items($order, 'line_item'),
                'tax_lines' => $this->get_order_items($order, 'tax'),
                'shipping_lines' => $this->get_order_items($order, 'shipping'),
                'fee_lines' => $this->get_order_items($order, 'fee'),
                'coupon_lines' => $this->get_order_items($order, 'coupon'),
                'refunds' => $this->get_order_items($order, 'refund'),
                'payment_url' => $order->get_checkout_payment_url(),
                'is_editable' => $order->is_editable(),
                'needs_payment' => $order->needs_payment(),
                'needs_processing' => $order->needs_processing(),
                'date_created_gmt' => $order_data['date_created']->date('Y-m-d\TH:i:s', 0),
                'date_modified_gmt' => $order_data['date_modified']->date('Y-m-d\TH:i:s', 0),
                'date_completed_gmt' => $order_data['date_completed'] ? $order_data['date_completed']->date('Y-m-d\TH:i:s', 0) : null,
                'date_paid_gmt' => $order_data['date_paid'] ? $order_data['date_paid']->date('Y-m-d\TH:i:s', 0) : null,
                'store_credit_used' => get_post_meta($order_data['id'], '_store_credit_used', true),
                'currency_symbol' => get_woocommerce_currency_symbol($order_data['currency']),
                '_links' => array(
                    'self' => array(
                        array(
                            'href' => rest_url('wc/v3/orders/' . $order_data['id'])
                        )
                    ),
                    'collection' => array(
                        array(
                            'href' => rest_url('wc/v3/orders')
                        )
                    )
                )
            );
            $response_data[] = $order_array;
        }
 
        return rest_ensure_response($response_data);
    }
 
    private function get_meta_data($order) {
        $meta_data = array();
        foreach ($order->get_meta_data() as $meta) {
            $meta_data[] = array(
                'id' => $meta->id,
                'key' => $meta->key,
                'value' => $meta->value,
            );
        }
        return $meta_data;
    }
 
    private function get_order_items($order, $type) {
        $items = array();
        foreach ($order->get_items($type) as $item_id => $item) {
            $items[] = $item->get_data();
        }
        return $items;
    }
    
}
 
new Get_Orders_By_Email_Endpoint();

<?php
/*
Plugin Name: WooCommerce State Shipping Prices
Description: Admin page to manage shipping prices per state in WooCommerce.
Version: 1.0
Author: Tu Nombre
License: GPL2
*/

// Evitar el acceso directo al archivo
if (!defined('ABSPATH')) { exit; }

// Cargar el dominio de texto para traducciones
add_action('plugins_loaded', 'wc_shipping_prices_load_textdomain');
function wc_shipping_prices_load_textdomain() {
    load_plugin_textdomain('woocommerce-state-shipping-prices', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// Hook para agregar elementos al menú de administración
add_action('admin_menu', 'wc_shipping_prices_add_admin_menu');
function wc_shipping_prices_add_admin_menu() {
    add_menu_page(
        __('Precios de Envío por Estado', 'woocommerce-state-shipping-prices'),
        __('Envío por Estado', 'woocommerce-state-shipping-prices'),
        'manage_options',
        'wc-shipping-prices',
        'wc_shipping_prices_render_admin_page',
        'dashicons-admin-generic',
        56
    );
    add_submenu_page(
        'wc-shipping-prices',
        __('Depuración', 'woocommerce-state-shipping-prices'),
        __('Depuración', 'woocommerce-state-shipping-prices'),
        'manage_options',
        'wc-shipping-prices-debug',
        'wc_shipping_prices_render_debug_page'
    );
}

// Función para renderizar la página de administración
function wc_shipping_prices_render_admin_page() {
    $states = wc_shipping_prices_get_states_with_prices();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th><?php _e('Estado', 'woocommerce-state-shipping-prices'); ?></th>
                    <th><?php _e('Precio Actual', 'woocommerce-state-shipping-prices'); ?></th>
                    <th><?php _e('Acción', 'woocommerce-state-shipping-prices'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($states)) : ?>
                    <?php foreach ($states as $state) : ?>
                        <tr data-country="<?php echo esc_attr($state['country_code']); ?>" data-state="<?php echo esc_attr($state['state_code']); ?>">
                            <td><?php echo esc_html($state['state_name']); ?></td>
                            <td class="price"><?php echo esc_html($state['price']); ?></td>
                            <td>
                                <button class="button edit-button" data-country="<?php echo esc_attr($state['country_code']); ?>" data-state="<?php echo esc_attr($state['state_code']); ?>"><?php _e('Editar', 'woocommerce-state-shipping-prices'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="3"><?php _e('No se encontraron estados de envío.', 'woocommerce-state-shipping-prices'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Función para renderizar la página de depuración
function wc_shipping_prices_render_debug_page() {
    $states = wc_shipping_prices_get_states_with_prices();
    echo '<div class="wrap"><h1>Depuración: Estados y Precios de Envío</h1><table class="widefat fixed" cellspacing="0"><thead><tr><th>País</th><th>Estado</th><th>Nombre del Estado</th><th>Precio</th></tr></thead><tbody>';
    if (!empty($states)) {
        foreach ($states as $state) {
            echo '<tr><td>' . esc_html($state['country_code']) . '</td><td>' . esc_html($state['state_code']) . '</td><td>' . esc_html($state['state_name']) . '</td><td>' . esc_html($state['price']) . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="4">No se encontraron estados de envío.</td></tr>';
    }
    echo '</tbody></table></div>';
}

// Función para obtener todos los estados y sus precios de envío
function wc_shipping_prices_get_states_with_prices() {
    if (!class_exists('WC_Shipping_Zones')) { return array(); }
    $zones = WC_Shipping_Zones::get_zones();
    $states_with_prices = array();
    $default_zone = WC_Shipping_Zones::get_zone(0);
    if ($default_zone) {
        $zones[] = array('zone_id' => $default_zone->get_id(), 'zone_name' => $default_zone->get_zone_name());
    }
    foreach ($zones as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['zone_id']);
        $zone_methods = $zone->get_shipping_methods();
        foreach ($zone_methods as $method) {
            if ('flat_rate' !== $method->id) { continue; }
            $cost = $method->get_option('cost');
            $zone_locations = $zone->get_zone_locations();
            foreach ($zone_locations as $location) {
                if ('state' !== $location->type) { continue; }
                $location_code = $location->code;
                $location_country_code = '';
                $location_state_code = '';
                if (strpos($location_code, ':') !== false) {
                    list($location_country_code, $location_state_code) = explode(':', $location_code, 2);
                } elseif (strpos($location_code, '-') !== false) {
                    list($location_country_code, $location_state_code) = explode('-', $location_code, 2);
                } else {
                    error_log("Formato de código de ubicación inesperado: $location_code en wc_shipping_prices_get_states_with_prices.");
                    continue;
                }
                if (empty($location_country_code) || empty($location_state_code)) { continue; }
                $state_name = isset(WC()->countries->states[strtoupper($location_country_code)][strtoupper($location_state_code)]) ? WC()->countries->states[strtoupper($location_country_code)][strtoupper($location_state_code)] : $location_state_code;
                $states_with_prices[] = array(
                    'state_code' => strtoupper($location_state_code),
                    'country_code' => strtoupper($location_country_code),
                    'state_name' => $state_name,
                    'price' => $cost,
                );
            }
        }
    }
    return $states_with_prices;
}

// Encolar scripts y estilos
add_action('admin_enqueue_scripts', 'wc_shipping_prices_enqueue_admin_scripts');
function wc_shipping_prices_enqueue_admin_scripts($hook) {
    if ('toplevel_page_wc-shipping-prices' !== $hook && 'wc-shipping-prices_page_wc-shipping-prices-debug' !== $hook) { return; }
    wp_enqueue_style('wc-shipping-prices-admin-css', plugin_dir_url(__FILE__) . 'css/admin-style.css', array(), '1.0');
    wp_enqueue_script('wc-shipping-prices-admin-js', plugin_dir_url(__FILE__) . 'js/admin-script.js', array('jquery'), '1.0', true);
    wp_localize_script('wc-shipping-prices-admin-js', 'wc_shipping_prices_ajax', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('wc_shipping_prices_nonce')));
}

// Callback para manejar la actualización del precio de envío vía AJAX
add_action('wp_ajax_wc_shipping_prices_update_price', 'wc_shipping_prices_update_price_callback');
function wc_shipping_prices_update_price_callback() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_shipping_prices_nonce')) {
        error_log('Nonce no válido en wc_shipping_prices_update_price_callback.');
        wp_send_json_error(__('Nonce no válido.', 'woocommerce-state-shipping-prices'));
    }
    if (!current_user_can('manage_options')) {
        error_log('Permisos insuficientes en wc_shipping_prices_update_price_callback.');
        wp_send_json_error(__('Permisos insuficientes.', 'woocommerce-state-shipping-prices'));
    }
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
    $state_code = isset($_POST['state_code']) ? sanitize_text_field($_POST['state_code']) : '';
    $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : 0;
    error_log("Actualización de Precio: País - $country_code, Estado - $state_code, Nuevo Precio - $new_price");
    if (empty($country_code) || empty($state_code)) {
        error_log('Código de país o estado no proporcionado en wc_shipping_prices_update_price_callback.');
        wp_send_json_error(__('Código de país o estado no proporcionado.', 'woocommerce-state-shipping-prices'));
    }
    if ($new_price < 0) {
        error_log('El precio es negativo en wc_shipping_prices_update_price_callback.');
        wp_send_json_error(__('El precio debe ser un número positivo.', 'woocommerce-state-shipping-prices'));
    }
    if (!class_exists('WC_Shipping_Zones')) {
        error_log('Clase WC_Shipping_Zones no encontrada en wc_shipping_prices_update_price_callback.');
        wp_send_json_error(__('WooCommerce no está activo.', 'woocommerce-state-shipping-prices'));
    }
    $zones = WC_Shipping_Zones::get_zones();
    $default_zone = WC_Shipping_Zones::get_zone(0);
    if ($default_zone) {
        $zones[] = array('zone_id' => $default_zone->get_id(), 'zone_name' => $default_zone->get_zone_name());
    }
    $updated = false;
    foreach ($zones as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data['zone_id']);
        $zone_methods = $zone->get_shipping_methods();
        foreach ($zone_methods as $method) {
            if ('flat_rate' !== $method->id) { continue; }
            $zone_locations = $zone->get_zone_locations();
            foreach ($zone_locations as $location) {
                if ('state' !== $location->type) { continue; }
                $location_code = $location->code;
                $location_country_code = '';
                $location_state_code = '';
                if (strpos($location_code, ':') !== false) {
                    list($location_country_code, $location_state_code) = explode(':', $location_code, 2);
                } elseif (strpos($location_code, '-') !== false) {
                    list($location_country_code, $location_state_code) = explode('-', $location_code, 2);
                } else {
                    error_log("Formato de código de ubicación inesperado: $location_code en wc_shipping_prices_update_price_callback.");
                    continue;
                }
                if (strtoupper($location_country_code) === strtoupper($country_code) && strtoupper($location_state_code) === strtoupper($state_code)) {
                    $method->update_option('cost', $new_price);
                    $updated = true;
                    break 3;
                }
            }
        }
    }
    if ($updated) {
        error_log("Precio actualizado correctamente para País: $country_code, Estado: $state_code con Nuevo Precio: $new_price.");
        wp_send_json_success(__('Precio actualizado correctamente.', 'woocommerce-state-shipping-prices'));
    } else {
        error_log("No se encontró el estado especificado: País - $country_code, Estado - $state_code en wc_shipping_prices_update_price_callback.");
        wp_send_json_error(__('No se encontró el estado especificado en las zonas de envío.', 'woocommerce-state-shipping-prices'));
    }
}
?>

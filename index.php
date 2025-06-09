<?php
/*
Plugin Name: BauCloudz
Description: Plugin WordPress che supporta Elementor e WooCommerce e integra un modulo per filtrare le variazioni dei prodotti.
Version: 1.0
Author: BauCloudz
Text Domain: baucloudz
*/


use Elementor\Widget_Base;


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Verifica che WooCommerce sia attivo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p><strong>BauCloudz:</strong> WooCommerce non è attivo. Attiva WooCommerce per utilizzare questo plugin.</p></div>';
    });
    return;
}

// Verifica che Elementor sia attivo
if ( ! in_array( 'elementor/elementor.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="error"><p><strong>BauCloudz:</strong> Elementor non è attivo. Attiva Elementor per utilizzare questo plugin.</p></div>';
    });
    return;
}

class BauCloudz {
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_filter( 'woocommerce_variation_is_active', array( $this, 'filter_variation_visibility' ), 10, 2 );

    }

    public function init_plugin() {
        // Integrazione con Elementor (esempio base per registrare un widget personalizzato)
        add_action( 'elementor/widgets/register', array( $this, 'register_custom_elementor_widget' ) );

        // Aggiungi filtro per modificare le variazioni dei prodotti WooCommerce
        add_filter( 'woocommerce_available_variation', array( $this, 'filter_product_variations' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
        add_action( 'wp_ajax_baucloudz_add_to_cart', array( $this, 'baucloudz_add_to_cart_handler' ) );
        add_action( 'wp_ajax_nopriv_baucloudz_add_to_cart', array( $this, 'baucloudz_add_to_cart_handler' ));
        add_action( 'wp_head', array( $this, 'baucloudz_dynamic_open_graph_tags' ), 5 );

        add_filter('get_canonical_url', function($canonical) {
            // Se siamo su una pagina prodotto e nell'URL ci sono parametri di variazione
            if ( is_singular('product') && ! empty($_GET) ) {
                // Usa l'URL corrente (inclusi i parametri) come canonical
                return home_url( $_SERVER['REQUEST_URI'] );
            }
            return $canonical;
        });
    }
    public function register_custom_elementor_widget() {
        require_once( __DIR__ . '/widgets/class-baucloudz-widget.php' );
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \BauCloudz\BauCloudz_Widget() );

    }
    function baucloudz_add_to_cart_handler() {
        if ( ! isset( $_POST['product_id'], $_POST['variation_id'] ) ) {
            wp_send_json_error( 'Dati mancanti.' );
        }
        $product_id   = absint( $_POST['product_id'] );
        $variation_id = absint( $_POST['variation_id'] );
        $quantity     = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
        // Assicurati di passare anche i dati degli attributi della variazione
        $variation    = isset($_POST['variation']) && is_array($_POST['variation']) ? array_map('sanitize_text_field', $_POST['variation']) : array();
    
        $added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
        if ( $added ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Impossibile aggiungere il prodotto al carrello.' );
        }
        wp_die();
    }
    // Funzione per filtrare le variazioni dei prodotti
    public function filter_product_variations( $variation_data ) {
        // Logica di filtro: esempio, rimuovi il campo "price_html" per certe condizioni
        if ( isset( $variation_data['price_html'] ) ) {
            // Aggiungi qui la logica di filtraggio desiderata
            $variation_data['price_html'] = ''; // Modifica o rimuove il prezzo della variazione
        }
        return $variation_data;
    }
    public function filter_variation_visibility( $visible, $variation ) {
        // Logica per determinare se la variazione dovrebbe essere visibile
        // Ad esempio, verifica se la combinazione di attributi della variazione esiste
        // Restituisci true se la variazione è disponibile, altrimenti false
        return $visible;
    }
    public function enqueue_widget_assets() {
        wp_enqueue_style( 'baucloudz-widget-css', plugins_url( 'assets/css/widget.css', __FILE__ ) );
        wp_enqueue_script( 'baucloudz-widget-js', plugins_url( 'assets/js/widget.js', __FILE__ ), array( 'jquery' ), '1.0', true );
        wp_localize_script( 'baucloudz-widget-js', 'ajaxurl', admin_url( 'admin-ajax.php' ));
    }
    public function baucloudz_get_matching_variation( $product ) {
        // Recupera i parametri URL relativi agli attributi (es. attribute_pa_colore, attribute_pa_taglia, ecc.)
        $variation_args = array();
        // Cicla sui parametri URL
        foreach ( $_GET as $key => $value ) {
            if ( strpos( $key, 'attribute_' ) === 0 && ! empty( $value ) ) {
                $variation_args[ $key ] = sanitize_text_field( $value );
            }
        }
        if ( empty( $variation_args ) ) {
            return false;
        }
        
        // Recupera tutte le variazioni disponibili
        $variations = $product->get_available_variations();
        foreach ( $variations as $variation ) {
            $match = true;
            // Confronta ciascun attributo
            foreach ( $variation_args as $attr_key => $attr_value ) {
                if ( ! isset( $variation['attributes'][ $attr_key ] ) || $variation['attributes'][ $attr_key ] !== $attr_value ) {
                    $match = false;
                    break;
                }
            }
            if ( $match ) {
                return $variation;
            }
        }
        return false;
    }
    
    // Hook per iniettare il meta tag dinamico nel <head>
    public function baucloudz_dynamic_open_graph_tags() {
        // Assicurati di essere su una pagina prodotto singola
        if ( ! is_singular( 'product' ) ) {
            return;
        }
        
        global $product;
        if ( ! $product ) {
            return;
        }
        
        // Usa la variazione corrispondente se disponibile, altrimenti il prodotto principale
        $matched_variation = $this->baucloudz_get_matching_variation( $product );
        
        $og_image   = '';
        $og_width   = '';
        $og_height  = '';
        
        if ( $matched_variation && ! empty( $matched_variation['image']['full_src'] ) ) {
            $og_image = esc_url( $matched_variation['image']['full_src'] );
            // Prova ad ottenere l'ID dell'immagine a partire dall'URL
            $attachment_id = attachment_url_to_postid( $matched_variation['image']['full_src'] );
            if ( $attachment_id ) {
                $img_data = wp_get_attachment_image_src( $attachment_id, 'full' );
                if ( $img_data ) {
                    $og_width  = $img_data[1];
                    $og_height = $img_data[2];
                }
            }
        } else {
            // Fallback: usa l'immagine principale del prodotto (featured image)
            $thumbnail_id = $product->get_image_id();
            if ( $thumbnail_id ) {
                $og_image = esc_url( wp_get_attachment_url( $thumbnail_id ) );
                $img_data = wp_get_attachment_image_src( $thumbnail_id, 'full' );
                if ( $img_data ) {
                    $og_width  = $img_data[1];
                    $og_height = $img_data[2];
                }
            }
        }
        
        // Imposta og:url come l'URL corrente (inclusi i parametri, se presenti)
        $og_url = home_url( $_SERVER['REQUEST_URI'] );
        // Tipo oggetto
        $og_type = 'product';
        // Titolo e descrizione: utilizza il titolo del prodotto e la sua short description
        $og_title = get_the_title();
        $og_description = wp_strip_all_tags( $product->get_short_description() );
        
        // Stampa i meta tag Open Graph
        echo '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
        if ( $og_image ) {
            echo '<meta property="og:image" content="' . $og_image . '" />' . "\n";
            // Se sono disponibili le dimensioni, aggiungile
            if ( $og_width && $og_height ) {
                echo '<meta property="og:image:width" content="' . esc_attr( $og_width ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $og_height ) . '" />' . "\n";
            }
        }
        
        // Meta tag Twitter
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        if ( $og_image ) {
            echo '<meta name="twitter:image" content="' . $og_image . '" />' . "\n";
        }
    }
    
    
}

new BauCloudz();
?>
<?php
global $product;

if ( ! $product || ! $product->is_type( 'variable' ) ) {
    return;
}

$attributes   = $product->get_variation_attributes();
$variations   = $product->get_available_variations();
foreach ( $variations as &$variation ) {
    if ( ! empty( $variation['image']['full_src'] ) ) {
        // Ottieni l'ID dell'immagine a partire dall'URL dell'immagine completa
        $attachment_id = attachment_url_to_postid( $variation['image']['full_src'] );
        if ( $attachment_id ) {
            $variation['image']['srcset'] = wp_get_attachment_image_srcset( $attachment_id, 'full' );
            $variation['image']['sizes']  = wp_get_attachment_image_sizes( $attachment_id, 'full' );
        } else {
            $variation['image']['srcset'] = '';
            $variation['image']['sizes']  = '';
        }
    }
}
unset( $variation );
// Recupera nome e range di prezzo
$product_name   = $product->get_name();
$variation_prices = array_map( function( $v ) {
    return $v['display_price'];
}, $variations );
$min_price = wc_price( min( $variation_prices ) );
$max_price = wc_price( max( $variation_prices ) );
?>
<div class="baucloudz-widget">
    <?php foreach ( $attributes as $attribute_name => $options ) : ?>
        <div class="product-attribute">
            <label for="baucloudz-<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
                <?php echo wc_attribute_label( $attribute_name ); ?>
            </label>
            <select id="baucloudz-<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>"
                    class="baucloudz-select"
                    name="attribute_<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
                <option value=""><?php esc_html_e( 'Scegli un\'opzione', 'baucloudz' ); ?></option>
                <?php foreach ( $options as $option ) : 
                    if ( taxonomy_exists( $attribute_name ) ) {
                        $term = get_term_by( 'slug', $option, $attribute_name );
                        $option_label = ( $term && ! is_wp_error( $term ) ) ? $term->name : $option;
                    } else {
                        $option_label = $option;
                    }
                ?>
                    <option value="<?php echo esc_attr( $option ); ?>">
                        <?php echo esc_html( $option_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>
</div>

<!-- Area dove verranno visualizzati nome e prezzo della variazione selezionata -->
<div id="baucloudz-selection-details">
    <p class="BauTitle"> <strong><?php echo esc_html( $product_name ); ?></strong></br>Da <?php echo sprintf(  $min_price,' A ', $max_price ); ?></p>
</div>
<div id="baucloudz-add-to-cart">
    <button type="button" id="baucloudz-add-to-cart-button" disabled>
        <?php esc_html_e( 'Aggiungi al carrello', 'baucloudz' ); ?>
    </button>
</div>

<!-- Passa i dati al JS -->
<script type="text/javascript">
    window.baucloudz_variations = <?php echo wp_json_encode( $variations ); ?>;
    window.baucloudz_product_info = {
        name: <?php echo json_encode( $product_name ); ?>,
        min_price: <?php echo json_encode( $min_price ); ?>,
        max_price: <?php echo json_encode( $max_price ); ?>,
        product_id: <?php echo json_encode( $product->get_id() ); ?>
    };
</script>

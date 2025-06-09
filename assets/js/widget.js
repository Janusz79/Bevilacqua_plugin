jQuery(document).ready(function($) {
    // Funzione per aggiornare le opzioni delle select
    function updateSelectOptions() {
        var currentSelections = {};
        $('.baucloudz-select').each(function(){
            var attr = $(this).attr('name').replace('attribute_', '');
            currentSelections[attr] = $(this).val();
        });

        $('.baucloudz-select').each(function(){
            var attribute = $(this).attr('name').replace('attribute_', '');
            var validOptions = [];

            // Filtra le variazioni in base alle selezioni correnti
            $.each(window.baucloudz_variations, function(index, variation) {
                var valid = true;
                $.each(currentSelections, function(attr, selectedVal) {
                    if (attr === attribute) {
                        return true; // Salta l'attributo corrente
                    }
                    if (selectedVal !== '' && variation.attributes['attribute_' + attr] !== selectedVal) {
                        valid = false;
                        return false;
                    }
                });
                if (valid) {
                    var opt = variation.attributes['attribute_' + attribute];
                    if (opt && $.inArray(opt, validOptions) === -1) {
                        validOptions.push(opt);
                    }
                }
            });

            // Aggiorna le opzioni della select corrente
            $(this).find('option').each(function(){
                var optionVal = $(this).attr('value');
                if (optionVal === '') {
                    $(this).prop('disabled', false);
                    return true;
                }
                $(this).prop('disabled', $.inArray(optionVal, validOptions) === -1);
            });
        });

        // Aggiorna i dettagli della selezione
        updateSelectionDetails(currentSelections);
    }

    // Funzione per aggiornare il testo dei dettagli
    function updateSelectionDetails(currentSelections) {
        var complete = true;
        $('.baucloudz-select').each(function(){
            if ( $(this).val() === '' ) {
                complete = false;
                return false;
            }
        });
        var $details = $('#baucloudz-selection-details');
        var $button  = $('#baucloudz-add-to-cart-button');

        if (!complete) {
            $button.prop('disabled', true).removeData('variation_id');
            $details.html('<p class="BauTitle"> <strong>' + window.baucloudz_product_info.name + ' </strong></br>Da  ' + window.baucloudz_product_info.min_price.replace(/[^\d.,]/g, '') + ' A ' + window.baucloudz_product_info.max_price.replace(/[^\d.,]/g, '') + ' Euro</p>');
        } else {
            // Cerca la variazione corrispondente alle selezioni
            var found = false;
            $.each(window.baucloudz_variations, function(index, variation) {
                var match = true;
                $.each(currentSelections, function(attr, selectedVal) {
                    if (variation.attributes['attribute_' + attr] !== selectedVal) {
                        match = false;
                        return false;
                    }
                });
                if (match) {
                    // Se la variazione è trovata, mostra il prezzo (usando price_html se disponibile)
                    var newHtml = '<p class="BauTitle"> <strong>';
                    newHtml += variation.variation_description.replace(/<[^>]+>/g, '') + ' </strong></br>'
                    newHtml += variation.price_html ? variation.price_html : parseFloat(variation.display_price).toFixed(2) + ' Euro';
                    newHtml += '</p>';
                    $details.html(newHtml);
                    found = true;
                    console.log('variation', variation);
                    var newThumbnail = variation.image.gallery_thumbnail_src; // miniatura per data-thumb
                    var newFull = variation.image.full_src; // URL full per src/href
                    var newSrcset = variation.image.srcset; // srcset generato in PHP
                    var newSizes = variation.image.sizes;   // sizes generato in PHP
                    
                    // Chiama la funzione di aggiornamento della gallery
                    changeImage(newThumbnail, newFull, newSrcset, newSizes);
                    $button.prop('disabled', false)
                        .data('variation_id', variation.variation_id)
                        .data('variation', variation.attributes);
                    return false;
                }
            });
            if (!found) {
                $button.prop('disabled', true).removeData('variation_id');
                $details.html('<p class="BauTitle"> <strong>' + window.baucloudz_product_info.name + ' </strong></br>Da  ' + window.baucloudz_product_info.min_price.replace(/[^\d.,]/g, '') + ' A ' + window.baucloudz_product_info.max_price.replace(/[^\d.,]/g, '') + '</p>')
            }
        }
    }

    // Esegui all'avvio
    updateSelectOptions();

    // Aggiungi listener su ogni select
    $('.baucloudz-select').on('change', function(){
        updateUrlWithOptions();
        updateSelectOptions();
    });

    // Gestione del click sul bottone
    $('#baucloudz-add-to-cart-button').on('click', function(){
        var variation_id = $(this).data('variation_id');
        var variationAttributes = $(this).data('variation'); // Otteniamo l'array degli attributi
        if (!variation_id) {
            alert('Seleziona una variazione valida.');
            return;
        }
        $.ajax({
            url: window.ajaxurl, 
            method: 'POST',
            data: {
                action: 'baucloudz_add_to_cart',
                product_id: window.baucloudz_product_info.product_id,
                variation_id: variation_id,
                quantity: 1,
                variation: variationAttributes // Invia gli attributi della variazione
            },
            success: function(response) {
                if(response.success) {
                    $(document.body).trigger('wc_fragment_refresh');
                    alert('Prodotto aggiunto al carrello!');
                } else {
                    alert('Errore durante l\'aggiunta al carrello: ' + response.data);
                }
            },
            error: function() {
                alert('Errore nella richiesta AJAX.');
            }
        });
    });
    function changeImage(newThumbnail, newFull, newSrcset, newSizes) {
        console.log('newThumbnail:', newThumbnail);
        console.log('newFull:', newFull);
        console.log('newSrcset:', newSrcset);
        console.log('newSizes:', newSizes);
        
        var $gallery = $('.woocommerce-product-gallery');
        
        // Seleziona il primo container immagine nel wrapper
        var $imageContainer = $gallery.find('.woocommerce-product-gallery__wrapper .woocommerce-product-gallery__image').first();
        if (!$imageContainer.length) {
            console.warn('Container immagine non trovato');
            return;
        }
        
        // Aggiorna il data-thumb con la miniatura
        $imageContainer.attr('data-thumb', newThumbnail);
        
        // Aggiorna l'attributo href dell'anchor al nuovo link dell'immagine completa
        var $anchor = $imageContainer.find('a').first();
        $anchor.attr('href', newFull);
        
        // Aggiorna l'immagine principale (con classe wp-post-image)
        var $mainImage = $anchor.find('img.wp-post-image').first();
        $mainImage
            .attr('src', newFull)
            .attr('data-src', newFull)
            .attr('data-large_image', newFull)
            .attr('srcset', newSrcset)
            .attr('sizes', newSizes);
        
        // Aggiorna l'immagine zoom (con classe zoomImg)
        var $zoomImage = $imageContainer.find('img.zoomImg').first();
        $zoomImage
            .attr('src', newFull)
            .attr('srcset', newSrcset)
            .attr('sizes', newSizes);
        $mainImage.one('load', function() {
            $('.woocommerce-product-gallery').wc_product_gallery();
        }).each(function() {
            if (this.complete) $(this).trigger('load');
        });
        // Emetti un evento personalizzato, se necessario, per far reagire altri script
        $gallery.trigger('baucloudzGalleryUpdated', { thumbnail: newThumbnail, full: newFull, srcset: newSrcset, sizes: newSizes });
    }
    
    function updateUrlWithOptions() {
        // Crea un oggetto URLSearchParams a partire dall'URL corrente
        var params = new URLSearchParams(window.location.search);
        
        // Per ogni select con la classe 'baucloudz-select'
        $('.baucloudz-select').each(function() {
            // Prendi il nome dell'attributo (ad es. "attribute_pa_colore") e il valore selezionato
            var attribute = $(this).attr('name');
            var value = $(this).val();
            
            // Se è stato selezionato un valore, lo aggiungiamo o aggiorniamo
            if ( value ) {
                params.set(attribute, value);
            } else {
                // Se non è selezionato nessun valore, lo rimuoviamo dall'URL
                params.delete(attribute);
            }
        });
        
        // Costruisci il nuovo URL
        var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + params.toString();
        
        // Aggiorna l'URL senza ricaricare la pagina
        history.replaceState({}, '', newUrl);
    }
    function setSelectValuesFromUrl() {
        // Crea un oggetto URLSearchParams a partire dall'URL corrente
        var params = new URLSearchParams(window.location.search);
        
        // Per ogni select con la classe 'baucloudz-select'
        $('.baucloudz-select').each(function() {
            var attribute = $(this).attr('name'); // ad esempio, "attribute_pa_colore"
            var value = params.get(attribute);
            if ( value ) {
                $(this).val(value).trigger('change');
            }
        });
    }
    setSelectValuesFromUrl();
});

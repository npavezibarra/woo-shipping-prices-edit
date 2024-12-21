/* JS Code */
// Crea un archivo llamado `admin-script.js` en la carpeta `js/` de tu plugin y añade lo siguiente:
jQuery(document).ready(function($) {
    $('.edit-button').on('click', function() {
        var $button = $(this);
        var $row = $button.closest('tr');
        var $priceCell = $row.find('.price');
        var countryCode = $row.data('country');
        var stateCode = $row.data('state');
        var currentPrice = $priceCell.text();
        if ($button.text() === 'Editar') {
            $priceCell.html('<input type="number" class="price-input" value="' + currentPrice + '" min="0" step="0.01">');
            $button.text('Guardar').addClass('save-button');
        } else {
            var newPrice = $priceCell.find('input').val();
            if (newPrice === '' || isNaN(newPrice) || Number(newPrice) < 0) {
                alert('Por favor, ingresa un precio válido.');
                return;
            }
            $.ajax({
                url: wc_shipping_prices_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_shipping_prices_update_price',
                    country_code: countryCode,
                    state_code: stateCode,
                    new_price: newPrice,
                    nonce: wc_shipping_prices_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $priceCell.text(newPrice);
                        $button.text('Editar').removeClass('save-button');
                    } else {
                        alert('Error al guardar el nuevo precio: ' + response.data);
                    }
                },
                error: function() {
                    alert('Ocurrió un error al procesar la petición.');
                }
            });
        }
    });
});
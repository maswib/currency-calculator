jQuery(document).ready(function($) {
    "use strict";
    
    $(document).on('click', '#currency-calculator-calculate-button', function(){
        var t = $(this);
        var original_button = t.text();
        
        var data = { 
            action : 'currency_calculate',
            nonce  : Currency_Calculator.nonce,
            amount : $('#currency-calculator-amount').val(),
            from   : $('#currency-calculator-from').val(),
            to     : $('#currency-calculator-to').val()
        };
        
        t.text(Currency_Calculator.calculating);
        t.attr('disabled', 'disabled');
        
        $.post(Currency_Calculator.ajaxurl, data, function(res){
            if (res.success) {
                $('#currency-calculator-result').html(res.data.result);
            } else {
                console.log(res);
            }
            
            t.text(original_button);
            t.removeAttr('disabled');
        }).fail(function(xhr, textStatus, e) {
            console.log(xhr.responseText);
        });
        
        return false;
    });
    
});
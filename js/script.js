var entityMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '/': '&#x2F;',
    '`': '&#x60;',
    '=': '&#x3D;'
};

function escapeHtml (string) {
    return String(string).replace(/[&<>"'`=\/]/g, function (s) {
        return entityMap[s];
    });
}

jQuery('#tc_submit').click(function(){
    try{
        let prName = escapeHtml(jQuery('#tc_prName').val());
        let netto = escapeHtml(jQuery('#tc_netto').val());
        let vat = escapeHtml(jQuery('#tc_vat').val());
        let curr = escapeHtml(jQuery('#tc_currency').val());
        let vcalc = 0;

        if(prName == "") throw("Pole <strong>Nazwa produktu</strong> jest puste!");
        if((netto < 0) || (isNaN(netto))) throw("Nieprawidłowa kwota netto!");
        switch(vat){
            case('23'): vcalc = 23; break;
            case('22'): vcalc = 22; break;
            case('8'): vcalc = 8; break;
            case('7'): vcalc = 7; break;
            case('5'): vcalc = 5; break;
            case('3'): vcalc = 3; break;
            case('0'): vcalc = 0; break;
            case('zw'): vcalc = 0; break;
            case('np'): vcalc = 0; break;
            case('oo'): vcalc = 0; break;
            default: throw("Nieprawidłowa stawka VAT!"); break;
        }

        let cp = (netto*(vcalc/100+1)).toFixed(2);
        let kp = (netto*(vcalc/100)).toFixed(2);

        jQuery.ajax({
            type: "POST",
            dataType: "html",
            url: ajax_object.ajax_url,
            data: {
                'action': 'add_to_log',
                'prname': prName,
                'netto': netto,
                'vcalc': vcalc,
                'vat': vat,
                'cp': cp,
                'kp': kp,
                'curr': curr,
            },
            success: function(dx){
                console.log(dx);
                if(dx == '1')  jQuery('#tc_result').fadeIn().html("Cena produktu "+prName+", wynosi "+cp+" zł brutto, kwota podatku to "+kp+" zł.");
                else  jQuery('#tc_result').fadeIn().html("Wystąpił błąd, spróbuj ponownie!");

            },
            error : function(jqXHR, textStatus, errorThrown) {
                jQuery('#tc_result').fadeIn().html("Wystąpił błąd: "+errorThrown);
            }
        });
    }
    catch(e){
        jQuery('#tc_result').fadeIn();
        jQuery('#tc_result').html("Formularz zawiera błędy: " + e);
    }

})
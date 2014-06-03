function mascara(o, f) {
    v_obj = o
    v_fun = f
    setTimeout("execmascara()", 1)
}
function execmascara() {
    v_obj.value = v_fun(v_obj.value)
}
function mdocumento(v) {
    v = v.replace(/\D/g, "");
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    } else {
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
    }
    return v;
}
function mdata(v) {
    v = v.replace(/\D/g, "")
    v = v.replace(/(\d{2})(\d)/, "$1/$2")
    v = v.replace(/(\d{2})(\d)/, "$1/$2")
    return v
}
function mtel(v) {
    v = v.replace(/\D/g, "");
    v = v.replace(/^(\d{2})(\d)/g, "($1) $2");
    v = v.replace(/(\d)(\d{4})$/, "$1-$2");
    return v;
}
function sonumeros(v) {
    v = v.replace(/\D/g, "");
    return v;
}
function checkMail(mail) {
    var er = new RegExp(/^[A-Za-z0-9_\-\.]+@[A-Za-z0-9_\-\.]{2,}\.[A-Za-z0-9]{2,}(\.[A-Za-z0-9])?/);
    if (typeof (mail) == "string") {
        if (er.test(mail)) {
            return true;
        }
    } else if (typeof (mail) == "object") {
        if (er.test(mail.value)) {
            return true;
        }
    } else {
        return false;
    }
}
function formatoCartao(num) {

    var cards = [
        {
            type: 'maestro',
            pattern: /^(5018|5020|5038|6304|6759|676[1-3])/,
            length: [12, 13, 14, 15, 16, 17, 18, 19],
            cvcLength: [3],
            luhn: true
        },{
            type: "HI",
            pattern: /^606282|3841/,
            length: [16],
            cvcLength: 3,
            luhn: true
        }, {
            type: 'DN', //DINERS
            pattern: /^3(?:0[0-5]|[68][0-9])/,
            length: [14,16],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'laser',
            pattern: /^(6304|6706|6771|6709)/,
            length: [16, 17, 18, 19],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'jcb',
            pattern: /^35/,
            length: [16],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'unionpay',
            pattern: /^62/,
            length: [16, 17, 18, 19],
            luhn: false
        }, {
            type: 'discover',
            pattern: /^(6011|65|64[4-9]|622)/,
            length: [16],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'MC', //MASTERCARD
            pattern: /^5[1-5]/,
            length: [16],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'AE', //AMEX
            pattern: /^3[47]/,
            length: [15],
            cvcLength: [3, 4],
            luhn: true
        }, {
            type: 'VI', //VISA
            pattern: /^4/,
            length: [13, 14, 15, 16],
            cvcLength: [3],
            luhn: true
        }, {
            type: 'EL', //ELO
            pattern: /^(431274|636297|6363|5067((17)|(18)|(33)|(39)|(41)|(42)))/,
            length: [16],
            cvcLength: [3],
            luhn: true
        }
    ];
    var card, _i, _len;
    num = (num + '').replace(/\D/g, '');
    for (_i = 0, _len = cards.length; _i < _len; _i++) {
        card = cards[_i];
        if (card.pattern.test(num)) {
            return card.type ;
        }
    }
}
function valor(v) {
    v = v.replace(/\D/g, "");
    v = v.replace(/[0-9]{15}/, "inválido");
    v = v.replace(/(\d{1})(\d{11})$/, "$1.$2");
    v = v.replace(/(\d{1})(\d{8})$/, "$1.$2");
    v = v.replace(/(\d{1})(\d{5})$/, "$1.$2");
    v = v.replace(/(\d{1})(\d{1,2})$/, "$1,$2");
    return v;
}
function PulaCampo(fields) {
    if (fields.value.length == fields.maxLength) {
        for (var i = 0; i < fields.form.length; i++) {
            if (fields.form[i] == fields && fields.form[(i + 1)] && fields.form[(i + 1)].type != "hidden") {
                fields.form[(i + 1)].focus();
                break;
            }
        }
    }
}
function validaCPF(cpf, pType) {
    var cpf_filtrado = "", valor_1 = " ", valor_2 = " ", ch = "";
    var valido = false;
    for (i = 0; i < cpf.length; i++) {
        ch = cpf.substring(i, i + 1);
        if (ch >= "0" && ch <= "9") {
            cpf_filtrado = cpf_filtrado.toString() + ch.toString()
            valor_1 = valor_2;
            valor_2 = ch;
        }
        if ((valor_1 != " ") && (!valido))
            valido = !(valor_1 == valor_2);
    }
    if (!valido)
        cpf_filtrado = "12345678912";
    if (cpf_filtrado.length < 11) {
        for (i = 1; i <= (11 - cpf_filtrado.length); i++) {
            cpf_filtrado = "0" + cpf_filtrado;
        }
    }
    if (pType <= 1) {
        if ((cpf_filtrado.substring(9, 11) == checkCPF(cpf_filtrado.substring(0, 9))) && (cpf_filtrado.substring(11, 12) == "")) {
            return true;
        }
    }
    if ((pType == 2) || (pType == 0)) {
        if (cpf_filtrado.length >= 14) {
            if (cpf_filtrado.substring(12, 14) == checkCNPJ(cpf_filtrado.substring(0, 12))) {
                return true;
            }
        }
    }
    return false;
}
function checkCNPJ(vCNPJ) {
    var mControle = "";
    var aTabCNPJ = new Array(5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2);
    for (i = 1; i <= 2; i++) {
        mSoma = 0;
        for (j = 0; j < vCNPJ.length; j++)
            mSoma = mSoma + (vCNPJ.substring(j, j + 1) * aTabCNPJ[j]);
        if (i == 2)
            mSoma = mSoma + (2 * mDigito);
        mDigito = (mSoma * 10) % 11;
        if (mDigito == 10)
            mDigito = 0;
        mControle1 = mControle;
        mControle = mDigito;
        aTabCNPJ = new Array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3);
    }
    return((mControle1 * 10) + mControle);
}
function checkCPF(vCPF) {
    var mControle = ""
    var mContIni = 2, mContFim = 10, mDigito = 0;
    for (j = 1; j <= 2; j++) {
        mSoma = 0;
        for (i = mContIni; i <= mContFim; i++)
            mSoma = mSoma + (vCPF.substring((i - j - 1), (i - j)) * (mContFim + 1 + j - i));
        if (j == 2)
            mSoma = mSoma + (2 * mDigito);
        mDigito = (mSoma * 10) % 11;
        if (mDigito == 10)
            mDigito = 0;
        mControle1 = mControle;
        mControle = mDigito;
        mContIni = 3;
        mContFim = 11;
    }

    return((mControle1 * 10) + mControle);
}
function MontaParcelamento(campo, parcela, valor) {
    var valorparcelas = 0;
    var valorparcelas = [];
    var parcelaI = 0;

    jQuery(campo + ' option').remove();

    for (var i = 1; i <= parcela; i++) {
        valorparcelas.push(valor / i);
    }
    jQuery.each(valorparcelas.reverse(), function(val, text) {
        var parcelaI = parcela - val;
        jQuery(campo).append(new Option(parcelaI + "x sem juros (R$ " + text.toFixed(2).replace('.',',') + ")", parcelaI));
    });
}
function formatNumber(nStr,dec,mil) {
    nStr += '';
    x = nStr.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? ''+mil+'' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ',' + '$2');
    }
    return x1 + x2;
}
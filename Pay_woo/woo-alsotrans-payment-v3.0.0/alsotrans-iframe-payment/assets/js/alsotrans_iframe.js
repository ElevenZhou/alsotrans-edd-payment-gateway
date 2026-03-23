jQuery(function($){
    checkout_form = $( 'form.woocommerce-checkout' );
    order_form = $( 'form#order_review' );
    checkout_form.on('click','#place_order', generateATPCardToken);


});

function getSelectedPaymentMethod() {
    const selectedRadio = document.querySelector('input[name="payment_method"]:checked');
    return selectedRadio ? selectedRadio.value : null;
}

var generateATPCardToken = function(e) {
    if(getSelectedPaymentMethod() !== 'alsotrans_iframe'){
        return;
    }
    e.preventDefault()
    let cardToken = checkout_form.find('#alsotrans_token').val();
    if (!cardToken) {
        try {
            getATPCardToken().then(res=>{
                let token = checkout_form.find('#alsotrans_token')
                if(res){
                    token.val(res)
                    successCallback()
                }else{
                    return false;
                }
            })
        } catch (error) {
            console.error("Get Token Error:", error);
        }
    }else{
        successCallback()
    }
};

async function getATPCardToken() {
    try {
        const validateResult = await ATP.getValidateResult();
        if (validateResult) {
            try {
                let { token } = await ATP.confirmPay();
                return token;
            } catch (confirmError) {
                console.error('Payment confirmation failed:', confirmError);
            }
        }
    } catch (validateError) {
        console.error('Validation failed:', validateError);
    }
}

var successCallback = function(data) {
    checkout_form.submit();
};
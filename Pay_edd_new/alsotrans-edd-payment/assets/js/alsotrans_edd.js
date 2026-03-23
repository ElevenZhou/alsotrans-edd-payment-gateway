jQuery(document).ready(function($) {
    console.log('Alsotrans EDD JavaScript loaded and initialized');
    if (typeof alsotransEddParams === 'undefined') {
        console.error('alsotransEddParams is undefined - plugin not properly configured');
        return;
    }

    console.log('alsotransEddParams:', alsotransEddParams);

    var gatewayId = alsotransEddParams.gatewayId;
    var iframeToken = alsotransEddParams.iframeToken;
    var jsBaseUrl = alsotransEddParams.jsBaseUrl;

    console.log('Gateway ID:', gatewayId);
    console.log('Iframe Token:', iframeToken);
    console.log('JS Base URL:', jsBaseUrl);

    // Clear any existing token on page load to prevent reuse
    $('input[name="alsotrans_token"]').val('');
    console.log('Cleared existing alsotrans_token on page load');

    // Check form elements
    console.log('Checking form elements...');
    console.log('Form with class edd_form:', $('form.edd_form').length);
    console.log('Form with id edd-purchase-form:', $('#edd-purchase-form').length);
    console.log('Alsotrans token field:', $('input[name="alsotrans_token"]').length);
    console.log('Alsotrans token field value:', $('input[name="alsotrans_token"]').val());

    // Check if ATP library is loaded
    setTimeout(function() {
        console.log('ATP library status after 1 second:', typeof ATP);
        if (typeof ATP !== 'undefined') {
            console.log('ATP object keys:', Object.keys(ATP));
        }

        // Check iframe element
        var iframeElement = document.getElementById('llpay-card-element');
        console.log('Iframe element exists:', !!iframeElement);
        if (iframeElement) {
            console.log('Iframe element children:', iframeElement.children.length);
            console.log('Iframe element innerHTML:', iframeElement.innerHTML);
        }
    }, 1000);

    function getSelectedPaymentMethod() {
        var selected = document.querySelector('input[name="payment-mode"]:checked');
        if (selected && selected.value) {
            return selected.value;
        }

        selected = document.querySelector('input[name="edd-gateway"]:checked');
        if (selected && selected.value) {
            return selected.value;
        }

        selected = document.querySelector('input[name="gateway"]:checked');
        if (selected && selected.value) {
            return selected.value;
        }

        selected = document.querySelector('input[name="edd-gateway"]');
        if (selected && selected.value) {
            return selected.value;
        }

        selected = document.querySelector('input[name="payment-mode"]');
        if (selected && selected.value) {
            return selected.value;
        }

        selected = document.querySelector('input[name="gateway"]');
        return (selected && selected.value) ? selected.value : null;
    }

    function isAlsotransGateway() {
        var method = getSelectedPaymentMethod();
        console.log('Selected payment method for Alsotrans check:', method);

        if (!method) {
            // fallback: check for hidden fields commonly set by EDD when only one gateway is available
            var fallbackValue = $('input[name="edd-gateway"]').val() || $('input[name="gateway"]').val() || $('input[name="payment-mode"]').val();
            if (fallbackValue) {
                console.log('Fallback gateway value detected:', fallbackValue);
                method = fallbackValue;
            }
        }

        // If still no method and only alsotrans fields exist, assume this gateway
        if (!method) {
            var maybeAlsotrans = $('form.edd_form').length && $('input[name="alsotrans_token"]').length;
            if (maybeAlsotrans) {
                console.log('No explicit payment method found, but Alsotrans fields exist, defaulting to Alsotrans gateway');
                method = gatewayId;
            }
        }

        return method === gatewayId;
    }

    // 单次 token 策略：页面加载后只生成一个 token，避免重复调用并且防止嵌套 submit 触发重复
    var alsotransTokenInFlight = false;
    var alsotransSubmitRedirect = false;

    function submitAlsotransOnce(e) {
        if (!isAlsotransGateway()) {
            return true;
        }

        var existingToken = $('input[name="alsotrans_token"]').val();
        if (existingToken) {
            console.log('Alsotrans token already exists:', existingToken, 'Proceeding to submit.');
            return true;
        }

        if (alsotransTokenInFlight) {
            console.log('Token generation already in progress, ignoring duplicate submit');
            e.preventDefault();
            return false;
        }

        e.preventDefault();
        alsotransTokenInFlight = true;
        console.log('Generating ATP token once on form submit');

        generateATPCardToken().then(function(token) {
            if (!token) {
                throw new Error('Token validation failed: no token returned');
            }

            $('input[name="alsotrans_token"]').val(token);
            console.log('Token saved into form, now re-submitting form');

            // 切换状态，防止这次触发被重复处理
            alsotransSubmitRedirect = true;
            $('form.edd_form, #edd-purchase-form, #edd_purchase_form').first().trigger('submit');
        }).catch(function(err) {
            console.error('ATP token generation error:', err);
            eddGatewayAlert('error', 'Payment initialization failed. Please try again. ' + (err.message || ''));
        }).finally(function() {
            alsotransTokenInFlight = false;
        });

        return false;
    }

    $(document).on('submit', 'form.edd_form, #edd-purchase-form, #edd_purchase_form', function(e) {
        if (alsotransSubmitRedirect) {
            alsotransSubmitRedirect = false;
            console.log('Received controller redirect submit; allowing final form send.');
            return true;
        }

        return submitAlsotransOnce(e);
    });

    $(document).on('edd_gateway_purchase_form_submit', function(e) {
        return submitAlsotransOnce(e);
    });

    function generateATPCardToken() {
        console.log('generateATPCardToken called');
        return new Promise(function(resolve, reject) {
            if (typeof ATP === 'undefined') {
                console.error('ATP library not loaded');
                reject(new Error('ATP library not loaded'));
                return;
            }

            console.log('Starting ATP token generation...');

            ATP.getValidateResult().then(function(validateResult) {
                console.log('ATP validation result:', validateResult);
                console.log('Validation result type:', typeof validateResult);
                if (validateResult) {
                    console.log('Validation passed, calling confirmPay...');
                    ATP.confirmPay().then(function(result) {
                        console.log('ATP confirm pay result:', result);
                        console.log('Result type:', typeof result);
                        if (result) {
                            console.log('Result keys:', Object.keys(result));
                            console.log('Full result:', JSON.stringify(result));
                        }

                        if (result && result.token) {
                            console.log('Token obtained successfully:', result.token);
                            resolve(result.token);
                        } else {
                            console.error('No token in result, full result:', JSON.stringify(result));
                            reject(new Error('No token returned from confirmPay'));
                        }
                    }).catch(function(confirmError) {
                        console.error('Payment confirmation failed:', confirmError);
                        console.error('Confirm error type:', typeof confirmError);
                        console.error('Confirm error message:', confirmError.message || confirmError);
                        reject(confirmError);
                    });
                } else {
                    console.error('ATP validation failed - form fields may be incomplete');
                    reject(new Error('ATP validation failed - form fields may be incomplete'));
                }
            }).catch(function(validateError) {
                console.error('ATP validation error:', validateError);
                console.error('Validate error type:', typeof validateError);
                console.error('Validate error message:', validateError.message || validateError);
                reject(validateError);
            });
        });
    }

    function submitAlsotransForm(form) {
        if (!form || !form.length) {
            console.error('submitAlsotransForm: form not found');
            return;
        }

        generateATPCardToken().then(function(token) {
            console.log('submitAlsotransForm: token generated', token);
            if (token) {
                $('input[name="alsotrans_token"]').val(token);
                setTimeout(function() {
                    form.off('submit.alsotransLegacy');
                    form.submit();
                }, 20);
            } else {
                console.error('submitAlsotransForm: no token received');
                eddGatewayAlert('error', 'Unable to generate payment token. Please retry.');
            }
        }).catch(function(error) {
            console.error('submitAlsotransForm: token generation error', error);
            var errorMessage = 'Unable to generate payment token. ';
            if (error.message && error.message.includes('validation failed')) {
                errorMessage += 'Please ensure all card fields are filled correctly.';
            } else if (error.message) {
                errorMessage += error.message;
            } else {
                errorMessage += 'Please try again.';
            }
            eddGatewayAlert('error', errorMessage);
        });
    }

    var alsotransSubmitSelector = 'form.edd_form input[type="submit"], form.edd_form button[type="submit"], #edd_purchase_form input[type="submit"], #edd_purchase_form button[type="submit"]';

    $(document).on('click', alsotransSubmitSelector, function(e) {
        var form = $(this).closest('form');
        if (!isAlsotransGateway()) {
            console.log('Not Alsotrans gateway, proceeding normally');
            return true;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        console.log('Submit button click intercepted for Alsotrans, generating token');

        // Check if iframe has content (card fields filled)
        var iframeElement = document.getElementById('llpay-card-element');
        if (iframeElement && iframeElement.children.length === 0) {
            console.warn('Iframe element appears empty, card fields may not be loaded');
        }

        submitAlsotransForm(form);
        return false;
    });

    function eddGatewayAlert(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: type === 'error' ? 'Error' : 'Notice',
                text: message,
                icon: type,
                confirmButtonText: 'OK'
            });
        } else {
            alert(message);
        }
    }

    if (typeof getBrowserInfoSid === 'function') {
        getBrowserInfoSid().then(function(res) {
            if (res && res.sid) {
                $('input[name="alsotrans_sid"]').val(res.sid);
            }
        }).catch(function(err) {
            console.log('Browser info error:', err);
        });
    }
});

// V2.1.1

// IO_BB for Fraud Detection
var io_install_flash = false;
var io_bbout_element_id = 'fatzebra_io_bb';
var io_enable_rip = true;
var io_install_stm = false;
var io_exclude_stm = 12;
var originalPaymentSave;

Event.observe(window, "load", function () {
    document.observe('payment-method:switched', function (e) {
        var togglePaymentFields = function() {
            if ($$('[name=use_saved_card]:checked').length > 0 && $$('[name=use_saved_card]:checked')[0].value == 1) {
                // Disable/fade-out form
                 $('payment_form_fatzebra_entry').style.opacity = 0.5;
                $$('#payment_form_fatzebra_entry input').each(function(item) {
                    item.disable();
                    item.removeClassName('required-entry');
                });
                $$('#payment_form_fatzebra_entry select').each(function(item) {
                    item.disable();
                    item.removeClassName('required-entry');
                });

                $('fatzebra_cc_cid').removeClassName('validate-cc-cvn');
            } else {
                $('payment_form_fatzebra_entry').style.opacity = 1.0;
                $$('#payment_form_fatzebra_entry input').each(function(item) {
                    item.enable();
                    if (item.id !== "fatzebra_cc_save")
                        item.addClassName('required-entry');
                });
                $$('#payment_form_fatzebra_entry select').each(function(item) {
                    item.enable();
                    item.addClassName('required-entry');
                });

                $('fatzebra_cc_cid').addClassName('validate-cc-cvn');
            }
        };
        $$('[name=use_saved_card]').invoke('observe', 'click', togglePaymentFields);
        togglePaymentFields();
        if ($('fatzebra_io_bb')) {
            if (e.memo.method_code == 'fatzebra') {
                // Load io_bb if not setup here...
                var s = document.createElement('script');
                s.src = 'https://mpsnare.iesnare.com/snare.js';
                s.id = 'mpsnare';
                document.getElementsByTagName('head')[0].appendChild(s);
            }

            $('fatzebra_io_bb').disabled = false;
            $('fatzebra_cc_type').disabled = false;
        }

        if ($('fz_directpost_enabled') && e.memo.method_code == 'fatzebra') {
            // Remove the 'name=' attr from the inputs so they aren't sent back to the server...
            $('fatzebra_cc_owner').removeAttribute('name');
            $('fatzebra_cc_number').removeAttribute('name');
            $('fatzebra_cc_cid').removeAttribute('name');

            // Hook the submit method and direct data to FZ, then update the hidden fields and submit to Magento...

            if(typeof originalPaymentSave == 'undefined') {
                originalPaymentSave = window.payment.save;
            }

            window.payment.save = function () {
                if ($('p_method_fatzebra').checked) {  // FIXED: call these "hook" code only when FatZebra payment method is checked.
                    // Embed these in the form
                    if ($$('[name=use_saved_card]:checked').length > 0 && $$('[name=use_saved_card]:checked')[0].value == 1) {
                        originalPaymentSave.apply(window.payment);
                        return;
                    }
                    var gwUrl = $('fz_directpost_url').value;
                    var nonce = $('fz_directpost_nonce').value;
                    var verification = $('fz_directpost_verification').value;
                    var v = function (name) {
                        return $('fatzebra_' + name).value;
                    };

                    var req = new Ajax.JSONRequest(gwUrl, {
                        parameters: {
                            format: 'json',
                            card_holder: v('cc_owner'),
                            card_number: v('cc_number'),
                            expiry_month: v('expiration'),
                            expiry_year: v('expiration_yr'),
                            cvv: v('cc_cid'),
                            return_path: nonce,
                            verification: verification
                        },
                        onSuccess: function (response) {
                            if (response.responseJSON.r == 1) {
                                var form = $('co-payment-form');
                                form.insert(new Element('input', {
                                    type: 'hidden',
                                    name: 'payment[cc_number]',
                                    value: response.responseJSON.card_number
                                }));
                                form.insert(new Element('input', {
                                    type: 'hidden',
                                    name: 'payment[cc_owner]',
                                    value: response.responseJSON.card_holder
                                }));
                                form.insert(new Element('input', {
                                    type: 'hidden',
                                    name: 'payment[cc_token]',
                                    value: response.responseJSON.token
                                }));
                                $('fatzebra_cc_cid').setAttribute('name', 'payment[cc_cid]');
                                originalPaymentSave.apply(window.payment);
                            } else if (response.responseJSON.r == 97) {
                                alert("Credit Card Validation Error - please check your card number and try again.");
                            } else {
                                alert("Sorry there has been an error attempting to validation your credit card details. Please try again.\n\nIf this error persists please contact the store owner.\n\nError Code: " + response.responseJSON.r);
                            }
                        },
                        onFailure: function (response) {
                            alert("Sorry there has been an error attempting to validation your credit card details. Please try again.\n\nIf this error persists please contact the store owner.");
                        }
                    })
                } else {
                    originalPaymentSave.apply(window.payment);
                }
            }

            if (window.checkout && window.checkout.LightcheckoutSubmit) {
                window.checkout.LightcheckoutSubmit = (function () {
                    originalLightcheckoutSubmit = window.checkout.LightcheckoutSubmit;
                    return function() {
                        // Embed these in the form

                        if($$('[name=use_saved_card]:checked').length > 0 && $$('[name=use_saved_card]:checked')[0].value == 1){
                                    originalLightcheckoutSubmit.apply(window.checkout);
                                    return;
                        }
                        var gwUrl = $('fz_directpost_url').value;
                        var nonce = $('fz_directpost_nonce').value;
                        var verification= $('fz_directpost_verification').value;
                        var v = function(name) { return $('fatzebra_' + name).value; };

                        var req = new Ajax.JSONRequest(gwUrl, {
                            parameters: {
                                format: 'json',
                                card_holder: v('cc_owner'),
                                card_number: v('cc_number'),
                                expiry_month: v('expiration'),
                                expiry_year: v('expiration_yr'),
                                cvv: v('cc_cid'),
                                return_path: nonce,
                                verification: verification
                            },
                            onSuccess: function (response) {
                                if (response.responseJSON.r == 1) {
                                    var form = $('payment_form_fatzebra');
                                    form.insert(new Element('input', {type: 'hidden', name: 'payment[cc_number]', value: response.responseJSON.card_number}));
                                    form.insert(new Element('input', {type: 'hidden', name: 'payment[cc_owner]', value: response.responseJSON.card_holder}));
                                    form.insert(new Element('input', {type: 'hidden', name: 'payment[cc_token]', value: response.responseJSON.token}));
                                    $('fatzebra_cc_cid').setAttribute('name', 'payment[cc_cid]');
                                    originalLightcheckoutSubmit.apply(window.checkout);
                                } else if (response.responseJSON.r == 97) {
                                    alert("Credit Card Validation Error - please check your card number and try again.");
                                } else {
                                    alert("Sorry there has been an error attempting to validation your credit card details. Please try again.\n\nIf this error persists please contact the store owner.\n\nError Code: " + response.responseJSON.r);
                                }
                            },
                            onFailure: function (response) {
                                alert("Sorry there has been an error attempting to validation your credit card details. Please try again.\n\nIf this error persists please contact the store owner.");
                            }
                        })
                    }
                })();
            }
        }
    });
    document.observe('keyup', function (e, el) {
        if (el = e.findElement("#fatzebra_cc_number")) {
            var value = $("fatzebra_cc_number").value;
            if (value.length === 0) return;

            var card_id, code;
            if (value.match(/^4/)) {
                card_id = "card-vi";
                code = "VI";
            }
            if (value.match(/^5/)) {
                card_id = "card-mc";
                code = "MC";
            }
            if (value.match(/^(34|37)/)) {
                card_id = "card-ae";
                code = "AE";
            }
            if (value.match(/^(36)/)) {
                card_id = "card-dic";
                code = "DIC";
            }
            if (value.match(/^(35)/)) {
                card_id = "card-jcb";
                code = "JCB";
            }
            if (value.match(/^(65)/)) {
                card_id = "card-di";
                code = "DI";
            }

            $$("img.card-logo").each(function (x) {
                if (x.id != card_id) {
                    $(x).setStyle({opacity: 0.5});
                } else {
                    $(x).setStyle({opacity: 1.0});
                }
            });

            $("fatzebra_cc_type").value = code;
        }
    });
});


/* JSON-P implementation for Prototype.js somewhat by Dan Dean (http://www.dandean.com)
 *
 * *HEAVILY* based on Tobie Langel's version: http://gist.github.com/145466.
 * Might as well just call this an iteration.
 *
 * This version introduces:
 * - Support for predefined callbacks (Necessary for OAuth signed requests, by @rboyce)
 * - Partial integration with Ajax.Responders (Thanks to @sr3d for the kick in this direction)
 * - Compatibility with Prototype 1.7 (Thanks to @soung3 for the bug report)
 * - Will not break if page lacks a <head> element
 *
 * See examples in README for usage
 *
 * VERSION 1.1.2
 *
 * new Ajax.JSONRequest(url, options);
 * - url (String): JSON-P endpoint url.
 * - options (Object): Configuration options for the request.
 */
Ajax.JSONRequest = Class.create(Ajax.Base, (function () {
    var id = 0, head = document.getElementsByTagName('head')[0] || document.body;
    return {
        initialize: function ($super, url, options) {
            $super(options);
            this.options.url = url;
            this.options.callbackParamName = this.options.callbackParamName || 'callback';
            this.options.timeout = this.options.timeout || 10; // Default timeout: 10 seconds
            this.options.invokeImmediately = (!Object.isUndefined(this.options.invokeImmediately)) ? this.options.invokeImmediately : true;

            if (!Object.isUndefined(this.options.parameters) && Object.isString(this.options.parameters)) {
                this.options.parameters = this.options.parameters.toQueryParams();
            }

            if (this.options.invokeImmediately) {
                this.request();
            }
        },

        /**
         *  Ajax.JSONRequest#_cleanup() -> undefined
         *  Cleans up after the request
         **/
        _cleanup: function () {
            if (this.timeout) {
                clearTimeout(this.timeout);
                this.timeout = null;
            }
            if (this.transport && Object.isElement(this.transport)) {
                this.transport.remove();
                this.transport = null;
            }
        },

        /**
         *  Ajax.JSONRequest#request() -> undefined
         *  Invokes the JSON-P request lifecycle
         **/
        request: function () {

            // Define local vars
            var response = new Ajax.JSONResponse(this);
            var key = this.options.callbackParamName,
                name = '_prototypeJSONPCallback_' + (id++),
                complete = function () {
                    if (Object.isFunction(this.options.onComplete)) {
                        this.options.onComplete.call(this, response);
                    }
                    Ajax.Responders.dispatch('onComplete', this, response);
                }.bind(this);

            // If the callback parameter is already defined, use that
            if (this.options.parameters[key] !== undefined) {
                name = this.options.parameters[key];
            }
            // Otherwise, add callback as a parameter
            else {
                this.options.parameters[key] = name;
            }

            // Build request URL
            this.options.parameters[key] = name;
            var url = this.options.url + ((this.options.url.include('?') ? '&' : '?') + Object.toQueryString(this.options.parameters));

            // Define callback function
            window[name] = function (json) {
                this._cleanup(); // Garbage collection
                window[name] = undefined;

                response.status = 200;
                response.statusText = "OK";
                response.setResponseContent(json);

                if (Object.isFunction(this.options.onSuccess)) {
                    this.options.onSuccess.call(this, response);
                }
                Ajax.Responders.dispatch('onSuccess', this, response);

                complete();

            }.bind(this);

            this.transport = new Element('script', { type: 'text/javascript', src: url });

            if (Object.isFunction(this.options.onCreate)) {
                this.options.onCreate.call(this, response);
            }
            Ajax.Responders.dispatch('onCreate', this);

            head.appendChild(this.transport);

            this.timeout = setTimeout(function () {
                this._cleanup();
                window[name] = Prototype.emptyFunction;
                if (Object.isFunction(this.options.onFailure)) {
                    response.status = 504;
                    response.statusText = "Gateway Timeout";
                    this.options.onFailure.call(this, response);
                }
                complete();
            }.bind(this), this.options.timeout * 1000);
        },
        toString: function () {
            return "[object Ajax.JSONRequest]";
        }
    };
})());

Ajax.JSONResponse = Class.create({
    initialize: function (request) {
        this.request = request;
    },
    request: undefined,
    status: 0,
    statusText: '',
    responseJSON: undefined,
    responseText: undefined,
    setResponseContent: function (json) {
        this.responseJSON = json;
        this.responseText = Object.toJSON(json);
    },
    getTransport: function () {
        if (this.request) return this.request.transport;
    },
    toString: function () {
        return "[object Ajax.JSONResponse]";
    }
});
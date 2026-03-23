<?php

class CallbackData {
    public $id;
    public $order_id;
    public $status;
    public $currency;
    public $amount_value;
    public $metadata;
    public $request_id;
    public $fail_code;
    public $fail_message;
    public $pay_method;
    public $version;
    public $sign_verify;

    public function __construct($data) {
        if (!is_array($data)) {
            $data = json_decode($data, true);
            if (!is_array($data)) {
                throw new Exception('data must be array!');
            }
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

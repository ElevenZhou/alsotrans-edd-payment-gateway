<?php
class IframeResponse
{
    protected $code;
    protected $describe;
    protected $token;
    protected $success;

    public function __construct($data)
    {
        if(!is_array($data)){
            $data = json_decode($data,true);
            if(!is_array($data)){
                throw new Exception('Response data must be array!');
            }
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @return mixed|string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return mixed|string
     */
    public function getDescribe()
    {
        return $this->describe;
    }

    /**
     * @return mixed|string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return mixed|string
     */
    public function getSuccess()
    {
        return $this->success;
    }
}
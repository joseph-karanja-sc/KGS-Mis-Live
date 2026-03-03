<?php


namespace App\Helpers;


use NoProtocol\Encryption\MySQL\AES\Crypter;

class SecurityHelper
{

    static function mysql_aes_key($key)
    {
        $new_key = str_repeat(chr(0), 16);
        for ($i = 0, $len = strlen($key); $i < $len; $i++) {
            $new_key [$i % 16] = $new_key [$i % 16] ^ $key [$i];
        }
        return $new_key;
    }

    static function aes_encrypt($val)
    {
        $key = self::getEncryptionKey();
        $crypter = new Crypter($key);
        $encrypted = $crypter->encrypt($val);
        return base64_encode($encrypted);
    }

    static function aes_decrypt($val)
    {
        $original_val = $val;
        if (self::is_base64_encoded($val) == true || self::is_base64_encoded($val) == 'true' || self::is_base64_encoded($val) == 1 || self::is_base64_encoded($val) == '1') {
            $val = base64_decode($val);
            $key = self::getEncryptionKey();
            $crypter = new Crypter($key);
            $decrypted = $crypter->decrypt($val);
            if ($decrypted == '') {
                return $original_val;
            }
            $valid_encoding = mb_check_encoding($decrypted, 'UTF-8');//check if malformed for the specified encoding
            if ($valid_encoding == false) {
                return $original_val;
            }
            return $decrypted;
        }
        return $original_val;
    }

    static function getEncryptionKey()
    {
        $key = Config('constants.encrypt_key');
        return $key;
    }

    static function encryptArray($array, $skip = array())
    {
        $return_array = array();
        foreach ($array as $key => $value) {
            if (in_array($key, $skip)) {
                $return_array[$key] = $value;
            } else {
                $return_array[$key] = aes_encrypt($value);
            }
        }
        return $return_array;
    }

    static function decryptArray($arrays)
    {
        $new_array = array();
        foreach ($arrays as $value) {
            foreach ($value as $nested_key => $nested_value) {
                if ($nested_value == '' || $nested_value == null || $nested_value == NULL || $nested_value == ' ' || $nested_value == " ") {
                    $value [$nested_key] = '';
                } else {
                    $value [$nested_key] = self::aes_decrypt($nested_value);
                }
            }
            $new_array [] = $value;
        }
        return $new_array;
    }

    static function decryptSimpleArray($array)
    {
        $new_array = array();
        foreach ($array as $key => $value) {
            $new_array[$key] = self::aes_decrypt($value);
        }
        return $new_array;
    }

    static function is_base64_encoded($data)
    {
        if (is_numeric($data)) {
            return false;
        }
        try {
            $decoded = base64_decode($data, true);
            if (base64_encode($decoded) === $data) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            // If exception is caught, then it is not a base64 encoded string
            return false;
        }
    }

}

<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class ValidationsHelper
{
    static function validateStringFormat($value)
    {
        $regex = '/^[0-9]{1,10}\-[a-zA-Z]{2,90}$/';
        if (preg_match($regex, $value)) {
            return true;
        }
        return 'Value is in a wrong format';
    }

    static function validateNum($value)
    {
        if (is_numeric($value) && $value > 0) {
            return true;
        }
        return 'Value not a valid number';
    }

    static function validateType($value, $type)
    {
        switch ($type) {
            case 1://number
                if (is_numeric($value) && $value > 0) {
                    return true;
                } else {
                    return 'Value not a valid number';
                }
                break;
            case 2://text
                if (preg_match("/^[a-zA-Z]+$/", $value)) {
                    return true;
                } else {
                    return 'Value not a valid text';
                }
                break;
            case 3://code name
                if (preg_match('/^[0-9]{1,10}-[a-zA-Z]|[-]$/', $value)) {
                    return true;
                } else {
                    // return 'Value has a wrong pattern. Should be in the format Code-Name';
            return true;
                }
                break;
            case 5://code code
                if (preg_match('/^[0-9]{1,10}-[0-9]{1,10}$/', $value)) {
                    return true;
                } else {
                    // return 'Value has a wrong pattern. Should be in the format Code-Code';
            return true;
                }
                break;
            case 6://date
                return self::validateDate($value);
                break;
            default:
                return true;
        }
    }

    static function validateIsMandatory($value)
    {
        if ($value == '' || trim($value) == ' ' || is_null(trim($value))) {
            return 'No value provided yet it is mandatory';
        }
        return true;
    }

    static function validateCodeNameHyphened($value)
    {
        if (preg_match('/^[0-9]{1,10}-[a-zA-Z]|[-]$/', $value)) {
        // if (preg_match("/^(?:[0-9]{1,10}-[a-zA-Z]|[0-9]{2}-[a-z]{2}|[A-Z0-9]+-[A-Z0-9 .,\''&()-]+)$/i", $value) {
            return true;
        } else {
            // return 'Value has a wrong pattern. Should be in the format Code-Name';
            return true;
        }
    }

    static function validateCodeCodeHyphened($value)
    {
        if (preg_match('/^[0-9]{1,10}-[0-9]{1,10}$/', $value)) {
            return true;
        } else {
            // return 'Value has a wrong pattern. Should be in the format Code-Code';
            return true;
        }
    }

    static function validateIsParameterized($table, $value, $flag)
    {
        $returnString = true;
        if ($flag == 1) {
            $code_exists = DB::table($table)->where('code', $value)->first();
            if (is_null($code_exists)) {
                $returnString = 'Value not existing in the specified param table';
            }
        } else {
            $valueArray = explode('-', $value);
            $code = $valueArray[0];
            $code_exists = DB::table($table)->where('code', $code)->first();
            if (is_null($code_exists)) {
                $returnString = 'Value not existing in the specified param table';
            }
        }
        return $returnString;
    }

    static function splitDate($value)
    {
        if (count(explode('/', $value)) > 1) {
            $dateArray = explode('/', $value);
        } else if (count(explode('.', $value)) > 1) {
            $dateArray = explode('.', $value);
        } else if (count(explode('-', $value)) > 1) {
            $dateArray = explode('-', $value);
        }
        return $dateArray;
    }

    static function validateDate($date)
    {
        /*$dateArray = self::splitDate($date);
        if (checkdate($dateArray[1], $dateArray[0], $dateArray[2])) {
            return true;
        } else {
            return 'Value not in the accepted date format. Date can be in any of these formats=>d/m/y, d.m.y or d-m-y';
        }*/
        return true;
    }

    static function validateDate2($value)
    {
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $value)) {//yyyy-mm-dd
            echo 'Date is valid';
        } else {
            echo 'Date is invalid';
        }
    }
    //hiram to validate is numeric
    static function validateisNumeric($value)
    {
    	if (is_numeric($value) && $value != 0) {
    		return true;
    	}
    	else{
    		return false;
    	}
    }
}
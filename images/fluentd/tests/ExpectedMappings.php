<?php declare(strict_types=1);

namespace KMOtrebski\Infratifacts\Images\Fluentd\Tests;

class ExpectedMappings
{
    public static function metrics() : array
    {
        $json = '{
        "intValue" : {
          "type" : "integer"
        },
        "floatValue" : {
          "type" : "float"
        },
        "key" : {
          "type" : "keyword"
        },
        "@time": {
          "type": "date",
          "format": "yyyy-MM-dd HH:mm:ss.SSSSSS"
        },
        "epoch_micros": {
          "type": "long"
        },
        "microservice" : {
          "type" : "keyword"
        },
        "process" : {
          "type" : "keyword"
        }
      }';

        return json_decode($json, true);
    }
}

<?php

$code = '//Begin Really Simple SSL Load balancing fix
            $server_opts = array("HTTP_CLOUDFRONT_FORWARDED_PROTO" => "https", "HTTP_CF_VISITOR"=>"https", "HTTP_X_FORWARDED_PROTO"=>"https", "HTTP_X_FORWARDED_SSL"=>"on", "HTTP_X_FORWARDED_SSL"=>"1");
            foreach( $server_opts as $option => $value ) {
                if ((isset($_ENV["HTTPS"]) && ( "on" == $_ENV["HTTPS"] )) || (isset( $_SERVER[ $option ] ) && ( strpos( $_SERVER[ $option ], $value ) !== false )) ) {
                    $_SERVER[ "HTTPS" ] = "on";
                }
            }
//END Really Simple SSL
';

$str = '';

if (preg_match('/all, stop editing! Happy publishing.*END Really Simple SSL/', $str, $matches)) {

}
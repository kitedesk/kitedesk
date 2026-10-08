<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | When KiteDesk runs behind a load balancer, CDN or reverse proxy, list its
    | addresses (comma separated, CIDR allowed) or "*" so client IPs, HTTPS
    | and rate limits are based on the real visitor. Read by TrustProxies.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];

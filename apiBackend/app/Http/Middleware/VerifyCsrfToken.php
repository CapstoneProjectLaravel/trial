<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
   // app/Http/Middleware/VerifyCsrfToken.php

protected $except = [
    'Verify',
    'http://192.168.100.6/api/Verify',
    'http://192.168.100.6/api/sendVerif'

];

}

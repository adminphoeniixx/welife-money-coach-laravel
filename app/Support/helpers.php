<?php

namespace App\Support;

use App\Http\Controllers\Concerns\StreamsPrivateFiles;
use Illuminate\Support\Facades\URL;

if (! function_exists('App\Support\signed_file_url')) {
    /**
     * A token-free, expiring link to one of the encrypted file endpoints.
     * Models call this so the app never has to attach an Authorization header
     * just to render a receipt photo.
     *
     * @param  array<string, mixed>  $parameters
     */
    function signed_file_url(string $route, array $parameters): string
    {
        return URL::temporarySignedRoute($route, StreamsPrivateFiles::signedLinkLifetime(), $parameters);
    }
}

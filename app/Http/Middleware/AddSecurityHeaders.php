<?php
// FILE: app/Http/Middleware/AddSecurityHeaders.php
// Register in app/Http/Kernel.php under $middlewareGroups['web']
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only add CSP to HTML responses (not video streams, JSON, etc.)
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            $response->headers->set(
                'Content-Security-Policy',
                "media-src 'self' https://www.youtube.com https://www.youtube-nocookie.com blob:; object-src 'none';"
            );
        }

        return $response;
    }
}
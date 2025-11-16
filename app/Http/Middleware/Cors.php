<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = [
            'http://localhost:5173',
            'http://localhost:8081',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:8081', 
            'http://127.0.0.1:8082', 
            'https://xmffi-fms.vercel.app',
        ];

        $origin = $request->headers->get('Origin');

        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
            if (in_array($origin, $allowedOrigins)) {
                $this->setCorsHeaders($response, $origin);
            }
            return $response;
        }

        $response = $next($request);

        if (in_array($origin, $allowedOrigins)) {
            $this->setCorsHeaders($response, $origin);
        }

        return $response;
    }

    private function setCorsHeaders($response, $origin)
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        return $response;
    }
}

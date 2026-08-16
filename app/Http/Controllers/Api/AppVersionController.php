<?php
namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller{

public function getVersion(): JsonResponse
{
    $version = (string) config('app.flutter_app_version', '');

    return response()
        ->json([
            'version' => $version,
        ], 200)
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
}
}

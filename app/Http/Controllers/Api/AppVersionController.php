<?php
namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AppVersionController extends Controller{

public function getVersion(){

$data = config('app.FLUTTER_APP_VERSION');

 return response()->json($data, 200);
}
}
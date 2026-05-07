<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;

class MeController extends BaseController
{
    public function __invoke(Request $request)
    {
        try {
            $user = $request->user()->load('userDetail');
            return $this->success($user);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
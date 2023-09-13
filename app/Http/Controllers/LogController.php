<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogController extends Controller
{
    public function index()
    {
        try {
            if (Auth::user()->role == 'super_admin') {

                return Log::orderBy('created_at', 'desc')->paginate(10);
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
}

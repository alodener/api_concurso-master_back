<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            if (Auth::user()->role == 'super_admin' || Auth::user()->role == 'admin') {
                return User::paginate(10);
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd(Auth::user()->role);
        try {
            if (Auth::user()->role == "super_admin" || Auth::user()->role == 'admin') {
                $data = $request->all();

                $user = new User();
                $user->email = $data['email'];
                $user->name = $data['name'];
                $user->password = Hash::make($data['password']);
                $user->role = $data['role'];
                $user->save();

                $log = new Log();
                $log->user_id = Auth::user()->id;
                $log->user_name = Auth::user()->name;
                $log->action = 'Usuário '.Auth::user()->name.' criou o novo usuário '.$user->name;
                $log->save();

                return $user;
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            if (Auth::user()->role == 'super_admin' || Auth::user()->role == 'admin') {
                return User::findOrFail($id);
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        try {
            $data = $request->all();
            if (Auth::user()->role == 'super_admin' || Auth::user()->role == 'admin') {
                $user = User::find($id);
                $has_email = false;

                if ($user->email != $data['email']) {
                    $has_email = User::where('email', $data['email'])->first();
                }

                if (!$has_email) {
                    $user->name = $data['name'];
                    $user->email = $data['email'];
                    $user->role = $data['role'];
                    if (!empty($data['password'])) {
                        $user->password = Hash::make($data['password']);
                    }
                    $user->save();
                    $log = new Log();
                    $log->user_id = Auth::user()->id;
                    $log->user_name = Auth::user()->name;
                    $log->action = 'Usuário '.Auth::user()->name.' editou o usuário '.$user->name;
                    $log->save();
                    return response('Usuário Editado com Sucesso', 200);
                }
                throw new Exception('Email já cadastrado');
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            if (Auth::user()->role == 'super_admin' || Auth::user()->role == 'admin') {
                $user = User::find($id);
                User::find($id)->delete();

                $log = new Log();
                $log->user_id = Auth::user()->id;
                $log->user_name = Auth::user()->name;
                $log->action = 'Usuário '.Auth::user()->name.' excluiu o usuário '.$user->name;
                $log->save();

                return response('', 200);
            }
            throw new Exception('Não Possui permissão');
        } catch (\Throwable $th) {
            throw new Exception($th);
        }
    }
}

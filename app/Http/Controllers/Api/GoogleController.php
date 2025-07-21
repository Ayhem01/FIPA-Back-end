<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class GoogleController extends Controller
{
    
        public function redirectToGoogle()
        {
            // Redirige l'utilisateur vers Google pour l'autorisation
            return Socialite::driver('google')->stateless()->redirect();
        }
    
        public function handleGoogleCallback()
{
    
        $user = Socialite::driver('google')->stateless()->user();
        $findUser = User::where('google_id', $user->id)->first();
        if (!is_null($findUser)) {
            Auth::login($findUser);
        }
        else {
            $findUser=User::create([
                'name' => $user->name,
                'email' => $user->email,
                'google_id' => $user->id,
                'password' => encrypt(Str::random(18))
                
            ]);
            Auth::login($findUser);
        }
        return redirect('/');
        }
}

<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Invite;
use Illuminate\Http\Request;

class ValidateInviteToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token');
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token manquant'
            ], 400);
        }
        
        $invite = Invite::where('token', $token)->first();
        
        if (!$invite) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou invitation déjà traitée'
            ], 404);
        }
        
        // Ajouter l'invite à la requête
        $request->merge(['invite' => $invite]);
        
        return $next($request);
    }
}
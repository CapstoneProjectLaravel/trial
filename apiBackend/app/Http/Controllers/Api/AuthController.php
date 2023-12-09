<?php

namespace App\Http\Controllers\Api;
use Twilio\Rest\Client;

use Illuminate\Http\Request;
use App\User;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function login(Request $request){
        $creds = $request->only(['email','password']);

        if(!$token=auth()->attempt($creds)){
            return response()->json([
                'success' => false,
                'message' => 'account not found'
            ]);
        }
        return response()->json([
            'success' =>true,
            'token' => $token,
            'user' => Auth::user()
        ]);
    }

    public function register(Request $request){

        $encryptedPass = Hash::make($request->password);

        $user = new User;

        try{
            $user->user_Type = $request->user_Type;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->address = $request->address;
            $user->contact = $request->contact;
            $user->password = $encryptedPass;
            $user->save();
            return $this->login($request);
        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => $e
            ]);
        }
    }
    public function logout(Request $request){
        try{
            JWTAuth::invalidate(JWTAuth::parseToken($request->token));
            return response()->json([
                'success' => true,
                'message' => 'logout success'
            ]);

        }
        catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => ''.$e
            ]);
        }
    }


    public function sendVerificationCode(Request $request)
    {
        // Generate a verification code (you can customize the code generation logic)
        $verificationCode = mt_rand(1000, 9999); // Generate a 4-digit verification code
    
        // User's phone number
        $phoneNumber = $request->input('phone_number');
    
        // Send the verification code via SMS using Twilio (or your preferred SMS gateway)
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $twilio = new Client($sid, $token);
    
        // Store the verification code in the session
        session()->put('verification_code', $verificationCode);
    
        $twilio->messages->create(
            $phoneNumber,
            [
                'from' => config('services.twilio.from'),
                'body' => 'Your verification code is: ' . $verificationCode,
            ]
        );
    
        // Response indicating the verification code has been sent
        return response()->json(['message' => 'Verification code sent successfully', 'verification_code' => $verificationCode]);
    }
    

    public function verifyVerificationCode(Request $request)
{
    // Get user-input verification code
    $userVerificationCode = $request->input('verification_code');

    // Get the verification code stored in the session
    $storedVerificationCode = session()->get('verification_code');

    // Compare user-input code with stored code
    if ($userVerificationCode == $storedVerificationCode) {
        // Verification successful
        // Clear the stored verification code from the session after successful verification
        session()->forget('verification_code');

        return response()->json(['message' => 'Verification successful']);
    } else {
        // Verification failed
        return response()->json(['error' => 'Invalid verification code'], 401);
    }
}

public function getCsrfToken()
{
    $csrfToken = csrf_token();
    return response()->json(['csrf_token' => $csrfToken]);
}

    
}

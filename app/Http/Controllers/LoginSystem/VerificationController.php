<?php

namespace App\Http\Controllers\LoginSystem;

use App\Models\User;
use Illuminate\Http\Request;
use App\Jobs\SendEmailVerifyJob;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use App\Jobs\SendOtpJob;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    public function verify(Request $request, $id){
        if(!$request->hasValidSignature()){
            return [
                'message' => 'Email verified fails'
            ];
        }
        $user = User::find($id);

        if(!$user->email_verified_at){
            $user->email_verified_at = now();
            $user->save();

            return view('VerifyEmail.SuccessVerify');
        }else if($user->email_verified_at){
            return view('VerifyEmail.HasVerify');
        }else{
            return view('VerifyEmail.InvalidVerify');

        }
        
    }

    public function verifyOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'otp_code' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Fails',
                'errors' => $validator->errors()
            ], 422);
        }
    
        $user = User::where('otp_code', $request->otp_code)->first();
        
        if ($user) {
            $user->email_verified_at = now();
            $user->otp_code = null;
            $user->save();
        
            // $token = $user->createToken('Token')->accessToken;
            return view('VerifyEmail.SuccessVerify');
        } 
    }
    

    public function resendVerification(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if($validator->fails()){
            return response()->json([
                'Error' => true,
                'Massage' => $validator->errors()
            ]); 
        }

        $users = User::where('email', $request->email)->first();

        if(!$users){
            return response()->json([
                'massage'=>'Email not found'
            ]);
        }else{
            if($users->email_verified_at){
                return response()->json([
                    'message' => 'Email already verified.'
                ]);
            }else{
                $verification = URL::temporarySignedRoute(
                    'verification.verify',
                    now()->addMinutes(60),
                    ['id' => $users->id, 'hash' => sha1($users->getEmailForVerification())]
                );
                $SendEmailVerifyJob = new SendEmailVerifyJob($users, $verification);
                dispatch($SendEmailVerifyJob);

                return response()->json([
                    'success'=>true,
                    'massage'=>'Please check your mail to activate account.'
                ]);
            }
        }
    }

    public function resendVerificationOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if($validator->fails()){
            return response()->json([
                'Error' => true,
                'Massage' => $validator->errors()
            ]); 
        }

        $users = User::where('email', $request->email)->first();

        if(!$users){
            return response()->json([
                'massage'=>'Email not found'
            ]);
        }else{
            if($users->email_verified_at){
                return response()->json([
                    'message' => 'Email already verified.'
                ]);
            }else{
                do {
                    $verificationOtp = mt_rand(1000, 9999);
                    $checkCode = User::where('otp_code', $verificationOtp)->first();
                } while ($checkCode);
                
                $SendEmailVerifyJob = new SendOtpJob($users, $verificationOtp);
                dispatch($SendEmailVerifyJob);

                return response()->json([
                    'success'=>true,
                    'massage'=>'Please check your mail to activate account.'
                ]);
            }
        }
    }
}

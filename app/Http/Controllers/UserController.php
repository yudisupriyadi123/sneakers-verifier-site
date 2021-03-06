<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;
use Validator;
use App\User;

class UserController extends Controller
{
    private $user;

    public function __construct(UserRepository $user)
    {
        $this->user = $user;
    }

    /**
     * Show user profile page
     *
     */
    public function profile($userid)
    {
        Log::debug('profile() is started', ['userid' => $userid]);
        $user = $this->user->withCount([
            'verification_items as verification_items_count',
            'verification_items as unreviewed_verification_items_count' => function ($query) {
                $query->where('status_review', 0);
            },
        ])->find($userid);

        $user->verification_items = $user->verification_items()->paginate(6);

        return view('user.profile', compact('user'));
    }

    public function change(Request $req)
    {
        $user = $req->user();

        if (! Hash::check($req->post('old_password'), $user->password)) {
            return redirect()->back()->with([
                'message' => 'Your current password is wrong.',
                'status' => 'FAIL',
            ]);
        }

        $return_message = [];

        // NOTE: this is not working, I don't know why.
        //$user->name     = $req->input('name', $user->name);
        //$user->email    = $req->input('email', $user->email);
        $user->name     = $req->input('name') ?: $user->name;
        $user->email    = $req->input('email') ?: $user->email;

        if ($req->post('new_password')) {
            if ($req->post('new_password') == $req->post('confirm_password')){
                $user->password = Hash::make($req->post('new_password'));
            } else {
                array_push($return_message, [
                    'message' => 'Your confirm password is wrong. But other data has been saved.',
                    'status'  => 'FAIL',
                ]);
            }
        }

        if ($req->hasFile('photo')) {
            $photo_valid = true;

            if (! $req->file('photo')->isValid()) {
                $photo_valid = false;

                array_push($return_message, [
                    'message' => 'There is a problem when uploading your photo',
                    'status'  => 'FAIL',
                ]);
            }

            $validator = Validator::make($req->all(), [
                'photo' => 'required|image',
            ]);
            if ($validator->fails()) {
                $photo_valid = false;

                array_push($return_message, [
                        'message' => 'Your photo is not valid.',
                        'status'  => 'FAIL',
                ]);
            }

            if ($photo_valid) {
                $old_photo_path = $user->photo_path;
                Storage::disk('public')->delete($old_photo_path);

                $req->photo->store('user_photo_profile', 'public');
                $new_photo   = $req->photo->hashName();
                $user->photo = $new_photo;
            }
        }

        $user->save();

        array_push($return_message, [
            'message' => 'Your data has been saved',
            'status'  => 'SUCCESS',
        ]);

        return redirect()->back()->with($return_message);
    }

    public function delete(Request $req, $id = null)
    {
        if (Auth::guard('web_admin')->check() AND $id != null) {
            Log::debug('executed admin');
            $user = User::find($id);
            $user->delete();

            return redirect('admin/user-list')->with([
                'message' => 'User has been deleted',
                'status'  => 'SUCCESS',
            ]);
        }

        $user = Auth::user();

        Auth::logout();

        $user->delete();

        return redirect('/');
    }

    public function all()
    {
        if (! Auth::guard('web_admin')->check()) return view('403');

        $users = User::orderBy('created_at', 'desc');
        $users = $users->paginate(20);

        return view('admin/user-list', compact('users'));
    }
}

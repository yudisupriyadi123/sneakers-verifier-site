<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\VerificationItem;
use App\VerificationItemLink;
use App\VerificationItemImage;
use App\Repositories\UserRepository;
use Validator;

class VerificationController extends Controller
{
    private $user;

    public function __construct(UserRepository $user)
    {
        $this->user = $user;
    }

    public function getReviewed()
    {
        $verification_items = VerificationItem::where('status_review', '=', VerificationItem::STATUS_REVIEWED)->paginate(6);

        return view('verification.list', compact('verification_items'));
    }

    public function getUnreviewed()
    {
        $verification_items = VerificationItem::where('status_review', '=', VerificationItem::STATUS_UNREVIEWED)->paginate(6);

        return view('verification.list', compact('verification_items'));
    }

    public function addVerificationRequestImagesBased(Request $req)
    {
        Log::debug('addVerificationRequestImagesBased() is started');
        Log::debug('sneakers images information:', ['num_image' => count($req->sneakers_images)]);

        $isAllValid = true;
        foreach ($req->sneakers_images as $img) {
            Log::debug('check status "uploading process" of one image');
            if (! $img->isValid()) $isAllValid = false;
        }
        if (! $isAllValid) {
            Log::error('Upload image process is fail');
            Log::debug('Redirecting user to previous page');
            return redirect()->back()->with([
                'message' => 'There is a problem when uploading images',
                'status'  => 'FAIL',
            ]);
        }

        $validator = Validator::make($req->all(), [
            'sneakers_images.*' => 'required|image',
        ]);
        if ($validator->fails()) {
            Log::debug('Images is not valid.');
            Log::debug('Redirecting user to previous page');
            return redirect()->back()->with([
                'message' => 'All or some of your image is not valid.',
                'status'  => 'FAIL',
            ]);
        }

        Log::debug('Uploading images by laravel is success');
        Log::debug('Uploaded images is valid');
        $images_path = [];
        foreach ($req->sneakers_images as $img) {
            Log::debug('Store uploaded file to public disk');
            $img->store('verification_sneakers_images', 'public');
            array_push($images_path, $img->hashName());
        }

        /* I know I can do all this without user model and just simple
         * assignment: `$verification_item->user_id = Auth::id()`.  But this is
         * just more safe to ensure user is exists. Maybe I am paranoid.
         * But who know about future risks?
         */
        $user = $this->user->find(Auth::id());

        $verification_item                = new VerificationItem;
        $verification_item->type          = VerificationItem::TYPE_IMAGE_BASED;
        $verification_item->status_review = VerificationItem::STATUS_UNREVIEWED;
        $user->verification_items()->save($verification_item);
        {
            Log::debug('Inner block executed');
            foreach ($images_path as $image_path) {
                Log::debug('image_path: ', ['image_path' => $image_path]);
                $verification_item_image       = new VerificationItemImage;
                $verification_item_image->path = $image_path;
                $verification_item->verification_item_images()->save($verification_item_image);
            }
        }

        Log::debug('addVerificationRequestImagesBased() is ended');

        return redirect()->route('public.verification.detail', ['id' => $verification_item->id]);
    }

    public function addVerificationRequestLinkBased(Request $req)
    {
        Log::debug('addVerificationRequestLinkBased started');
        /* I know I can do all this without user model and just simple
         * assignment: `$verification_item->user_id = Auth::id()`.  But this is
         * just more safe to ensure user is exists. Maybe I am paranoid.
         * But who know about future risks?
         */
        $user = $this->user->find(Auth::id());
        Log::debug('user model: ', ['user' => $user]);

        $verification_item                = new VerificationItem;
        $verification_item->type          = VerificationItem::TYPE_LINK_BASED;
        $verification_item->status_review = VerificationItem::STATUS_UNREVIEWED;
        $user->verification_items()->save($verification_item);
        {
            Log::debug('Inner block executed');
            $verification_item_link       = new VerificationItemLink;
            $verification_item_link->link = $req->link;
            $verification_item->verification_item_link()->save($verification_item_link);
        }

        Log::debug('addVerificationRequestLinkBased ended');

        return redirect()->route('public.verification.detail', ['id' => $verification_item->id]);
    }

    public function detail($id)
    {
        // TODO: use repository instead
        $verification_item = VerificationItem::find($id);

        if ($verification_item == null) {
            return redirect('user/'.Auth::user()->id)->with([
                'message' => 'Request cannot handled because the data is not found',
                'status'  => 'FAIL',
            ]);
        }

        return view('verification.detail', compact('verification_item'));
    }

    public function delete($id)
    {
        $verification_item = VerificationItem::find($id);
        if (Auth::guard('web_admin')->check())
        {
            $verification_item->delete();
            return redirect('admin/verification-list')->with([
                'message' => 'Your data has been deleted',
                'status'  => 'SUCCESS',
            ]);
        }
        else if (Gate::allows('delete-verification-item', $verification_item))
        {
            if ($verification_item->status_review == $verification_item->getStatusReviewAttribute(VerificationItem::STATUS_REVIEWED)) {
                return redirect()->back()->with([
                    'message' => 'Your data has been reviewed so it cannot be deleted',
                    'status'  => 'FAIL',
                ]);
            }

            // automatically delete relation record from table verification_item_images or verification_item_link
            $verification_item->delete();

            return redirect('user/'.Auth::user()->id)->with([
                'message' => 'Your data has been deleted',
                'status'  => 'SUCCESS',
            ]);
        }
        else {
            return redirect()->back()->with([
                'message' => 'You not allowed to delete this item',
                'status'  => 'FAIL',
            ]);
        }
    }

    public function showReviewResult($id)
    {
        $verification_item = VerificationItem::find($id);

        return view('verification.review_result', compact('verification_item'));
    }
}

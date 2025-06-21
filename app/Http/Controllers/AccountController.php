<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\File;
use App\Models\Review;

class AccountController extends Controller
{
    //This method will show register page
    public function register(){
        return view('account.register');
    }
    //This method will register a user
    public function processRegister(Request $request){
        $validator = Validator::make($request->all(),[
            'name' =>'required|min:3',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:5',
            'password_confirmation' => 'required',
            
        ]);
        if($validator->fails()){
            return redirect()->route('account.register')->withInput()->withErrors($validator);
        }
        //now register user

        $user =new user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = hash::make($request->password);
        $user->save();

        return redirect()->route('account.login')->with('success','You have registered successfully');

    }
    public function login(){
        return view('account.login');
    }
    public function authenticate(Request $request){
        $validator = Validator::make($request->all(),[
            'email' => 'required|email',
            'password' => 'required'


        ]);
        if ($validator->fails()){
            return redirect()->route('account.login')->withInput()->withErrors($validator);
        } 
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return redirect()->route('account.profile');
              
        } else{
            return redirect()->route('account.login')->with('error', 'Either email/password is incorrect');
        }
    }
    //This method will show user profile page
    public function profile(){
              $user = user::find(Auth::user()->id);
         

        return view('account.profile',[
            'user' => $user
        ]);
    }
     //This method will update user profile
     public function updateProfile(Request $request)
     {
         $rules = [
             'name' => 'required|min:3',
             'email' => 'required|email|unique:users,email,'.Auth::user()->id.',id',
         ];
 
      
 
         $validator = Validator::make($request->all(), $rules);
         
         if ($validator->fails()) {
             return redirect()->route('account.profile')->withInput()->withErrors($validator);
         }
 
         $user = User::find(Auth::user()->id);
         $user->name = $request->name;
         $user->email = $request->email;
 
         // Handle image upload
         if ($request->hasFile('image')) {
             // Create directories if they don't exist
             $uploadPath = public_path('uploads/profile');
             $thumbPath = public_path('uploads/profile/thumb');
             
             if (!File::exists($uploadPath)) {
                 File::makeDirectory($uploadPath, 0755, true);
             }
             
             if (!File::exists($thumbPath)) {
                 File::makeDirectory($thumbPath, 0755, true);
             }
 
             // Process image upload
             $image = $request->file('image');
             $ext = $image->getClientOriginalExtension();
             $imageName = time().'.'.$ext;
 
             // Save original image
             $image->move($uploadPath, $imageName);
 
             // Create and save thumbnail
             $manager = new ImageManager(new Driver());
             $img = $manager->read($uploadPath.'/'.$imageName);
             $img->cover(150, 150);
             $img->save($thumbPath.'/'.$imageName);
 
             // Delete old images if they exist
             if ($user->image) {
                 $oldImagePath = $uploadPath.'/'.$user->image;
                 $oldThumbPath = $thumbPath.'/'.$user->image;
                 
                 if (File::exists($oldImagePath)) {
                     File::delete (public_path('uploads/profile/'.$user->image));
                 }
                 
                 if (File::exists($oldThumbPath)) {
                     File::delete($oldThumbPath);
                 }
             }
 
             $user->image = $imageName;
         }
 
         $user->save();
 
         return redirect()->route('account.profile')->with('success', 'Profile Updated Successfully');
     }
    public function logout(){
        Auth::logout();
        return redirect()->route('account.login');
    }

    public function myReviews(Request $request){

        $reviews = Review::with('book')->where('user_id',Auth::user()->id);

        $reviews = $reviews->orderBy('created_at','DESC');

        if(!empty($request->keyword)){
            $reviews = $reviews->where('review','like','%'.$request->keyword.'%');

        }

        $reviews = $reviews->paginate(10);

        return view('account.my-reviews.my-reviews',[
            'reviews' => $reviews

    ]);
    }
    //This method will show edit review page
    public function editReview($id){
        $review = Review::where([
            'id' => $id,
            'user_id' => Auth::user()->id
        ])->with('book')->first();

          return view('account.my-reviews.edit-review',[
            'review' => $review

    ]);
    }
    //This method will updateReview
    public function updateReview($id, Request $request){

         $review = Review::findOrFail($id);

        $validator = Validator::make($request->all(),[
            'review' => 'required',
            'rating' => 'required'

        ]);
        if($validator->fails()){
            return redirect()->route('account.myReviews.editReview',$id)->withInput()->withErrors($validator);

        }

        $review->review = $request->review;
        $review->rating = $request->rating;
        $review->save();

        session()->flash('success','Review updated successfully.');
        return redirect()->route('account.myReviews');
    }
    public function deleteReview(Request $request){

        $id = $request->id;

        $review = Review::find($id);

        if($review == null){
            return response()->json([
                'status' => false
            ]);
        }
         $review ->delete();

         session()->flash('success','Review deleted successfully');
         
         return response()->json([
            'status' =>true,
            'message' => 'Review deleted successfully'

         ]);

    }
}

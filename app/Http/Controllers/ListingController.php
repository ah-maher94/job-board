<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ListingController extends Controller
{
    
    public function index(Request $request) {
        $listings = Listing::whenSearch($request->get('s'))
                        ->where('is_active', true)
                        ->with('tags')
                        ->latest()
                        ->get();

        $tags = Tag::orderBy('name')
                        ->get();

        if($request->has('tag')) {
            $tag = $request->get('tag');
            $listings = $listings->filter(function($listing) use($tag) {
                return $listing->tags->contains('slug', $tag);
            });
        }
        
        return view('listings.index', compact('listings', 'tags'));
    }


    public function show(Request $request, Listing $listing) {
        return view('listings.show', compact('listing'));
    }


    public function apply(Listing $listing, Request $request) {
        $listing->clicks()
                ->create([
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip()
                ]);

        return redirect()->to($listing->apply_link);
    }


    public function create() {
        return view('listings.create');
    }


    public function store(Request $request) {
        $validationArray = [
            'title' => 'required',
            'company' => 'required',
            'location' => 'required',
            'logo' => 'file|max:2048',
            'apply_link' => 'required|url',
            'content' => 'required',
            'payment_method_id' => 'required',
        ];

        if(!Auth::check()) {
            $validationArray = array_merge($validationArray, [
                'email' => 'required|email|unique:users',
                'name' => 'required',
                'password' => 'required|confirmed|min:5'
            ]);
        }

        $validatedItems = $request->validate($validationArray);

        $user = Auth::user();

        if(!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $user->createAsStripeCustomer();
            Auth::login($user);
        }

        try {
            $amount = 2000;
            if($request->filled('is_highlighted')) {
                $amount += 500;
            }
            $user->charge($amount, $request->payment_method_id);

            $md = new \ParsedownExtra();

            $listing = $user->listings()->create([
                'title' => $validatedItems['title'],
                'company' => $validatedItems['company'],
                'location' => $validatedItems['location'],
                'slug' => Str::slug('title').'-'.rand(1111, 9999),
                'logo' => basename($validatedItems['logo']->store('public/images')),
                'apply_link' => $validatedItems['apply_link'],
                'is_highlighted' => $request->filled('is_highlighted'),
                'is_active' => true,
                'content' => $md->text($validatedItems['content']),
            ]);

            foreach (explode(',', $request->tags) as $tag) {
                $tag = Tag::firstOrCreate([
                    'slug' => Str::slug(trim($tag))
                ],[
                    'name' => ucwords(trim($tag))
                ]);
                $tag->listing()->attach($listing->id);
            }

            return redirect()->route('dashboard');

        }catch(\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

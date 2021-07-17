<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Click;
use App\Models\Tag;

class Listing extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName() {
        return 'slug';
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function clicks() {
        return $this->hasMany(Click::class);
    }

    public function tags() {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeWhenSearch($query, $search) {
        return $query->when($search, function($query) use($search){
            return $query->where('title', 'like', '%'.$search.'%')
                         ->orWhere('company', 'like', '%'.$search.'%');
        });
    }

}

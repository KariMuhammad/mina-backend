<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'image_path'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return app(\App\Services\ImageService::class)->url($this->image_path);
        }
        return null;
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

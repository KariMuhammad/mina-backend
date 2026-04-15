<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['image_url', 'image_path', 'title', 'is_active'];

    protected $appends = ['image_full_url'];

    public function getImageFullUrlAttribute()
    {
        if ($this->image_path) {
            return app(\App\Services\ImageService::class)->url($this->image_path);
        }
        return $this->image_url;
    }
}

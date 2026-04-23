<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['image_url', 'image_path', 'title', 'link', 'is_active', 'order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['image_full_url'];

    public function getImageFullUrlAttribute()
    {
        if ($this->image_path) {
            return app(\App\Services\ImageService::class)->url($this->image_path);
        }
        return $this->image_url;
    }
}

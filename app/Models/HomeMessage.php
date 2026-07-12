<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HomeMessage extends Model
{
    protected $fillable = [
        'image',
        'title',
        'description',
    ];

    public function imageUrl(): ?string
    {
        $image = $this->image;
        if (! $image || trim($image) === '') {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        $cleanImage = ltrim($image, '/');
        if (Str::startsWith($cleanImage, 'storage/')) {
            return url($cleanImage);
        }

        return url('storage/'.$cleanImage);
    }
}

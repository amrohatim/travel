<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParentCompany extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'image',
    ];

    public function publicUrl(): string
    {
        return route('companies.show', $this);
    }

    public function appDeepLinkUrl(): string
    {
        return sprintf(
            '%s://company/%d',
            config('deep_links.custom_scheme', 'safriat'),
            $this->id
        );
    }

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

        if (Storage::disk('public')->exists($cleanImage)) {
            return url('storage/'.$cleanImage);
        }

        return url($cleanImage);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(User::class, 'parent_company_id')
            ->where('role', 'office');
    }
}

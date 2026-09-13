<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationBranding extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_name',
        'short_name',
        'brand_mark',
        'tagline',
        'logo_url',
        'primary_color',
        'accent_color',
    ];

}

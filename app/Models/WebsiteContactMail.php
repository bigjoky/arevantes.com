<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteContactMail extends Model
{
    protected $table = 'website_contact_mail';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recipient' => 'encrypted', 'attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteGraphTestAuthorization extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'website_graph_test_authorizations';
    protected $guarded = [];
}

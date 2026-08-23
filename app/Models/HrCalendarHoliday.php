<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrCalendarHoliday extends Model
{
    protected $fillable = ['holiday_date', 'name'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }
}

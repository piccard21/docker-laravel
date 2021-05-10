<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;


class Student extends Eloquent
{
    use HasFactory;
    protected $connection = 'mongodb';
    protected $collection = 'students';
    protected $fillable = [
        'first_name','last_name', 'address'
    ];
}

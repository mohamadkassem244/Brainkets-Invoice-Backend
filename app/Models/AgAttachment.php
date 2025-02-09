<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgAttachment extends Model
{
    use HasFactory;

    protected $table = 'ag_attachment';

    protected $fillable = [
        'table_name',
        'row_id',
        'type',
        'file_path',
        'file_name',
        'file_extension',
        'cdn_uploaded',
        'file_size',
    ];

    protected $casts = [
        'table_name' => 'string',
        'row_id' => 'integer',
        'type' => 'integer',
        'file_path' => 'string',
        'file_name' => 'string',
        'file_extension' => 'string',
        'cdn_uploaded' => 'boolean',
        'file_size' => 'string',
    ];
}

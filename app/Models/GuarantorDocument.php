<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuarantorDocument extends Model
{
    protected $fillable = ['guarantor_id', 'credit_guarantor_id', 'type', 'original_name', 'path', 'mime_type', 'size', 'uploaded_by'];

    public function guarantor() { return $this->belongsTo(Guarantor::class); }
    public function guarantee() { return $this->belongsTo(CreditGuarantor::class, 'credit_guarantor_id'); }
}

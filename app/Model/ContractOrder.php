<?php

declare (strict_types=1);
namespace App\Model;

/**
 */
class ContractOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contract_orders';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    public function order() {
        return $this->belongsTo(ContractIndex::class);
    }

    public function index() {
        return $this->belongsTo(ContractIndex::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
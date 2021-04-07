<?php

declare (strict_types=1);
namespace App\Model;

/**
 */
class FundRewardLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fund_reward_logs';
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
        return $this->belongsTo(FundOrder::class);
    }
}
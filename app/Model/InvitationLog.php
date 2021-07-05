<?php

declare (strict_types=1);
namespace App\Model;

/**
 */
class InvitationLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invitation_logs';
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


    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function child() {
        return $this->belongsTo(User::class, 'child_id', 'id');
    }

}
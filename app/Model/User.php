<?php

declare (strict_types=1);
namespace App\Model;

use HyperfExt\Jwt\Contracts\JwtSubjectInterface;

/**
 */
class User extends Model implements JwtSubjectInterface
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';
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

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJwtCustomClaims():array
    {
        return [];
    }

    public function children() {
        return $this->hasMany(InvitationLog::class, 'id', 'user_id');
    }

    public function parents() {
        return $this->hasMany(InvitationLog::class, 'id', 'child_id');
    }
}
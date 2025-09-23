<?php

namespace LucaLongo\LaravelHelpdesk\Tests\Fakes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LucaLongo\LaravelHelpdesk\Database\Factories\UserFactory;

class User extends Model
{
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (isset($attributes['id'])) {
            $this->exists = true;
        }
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
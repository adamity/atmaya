<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    use HasFactory;

    protected $table = 'telegram_users';
    protected $fillable = [
        'telegram_id',
        'bot_is_typing',
        'mode',
        'curr_question_index',
        'answer_1',
        'answer_2',
        'answer_3',
        'answer_4',
        'answer_5'
    ];

    public function bot()
    {
        return $this->hasOne(TelegramBot::class);
    }
}

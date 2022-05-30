<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\TelegramBot;
use App\Models\TelegramUser;
use App\Traits\RequestTrait;
use Illuminate\Http\Request;
use Orhanerday\OpenAi\OpenAi;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TelegramController extends Controller
{
    use RequestTrait;

    public function webhook()
    {
        $url = preg_replace("/^http:/i", "https:", url(route('webhook')));

        return $this->apiRequest('setWebhook', [
            'url' => $url,
        ]) ? ['success'] : ['something wrong'];
    }

    public function index()
    {
        $open_ai = new OpenAi(env('OPEN_AI_API_KEY'));
        $result = json_decode(file_get_contents('php://input'));

        $telegramId = $result->message->chat->id;
        $text = $result->message->text;

        // Simple telegram user registration
        $telegramUser = TelegramUser::firstOrCreate(['telegram_id' => $telegramId]);
        $telegramBot = TelegramBot::firstOrCreate(['telegram_user_id' => $telegramUser->id]);

        // Last 7 messages
        $messages = Message::where([['sender_id', $telegramUser->id], ['receiver_id', $telegramBot->id]])
            ->orWhere([['sender_id', $telegramBot->id], ['receiver_id', $telegramUser->id]])
            ->orderBy('id', 'desc')
            ->take(11)
            ->get();
        $messages = $messages->sortBy('id');

        // Disable for now
        // $tr = new GoogleTranslate(); // Translates to 'en' from auto-detected language by default
        // $tr->setSource('ms'); // Translate from Malay
        // $tr->setTarget('en'); // Translate to English
        // $humanTextTranslated = $tr->translate($text);
        $humanTextTranslated = $text;

        // Temporary user validation for development
        if ($telegramId != "789700107") {
            $this->apiRequest('sendMessage', [
                'chat_id' => $telegramId,
                'text' => 'You are not allowed to use this bot. ~Zul',
                'parse_mode' => 'html',
            ]);

            return;
        }

        $this->apiRequest('sendChatAction', [
            'chat_id' => $telegramId,
            'action' => 'typing',
        ]);

        $biodata = [
            'fullname' => 'Atmaya Kyo',
            'nickname' => 'Kyo',
            'birthday' => '1996-10-10',
            'characteristic' => 'empathic, creative, and curious',
            'goals' => 'to be a good and supportive friend',
        ];

        // TODO: Create table for storing user's messages and separate the context from the message
        $prompt = "The following is a conversation with an AI companion named " . $biodata['fullname'] . " can be called " . $biodata['nickname'] . ".";
        $prompt .= "The companion is " . $biodata['characteristic'] . " whose primary goal is " . $biodata['goals'] . ".\n\n";
        foreach ($messages as $conversation) {
            $prompt .= $conversation->sender_type == 'telegram_user' ? 'Human: ' : 'AI: ';
            $prompt .= trim($conversation->message) . "\n";
        }
        $prompt .= "Human: " . $humanTextTranslated . "\nAI:";

        // Store the user's message
        Message::create([
            'sender_id' => $telegramUser->id,
            'receiver_id' => $telegramBot->id,
            'sender_type' => 'telegram_user',
            'message' => $text,
        ]);

        $data = $open_ai->complete([
            'engine' => 'davinci',
            'prompt' => $prompt,
            'temperature' => 0.9,
            'max_tokens' => 200,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.6,
            'stop' => ["Human:", "AI:"],
        ]);

        $complete = json_decode($data);

        // Disable for now
        // $tr->setSource('en'); // Translate from English
        // $tr->setTarget('ms'); // Translate to Malay
        // $AITextTranslated = $tr->translate($complete->choices[0]->text);
        $AITextTranslated = $complete->choices[0]->text;

        // Store the AI's message
        Message::create([
            'sender_id' => $telegramBot->id,
            'receiver_id' => $telegramUser->id,
            'sender_type' => 'telegram_bot',
            'message' => $AITextTranslated,
        ]);

        $response = $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => $AITextTranslated,
            'parse_mode' => 'html',
        ]);

        return $prompt;
    }
}

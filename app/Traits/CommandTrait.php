<?php

namespace App\Traits;

use App\Models\TelegramUser;
use App\Traits\ComponentTrait;
use App\Traits\RequestTrait;
use App\Traits\TextTrait;
use App\Traits\NLPTrait;

trait CommandTrait
{
    use ComponentTrait;
    use RequestTrait;
    use TextTrait;
    use NLPTrait;

    const QUESTIONS = [
        "What symptoms or health concerns are you experiencing today?",
        "How recently did these symptoms start? Please provide a timeframe or number of days.",
        "Have you had a similar issue in the past?",
        "Are you currently taking any medications or supplements?",
        "Do you have any known allergies or intolerances?",
    ];

    private function getCommands($request, $text)
    {
        $telegramId = $this->getTelegramId($request);
        TelegramUser::firstOrCreate(['telegram_id' => $telegramId]);

        $method = "sendMessage";

        $option = [
            [
                ["text" => "👨‍💻 Customer Service"],
                ["text" => "👩‍⚕️ Preconsult"],
            ],
        ];

        $params['chat_id'] = $telegramId;
        $params['parse_mode'] = 'html';
        $params['text'] = $this->commandText($text);
        $params['reply_markup'] = $this->keyboardButton($option);

        return $this->apiRequest($method, $params);
    }

    private function startCustomerService($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();

        $teleUser->mode = "customer_service";
        $teleUser->save();

        $method = "sendMessage";

        $option = [
            [
                ["text" => "❌ Cancel"],
            ],
        ];

        $params['chat_id'] = $telegramId;
        $params['parse_mode'] = 'html';
        $params['text'] = $this->rephraseSentence("Hello! How can we assist you with our product or any questions you have today?");
        $params['reply_markup'] = $this->keyboardButton($option);

        return $this->apiRequest($method, $params);
    }

    private function startPreconsult($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();

        $teleUser->mode = "preconsult";
        $teleUser->curr_question_index = 0;
        $teleUser->save();

        $method = "sendMessage";

        $option = [
            [
                ["text" => "❌ Cancel"],
            ],
        ];

        $params['chat_id'] = $telegramId;
        $params['parse_mode'] = 'html';
        $params['text'] = $this->rephraseSentence("Hello! We're ready to start the Pre-Consultation. You can cancel anytime if you decide not to proceed. Let's start with the first question.");
        $params['reply_markup'] = $this->keyboardButton($option);

        $this->apiRequest($method, $params);

        $this->apiRequest('sendChatAction', [
            'chat_id' => $telegramId,
            'action' => 'typing',
        ]);

        return $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => $this->rephraseSentence(self::QUESTIONS[$teleUser->curr_question_index]),
            'parse_mode' => 'html',
        ]);
    }

    private function updateSession($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();
        $action = $request->message->text;

        if ($action == "/cancel" || $action == '❌ Cancel') {
            $response = $this->cancelOperation($request);
        } else if ($teleUser->mode == "customer_service") {
            $response = $this->apiRequest('sendMessage', [
                'chat_id' => $telegramId,
                'text' => $this->chatbotResponse($action),
                'parse_mode' => 'html',
            ]);
        } else if ($teleUser->mode == "preconsult") {
            $is_valid = $this->validateResponse(self::QUESTIONS[$teleUser->curr_question_index], $action);

            if ($is_valid) {
                eval('$teleUser->answer_' . ($teleUser->curr_question_index + 1) . ' = $action;');
                $teleUser->curr_question_index += 1;
                $teleUser->save();
                $append = "Question " . ($teleUser->curr_question_index) . "/" . count(self::QUESTIONS) . "\n\n";

                if ($teleUser->curr_question_index == count(self::QUESTIONS)) {
                    $response = $this->apiRequest('sendMessage', [
                        'chat_id' => $telegramId,
                        'text' => $this->rephraseSentence("Thank you for your time. We will get back to you as soon as possible."),
                        'parse_mode' => 'html',
                    ]);

                    // TODO: Generate report and send to doctor
                    // $this->cancelOperation($request);
                } else {
                    $response = $this->apiRequest('sendMessage', [
                        'chat_id' => $telegramId,
                        'text' => $append . $this->rephraseSentence(self::QUESTIONS[$teleUser->curr_question_index]),
                        'parse_mode' => 'html',
                    ]);
                }
            } else {
                $append = "Question " . ($teleUser->curr_question_index) . "/" . count(self::QUESTIONS) . "\n\n" . $this->rephraseSentence("I'm sorry, but you are not answering the question.");
                $response = $this->apiRequest('sendMessage', [
                    'chat_id' => $telegramId,
                    'text' => $append . ' ' . $this->rephraseSentence(self::QUESTIONS[$teleUser->curr_question_index]),
                    'parse_mode' => 'html',
                ]);
            }
        }

        return $response;
    }

    private function cancelOperation($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();

        $teleUser->mode = null;
        $teleUser->curr_question_index = null;
        $teleUser->answer_1 = null;
        $teleUser->answer_2 = null;
        $teleUser->answer_3 = null;
        $teleUser->answer_4 = null;
        $teleUser->answer_5 = null;
        $teleUser->save();

        return $this->getCommands($request, null);
    }

    private function testSend($reply = 'You are not allowed to use this bot. ~Zul', $telegramId = '789700107')
    {
        // If $reply is not string, convert using json_encode
        if (!is_string($reply)) $reply = json_encode($reply, JSON_PRETTY_PRINT);

        // Temporary user validation for development
        $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => $reply,
            'parse_mode' => 'html',
        ]);

        exit;
    }
}

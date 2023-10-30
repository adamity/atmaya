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

    //     "What symptoms or health concerns are you experiencing today?",
    //     "How recently did these symptoms start? Please provide a timeframe or number of days.",
    //     "Have you had a similar issue in the past?",
    //     "Are you currently taking any medications or supplements?",
    //     "Do you have any known allergies or intolerances?",

    // PHP < 8.2 cannot use const in trait
    // const QUESTIONS = [
    //     "Can you describe the symptoms you're currently experiencing in detail?",
    //     "When did these symptoms first start appearing?",
    //     "On a scale of 1 to 10, where 1 is mild and 10 is severe, how would you rate your discomfort or pain?",
    //     "Are you currently taking any medications or do you have any known allergies?",
    //     "Do you have any pre-existing medical conditions?",
    // ];

    private function getCommands($request, $text)
    {
        $telegramId = $this->getTelegramId($request);
        TelegramUser::firstOrCreate(['telegram_id' => $telegramId]);

        $method = "sendMessage";

        // [
        //     ["text" => "🤖 Quick Response"],
        // ],
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
        $params['text'] = "👨‍💻\n\n" . $this->rephraseSentence("Hello! How can we assist you with our product?");
        $params['reply_markup'] = $this->keyboardButton($option);

        return $this->apiRequest($method, $params);
    }

    private function startQuickResponse($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();

        $teleUser->mode = "quick_response";
        $teleUser->save();

        $method = "sendMessage";

        $option = [
            [
                ["text" => "❌ Cancel"],
            ],
        ];

        $params['chat_id'] = $telegramId;
        $params['parse_mode'] = 'html';
        $params['text'] = "🤖\n\n" . $this->rephraseSentence("Hello! Do you have any medical questions?") . "\n\n<b>Disclaimer: </b><i>This isn't a substitute for medical advice; consult a healthcare professional for personalized guidance.</i>";
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
        $params['text'] = "👩‍⚕️\n\n" . $this->rephraseSentence("Hello! We're ready to start the Pre-Consultation. You can cancel anytime if you decide not to proceed. Let's start with the first question.");
        $params['reply_markup'] = $this->keyboardButton($option);

        $this->apiRequest($method, $params);

        $this->apiRequest('sendChatAction', [
            'chat_id' => $telegramId,
            'action' => 'typing',
        ]);

        return $this->sendQuestion($teleUser);
    }

    private function updateSession($request)
    {
        // "Do you have any pre-existing medical conditions?",
        $QUESTIONS = [
            "Please provide your respiratory rate (breaths per minute), heart rate (beats per minute), blood pressure (systolic/diastolic), temperature (°C), and oxygen saturation (%) from your latest vitals check.",
            "Can you describe the symptoms you're currently experiencing in detail?",
            "When did these symptoms first start appearing?",
            "On a scale of 1 to 10, where 1 is mild and 10 is severe, how would you rate your discomfort or pain?",
            "Are you currently taking any medications or do you have any known allergies?",
        ];

        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();
        $action = $request->message->text;

        if ($action == "/cancel" || $action == '❌ Cancel') {
            $response = $this->cancelOperation($request);
        } else if ($teleUser->mode == "customer_service") {
            $message = $this->customerSupport($action);
            $response = $this->apiRequest('sendMessage', [
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => 'html',
            ]);
        } else if ($teleUser->mode == "quick_response") {
            $message = $this->chatbotResponse($action);
            $response = $this->apiRequest('sendMessage', [
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => 'html',
            ]);
        } else if ($teleUser->mode == "preconsult") {
            $is_valid = $this->validateResponse($QUESTIONS[$teleUser->curr_question_index], $action);

            if ($is_valid) {
                eval('$teleUser->answer_' . ($teleUser->curr_question_index + 1) . ' = $action;');
                $teleUser->curr_question_index += 1;
                $teleUser->save();

                if ($teleUser->curr_question_index == count($QUESTIONS)) {
                    $message = "👩‍⚕️\n\n" . $this->rephraseSentence("Thank you for your time. We will get back to you as soon as possible.");
                    $response = $this->apiRequest('sendMessage', [
                        'chat_id' => $telegramId,
                        'text' => $message,
                        'parse_mode' => 'html',
                    ]);

                    $this->submitReport($teleUser, $request);
                } else {
                    $response = $this->sendQuestion($teleUser);
                }
            } else {
                $response = $this->sendQuestion($teleUser, "I'm sorry, but you are not answering the question.");
            }
        }

        return $response;
    }

    private function sendQuestion($teleUser, $text = null)
    {
        // "Do you have any pre-existing medical conditions?",
        $QUESTIONS = [
            "Please provide your respiratory rate (breaths per minute), heart rate (beats per minute), blood pressure (systolic/diastolic), temperature (°C), and oxygen saturation (%) from your latest vitals check.",
            "Can you describe the symptoms you're currently experiencing in detail?",
            "When did these symptoms first start appearing?",
            "On a scale of 1 to 10, where 1 is mild and 10 is severe, how would you rate your discomfort or pain?",
            "Are you currently taking any medications or do you have any known allergies?",
        ];

        $question_num = $teleUser->curr_question_index + 1;
        $append = "👩‍⚕️\n\n<i>Question " . $question_num . "/" . count($QUESTIONS) . "</i>\n\n";
        if ($text) $append .= $this->rephraseSentence($text) . ' ';
        $message = $append . $this->rephraseSentence($QUESTIONS[$teleUser->curr_question_index]);

        return $this->apiRequest('sendMessage', [
            'chat_id' => $teleUser->telegram_id,
            'text' => $message,
            'parse_mode' => 'html',
        ]);
    }

    private function cancelOperation($request)
    {
        $telegramId = $this->getTelegramId($request);
        $teleUser = TelegramUser::where('telegram_id', $telegramId)->first();

        $message = $teleUser->mode ? "Operation cancelled. What would you like to do?" : "No active operation. What would you like to do?";
        $teleUser->mode = null;
        $teleUser->curr_question_index = null;
        $teleUser->answer_1 = null;
        $teleUser->answer_2 = null;
        $teleUser->answer_3 = null;
        $teleUser->answer_4 = null;
        $teleUser->answer_5 = null;
        $teleUser->save();

        return $this->getCommands($request, $message);
    }

    private function submitReport($teleUser, $request)
    {
        // "Do you have any pre-existing medical conditions?",
        $QUESTIONS = [
            "Please provide your respiratory rate (breaths per minute), heart rate (beats per minute), blood pressure (systolic/diastolic), temperature (°C), and oxygen saturation (%) from your latest vitals check.",
            "Can you describe the symptoms you're currently experiencing in detail?",
            "When did these symptoms first start appearing?",
            "On a scale of 1 to 10, where 1 is mild and 10 is severe, how would you rate your discomfort or pain?",
            "Are you currently taking any medications or do you have any known allergies?",
        ];

        $report = "Pre-consultation survey:\n\n";

        for ($i = 1; $i <= count($QUESTIONS); $i++) {
            $question = $QUESTIONS[$i - 1];
            $answer = null;
            eval('$answer = $teleUser->answer_' . $i . ';');

            $report .= $question . "\n";
            $report .= "Answer: " . $answer . "\n\n";
        }

        $this->apiRequest('sendMessage', [
            'chat_id' => $teleUser->telegram_id,
            'text' => "Generating report in the background, please wait...",
            'parse_mode' => 'html',
        ]);
        $generatedReport = $this->generateReport($report);
        $this->cancelOperation($request);

        $this->apiRequest('sendChatAction', [
            'chat_id' => $teleUser->telegram_id,
            'action' => 'typing',
        ]);

        return $this->apiRequest('sendMessage', [
            'chat_id' => $teleUser->telegram_id,
            'text' => "📝\n\n<i>On Doctor's Side</i>\n\n" . $generatedReport,
            'parse_mode' => 'html',
        ]);
    }

    private function getTelegramId($request)
    {
        if (isset($request->message)) {
            $telegramId = $request->message->from->id;
        } else if (isset($request->callback_query)) {
            $telegramId = $request->callback_query->from->id;
        }

        return $telegramId;
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
